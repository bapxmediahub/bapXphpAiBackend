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
    private array $available = [];

    public function __construct(private string $surface,private ?array $actor,private AgentToolRegistry|SupportToolRegistry $business,string $question='') {
        if(($surface==='support')!==($business instanceof SupportToolRegistry))
            throw new \InvalidArgumentException('Registry does not match agent surface.');
        $policy=$surface==='admin'?AgentCapabilityPolicy::ENTERPRISE:AgentCapabilityPolicy::SUPPORT;
        $modules = null;
        foreach($business->declarations() as $tool) {
            $name = $tool['name'];
            if (!AgentCapabilityPolicy::allows($policy, $name, $actor)) continue;
            $module = AgentCapabilityPolicy::requiredModule($policy, $name);
            if ($module !== null) {
                $modules ??= $this->discoverableModules();
                if (!($modules[$module] ?? false)) continue;
            }
            $this->available[$name] = $tool;
        }
        $this->bundle = AgentInstructionService::modelBundle($surface, $actor, array_keys($this->available));
        $this->catalog = $this->bundle['skills'];
        // The current registries are small (13 Support / 14 Enterprise).
        // Authorize every declaration once; the model, not lexical ranking,
        // selects the business operation. Skill bodies still load on demand.
    }

    private function discoverableModules(): array {
        try {
            return (new SettingsService())->modules(requireReadable: true);
        } catch (\RuntimeException | \JsonException) {
            // Unavailable settings cannot establish that a public module is enabled.
            return [];
        }
    }

    public function instructionPrompt(): string {
        AgentTraceService::observeInstructionManifest($this->bundle['manifest']);
        // The wire declarations already contain the authoritative descriptions.
        // Do not duplicate the entire tool catalogue in the instruction prompt.
        return $this->bundle['text'] . "\n\nUse the authorized business capability catalogue provided in the function declarations. All currently eligible business functions are callable directly. Tool search is optional metadata discovery, not a prerequisite for execution.";
    }

    public function declarations(): array {
        $tools=array_values($this->available);
        if ($this->catalog !== []) {
            $tools[]=['name'=>'read_runtime_skill','description'=>'Load detailed operating instructions for an available runtime skill by its catalogue identifier. No business data lookup and no file path.',
                'parameters'=>['type'=>'object','properties'=>['skill'=>['type'=>'string','enum'=>array_keys($this->catalog)]],'required'=>['skill']]];
        }
        $tools[]=['name'=>'tool_search','description'=>'Optionally search metadata for authorized business capabilities by a short query or exact tool identifier. Every eligible function is already advertised and callable. This returns descriptions, not business records.',
            'parameters'=>['type'=>'object','properties'=>['query'=>['type'=>'string','minLength'=>1,'maxLength'=>160],'limit'=>['type'=>'integer','minimum'=>1,'maximum'=>4]],'required'=>['query'],'additionalProperties'=>false]];
        return $tools;
    }

    private static function tokens(string $text): array {
        preg_match_all('/[\p{L}\p{N}]+/u',mb_strtolower($text),$matches);
        $stop=['a','an','the','to','of','for','in','is','are','it','my','your','me','i','you','how','what','which','with','and','or','no','not','such','all','can','could','please','show','get','read','use','do','does','by','on','at'];
        return array_values(array_unique(array_filter($matches[0],static fn(string $word):bool=>mb_strlen($word)>1&&!in_array($word,$stop,true))));
    }

    private function rank(string $query,int $limit): array {
        $query=trim(mb_strtolower($query));
        if(isset($this->available[$query]))return [$query];
        // An unknown exact identifier must not resolve to another capability.
        if(preg_match('/^[a-z][a-z0-9_]*_[a-z0-9_]+$/D',$query))return [];
        $words=self::tokens($query);$scores=[];
        foreach($this->available as $name=>$tool){
            $nameWords=self::tokens($name);
            $descriptionWords=self::tokens($tool['description'].' '.implode(' ',array_keys((array)($tool['parameters']['properties']??[]))));
            $score=5*count(array_intersect($words,$nameWords))+count(array_intersect($words,$descriptionWords));
            if($score>0)$scores[$name]=$score;
        }
        arsort($scores,SORT_NUMERIC);
        return array_slice(array_keys($scores),0,$limit);
    }

    private function search(array $args): array {
        $query=$args['query']??null;$limit=$args['limit']??3;
        if(array_diff(array_keys($args),['query','limit'])||!is_string($query)||trim($query)===''||mb_strlen($query)>160||!is_int($limit)||$limit<1||$limit>4)return ['error'=>'Invalid tool search.'];
        $span=AgentTraceService::startSpan('retrieval','tool_search');$matches=[];
        foreach($this->rank($query,$limit) as $name){
            $matches[]=['name'=>$name,'description'=>$this->available[$name]['description']];
        }
        AgentTraceService::endSpan($span,'complete',['result_count'=>count($matches)]);
        return ['matches'=>$matches,'activation_limit_reached'=>false];
    }


    public function has(string $name): bool {
        return in_array($name,array_column($this->declarations(),'name'),true);
    }

    private function activate(string $id): array {
        $skill=$this->loadedSkills[$id]??=AgentInstructionService::loadSkill($this->surface,$id,$this->actor,array_keys($this->available));
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
        if($name==='tool_search')return $this->search($args);
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
        return $this->executeBusiness($name,$args);
    }

    private function executeBusiness(string $name,array $args,string $origin='model_tool_lookup'): array {
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
                    'intent'=>'catalogue_summary','status'=>'matched','reason'=>$origin,'result_count'=>$result['total']]);
                if($name==='own_order_status'||$name==='own_ticket_status'){
                    $count=(int)($result['count']??0);
                    $this->retrieval[]=AgentTraceService::safeRetrieval(['intent'=>$name==='own_order_status'?'own_order':'own_ticket',
                        'status'=>$count?'matched':'no_match','reason'=>$origin,'result_count'=>$count,'candidate_count'=>$result['candidate_count']??null]);
                }
                if(in_array($name,['search_articles','read_published_article'],true)) {
                    $sources=AgentTraceService::articleSources($result);
                    $this->retrieval[]=AgentTraceService::safeRetrieval(['intent'=>'published_articles',
                        'status'=>$sources?'matched':'no_match','reason'=>$origin,
                        'candidate_count'=>(int)($result['found']??0),'result_count'=>count($sources),'sources'=>$sources]);
                }
                if(in_array($name,['public_catalog','public_best_sellers','public_product_details'],true)) {
                    $sources=[];
                    foreach((array)($result['products']??[]) as $p) {
                        if(!is_array($p)||!is_string($p['slug']??null)||PublicKnowledgeService::productPath($p['slug'])===null)continue;
                        $this->products[$p['slug']]=$p;
                        $sources[]=['id'=>'product:'.rawurlencode($p['slug']),'last_updated'=>null];
                    }
                    $selection=is_array($result['retrieval']??null)?$result['retrieval']:[];
                    $this->retrieval[]=AgentTraceService::safeRetrieval([
                        'intent'=>$name==='public_best_sellers'?'best_sellers':($selection['intent']??'catalogue_search'),
                        'status'=>$selection['status']??($sources?'matched':'no_match'),'reason'=>$origin,
                        'candidate_count'=>$selection['candidate_count']??null,'result_count'=>count($sources),'sources'=>$sources]);
                }
            }
        }
        if($guidance)$result['runtime_guidance']=$guidance;
        return $result;
    }

    public function activity(): array { return $this->surface==='admin'?$this->business->activity():$this->activity; }
    public function result(string $tool): ?array { return $this->results[$tool]??null; }
    public function retrieval(): array { return $this->retrieval; }
    public function products(): array { return array_values($this->products); }
}
