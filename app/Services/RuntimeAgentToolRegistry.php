<?php
namespace App\Services;

/**
 * Request-local model adapter. Existing business registries remain the execution
 * authority; only a fixed skill identifier can activate private runtime Markdown.
 * No paths, arbitrary SQL, public MCP registration or new persistence.
 */
final class RuntimeAgentToolRegistry
{
    private array $catalog;
    private array $bundle;
    private array $activity = [];
    private array $results = [];
    private array $retrieval = [];
    private array $products = [];
    private array $loadedSkills = [];

    public function __construct(private string $surface,private ?array $actor,private AgentToolRegistry|SupportToolRegistry $business) {
        $this->catalog=AgentInstructionService::skillCatalog($surface,$actor);
        if(($surface==='support')!==($business instanceof SupportToolRegistry))
            throw new \InvalidArgumentException('Registry does not match agent surface.');
        $this->bundle=AgentInstructionService::modelBundle($surface,$actor);
    }

    public function instructionPrompt(): string {
        AgentTraceService::observeInstructionManifest($this->bundle['manifest']);
        return $this->bundle['text'];
    }

    public function declarations(): array {
        $policy=$this->surface==='admin'?AgentCapabilityPolicy::ENTERPRISE:AgentCapabilityPolicy::SUPPORT;
        $tools=array_values(array_filter($this->business->declarations(),fn(array $t):bool=>
            AgentCapabilityPolicy::allows($policy,$t['name'],$this->actor)));
        $tools[]=['name'=>'read_runtime_skill','description'=>'Load detailed operating instructions for an available runtime skill by its catalogue identifier. No business data lookup and no file path.',
            'parameters'=>['type'=>'object','properties'=>['skill'=>['type'=>'string','enum'=>array_keys($this->catalog)]],'required'=>['skill']]];
        return $tools;
    }

    public function has(string $name): bool {
        return in_array($name,array_column($this->declarations(),'name'),true);
    }

    private function activate(string $id): array {
        $skill=$this->loadedSkills[$id]??=AgentInstructionService::loadSkill($this->surface,$id,$this->actor);
        $manifest=$this->bundle['manifest'];
        if(!in_array($skill['file']['path'],array_column($manifest['files'],'path'),true)) {
            $manifest['files'][]=$skill['file'];
            $this->bundle['manifest']=AgentInstructionService::fingerprintManifest($manifest);
        }
        AgentTraceService::observeInstructionManifest($this->bundle['manifest']);
        return ['skill'=>$id,'instructions'=>$skill['instructions']];
    }

    public function run(string $name,array $args): array {
        if(!$this->has($name))return ['error'=>'Not permitted'];
        if($name==='read_runtime_skill') {
            if(array_keys($args)!==['skill']||!is_string($args['skill'])||!isset($this->catalog[$args['skill']]))
                return ['error'=>'Unknown or unavailable runtime skill.'];
            $span=AgentTraceService::startSpan('retrieval','runtime_skill');
            try {
                $result=$this->activate($args['skill']);
                AgentTraceService::endSpan($span,'complete',['result_count'=>1]);
                return $result;
            } catch(AgentBudgetException $e) {
                throw $e;
            } catch(\Throwable) {
                AgentTraceService::endSpan($span,'error');
                return ['error'=>'Runtime skill unavailable.'];
            }
        }
        // Apply relevant task guidance even if the model omitted a separate activation.
        $guidance=[];
        try {
            foreach($this->catalog as $id=>$skill)if(in_array($name,$skill['tools'],true))$guidance[]=$this->activate($id);
        } catch(AgentBudgetException $e) { throw $e; }
        catch(\Throwable) { return ['error'=>'Runtime skill unavailable.']; }
        if($this->surface==='admin') {
            $result=$this->business->run($name,$args);
        } else {
            $start=microtime(true);
            $span=AgentTraceService::startSpan('tool',$name);
            try { $result=$this->business->run($name,$args); }
            catch(AgentBudgetException $e) { throw $e; }
            catch(\Throwable) { $result=['error'=>'Lookup unavailable.']; }
            $evidence=AgentTraceService::toolEvidence($name,$args,$result);
            $status=isset($result['error'])?'error':'complete';
            $this->activity[]=['name'=>$name,'status'=>$status,'duration_ms'=>(int)round((microtime(true)-$start)*1000),'evidence'=>$evidence];
            AgentTraceService::endSpan($span,$status,['evidence'=>$evidence]);
            if(!isset($result['error'])) {
                $this->results[$name]=$result;
                if($name==='public_catalogue_summary')$this->retrieval[]=AgentTraceService::safeRetrieval([
                    'intent'=>'catalogue_summary','status'=>'matched','reason'=>'model_tool_lookup','result_count'=>$result['total']]);
                if(in_array($name,['public_catalog','public_best_sellers'],true)) {
                    $sources=[];
                    foreach((array)($result['products']??[]) as $p) {
                        if(!is_array($p)||!is_string($p['slug']??null)||PublicKnowledgeService::productPath($p['slug'])===null)continue;
                        $this->products[$p['slug']]=$p;
                        $sources[]=['id'=>'product:'.rawurlencode($p['slug']),'last_updated'=>null];
                    }
                    $this->retrieval[]=AgentTraceService::safeRetrieval(['intent'=>$name==='public_best_sellers'?'best_sellers':'catalogue_search','status'=>$sources?'matched':'no_match','reason'=>'model_tool_lookup','result_count'=>count($sources),'sources'=>$sources]);
                }
            }
        }
        if($guidance)$result['runtime_guidance']=$guidance;
        return $result;
    }

    public function activity(): array { return $this->activity; }
    public function result(string $tool): ?array { return $this->results[$tool]??null; }
    public function retrieval(): array { return $this->retrieval; }
    public function products(): array { return array_values($this->products); }
}
