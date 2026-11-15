<?php
declare(strict_types=1);
/** Developer-only cross-review gate. Fingerprints detect drift; they do not prove prose is true. */
final class DocumentationReview
{
    public function __construct(private string $root, private array $rules) { $this->root=rtrim($root,'/'); }
    private function sourceFiles(): array {
        $files=[];
        foreach(['app','views','cli','config','.github','.agents/skills','.bapX','storage/schema','content/blog','content/help','assets'] as $dir){
            $base=$this->root.'/'.$dir;if(!is_dir($base))continue;
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS)) as $file){
                $relative=substr($file->getPathname(),strlen($this->root)+1);
                if(str_starts_with($relative,'assets/images/')||str_contains($relative,'/node_modules/')||str_contains($relative,'/vendor/'))continue;
                if(!in_array(strtolower($file->getExtension()),['php','js','cjs','mjs','css','md','json','yml','yaml','sh'],true)&&$relative!=='cli/bapXphp')continue;
                if($file->isLink())throw new RuntimeException('Linked source not allowed: '.$relative);
                if($file->isFile())$files[]=$relative;
            }
        }
        foreach(['AGENTS.md','Design.md','index.php','.htaccess'] as $file)if(is_file($this->root.'/'.$file))$files[]=$file;
        sort($files,SORT_STRING);return array_values(array_unique($files));
    }
    private function digest(array $files): string {
        sort($files,SORT_STRING);$hashes=[];
        foreach($files as $file){
            if(str_contains($file,'..')||str_starts_with($file,'/')||is_link($this->root.'/'.$file)||!is_file($this->root.'/'.$file))throw new RuntimeException('Missing or unsafe source/reference: '.$file);
            $hashes[$file]=hash_file('sha256',$this->root.'/'.$file);
        }
        return hash('sha256',json_encode($hashes,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    }
    public function snapshots(): array {
        $assigned=array_fill_keys(array_keys($this->rules),[]);
        foreach($this->sourceFiles() as $file){
            $match=false;
            foreach($this->rules as $area=>$rule){
                foreach($rule['sources'] as $pattern){
                    if(fnmatch($pattern,$file)){$assigned[$area][]=$file;$match=true;break 2;}
                }
            }
            if(!$match)throw new RuntimeException('Source has no documentation owner: '.$file);
        }
        $result=[];
        foreach($this->rules as $area=>$rule){
            if(!preg_match('/^[a-z][a-z0-9-]{1,40}$/D',$area))throw new RuntimeException('Invalid documentation area');
            $refs=$rule['references']??[];
            if($refs===[])throw new RuntimeException('No documentation references for '.$area);
            // A guide depends on shared feature sources even if another area owns them.
            // Explicit dependencies preserve first-match ownership while invalidating every affected review.
            $sources=array_values(array_unique(array_merge($assigned[$area],$rule['dependencies']??[])));
            sort($sources,SORT_STRING);
            $result[$area]=['source_sha256'=>$this->digest($sources),'references_sha256'=>$this->digest($refs),
                'source_count'=>count($sources),'sources'=>$sources,'references'=>$refs];
        }
        return $result;
    }
    private function receipts(): array {
        $file=$this->root.'/docs/source-contracts.json';if(!is_file($file))return [];
        if(is_link($file))throw new RuntimeException('Linked documentation review receipt refused');
        $data=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        if(($data['schema_version']??null)!==1||!is_array($data['reviews']??null))throw new RuntimeException('Invalid review receipt schema');
        return $data['reviews'];
    }
    public function check(): array {
        $errors=[];$docs=$this->root.'/docs';
        if(is_dir($docs))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docs,FilesystemIterator::SKIP_DOTS)) as $file){
            $path=substr($file->getPathname(),strlen($this->root)+1);
            if(str_starts_with($path,'docs/issues/')||str_starts_with($path,'docs/superpowers/plans/')
                ||preg_match('~(?:^|/)issue[-_]\d+[^/]*\.(?:md|json|ya?ml)$~i',$path))$errors[]='Use GitHub issues/comments, not a documentation tracker: '.$path;
        }
        try{
            $snapshots=$this->snapshots();$receipts=$this->receipts();
            foreach($snapshots as $area=>$snapshot){
                $receipt=$receipts[$area]??[];
                if(($receipt['source_sha256']??'')!==$snapshot['source_sha256']||($receipt['references_sha256']??'')!==$snapshot['references_sha256']
                    ||mb_strlen(trim((string)($receipt['review_note']??'')))<24)$errors[]=$area.': source/doc review missing or stale. Inspect sources and references; record an explicit rationale.';
            }
            foreach(array_diff(array_keys($receipts),array_keys($snapshots)) as $area)$errors[]='Removed documentation area has stale receipt: '.$area;
        }catch(Throwable $e){$errors[]=$e->getMessage();}
        return $errors;
    }
    public function record(string $area,string $reason): void {
        if(!isset($this->rules[$area])||mb_strlen(trim($reason))<24||mb_strlen($reason)>1200)throw new InvalidArgumentException('Known area and meaningful review rationale required.');
        $snapshot=$this->snapshots()[$area];$receipts=$this->receipts();
        $receipts[$area]=['source_sha256'=>$snapshot['source_sha256'],'references_sha256'=>$snapshot['references_sha256'],'review_note'=>trim($reason)];ksort($receipts);
        $output=['schema_version'=>1,'meaning'=>'Source/document review attestations, not proof of semantic correctness or issue completion.','reviews'=>$receipts];
        $file=$this->root.'/docs/source-contracts.json';$tmp=tempnam(dirname($file),'.doc-review-');
        if($tmp===false)throw new RuntimeException('Cannot create review receipt');
        try{if(file_put_contents($tmp,json_encode($output,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n")===false||!rename($tmp,$file))throw new RuntimeException('Cannot save review receipt');}
        finally{if(is_file($tmp))unlink($tmp);}
    }
}
