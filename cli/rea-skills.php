<?php
declare(strict_types=1);

/** Developer-only offline reference-skill index. No app bootstrap or upstream execution. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$base = $root.'/.agents/REA';
function readReaSkillJson(string $path): array {
    $data=file_get_contents($path);
    if ($data===false || strlen($data)>2000000) throw new RuntimeException('Invalid skill inventory file.');
    return json_decode($data,true,512,JSON_THROW_ON_ERROR);
}
function reaSkillHash(mixed $s,int $length=64): bool {
    return is_string($s) && preg_match('/^[a-f0-9]{'.$length.'}$/D',$s)===1;
}
function reaSkillFile(string $base,mixed $relative): string {
    if (!is_string($relative)||!preg_match('~^[A-Za-z0-9_.@/-]+$~D',$relative)
        ||str_starts_with($relative,'/')||in_array('..',explode('/',$relative),true)
        ||in_array('.',explode('/',$relative),true)||str_contains($relative,'//')) {
        throw new RuntimeException('Invalid skill inventory path.');
    }
    $file=$base.'/'.$relative;
    $real=realpath($file);
    if ($real===false || !str_starts_with($real,realpath($base).'/') || !is_file($file)) {
        throw new RuntimeException('Missing skill reference: '.$relative);
    }
    $dir=$base;
    foreach(explode('/',$relative) as $part) {
        $dir.='/'.$part;
        if(is_link($dir))throw new RuntimeException('Linked skill reference refused: '.$relative);
    }
    return $file;
}
function reaSkillTree(string $dir): array {
    $paths=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS)) as $file){
        if ($file->isLink()) throw new RuntimeException('Linked reference tree refused.');
        if ($file->isFile()) $paths[]=substr($file->getPathname(),strlen($dir)+1);
    }
    sort($paths,SORT_STRING);
    return $paths;
}
try {
    if(is_link($base)||is_link($root.'/.agents'))throw new RuntimeException('Linked REA root refused.');
    $index=readReaSkillJson(reaSkillFile($base,'skill-map.json'));
    $register=readReaSkillJson($root.'/docs/research/capability-register.json');
    if (($index['schema_version']??null)!==1 || !is_array($index['sources']??null)||!is_array($index['skills']??null)
        ||!array_is_list($index['sources'])||!array_is_list($index['skills'])) throw new RuntimeException('Invalid REA skill index.');
    $allowedSources=['wordpress'=>'https://github.com/WordPress/agent-skills','woocommerce'=>'https://github.com/woocommerce/agent-skills'];
    $sources=[];$originals=[];
    foreach($index['sources'] as $source){
        $id=$source['id']??'';
        if (!isset($allowedSources[$id])||isset($sources[$id])||$source['url']!==$allowedSources[$id]
            ||!reaSkillHash($source['revision']??null,40)||($source['license']??null)!=='GPL-2.0-or-later'
            ||($source['rea']['authority']??null)!=='historical-reference'||($source['rea']['inventory_state']??null)!=='partial'
            ||!reaSkillHash($source['rea']['root_sha256']??null)||!reaSkillHash($source['rea']['artifact_sha256']??null)
            ||!is_int($source['rea']['parse_failure_count']??null)||$source['rea']['parse_failure_count']<0
            ||!is_array($source['files']??null)||!array_is_list($source['files']))throw new RuntimeException('Invalid pinned skill source.');
        $paths=[];
        foreach($source['files'] as $file){
            $relative=$file['path']??null;
            if (!is_string($relative)||isset($paths[$relative])||!reaSkillHash($file['sha256']??null)
                ||!is_int($file['bytes']??null)||$file['bytes']<0)throw new RuntimeException('Invalid pinned skill file.');
            if(!in_array($relative,['LICENSE','README.md','CONTRIBUTING.md'],true)
                && !preg_match('~^(?:skills|docs|shared/references|eval/scenarios)/~',$relative))throw new RuntimeException('Unexpected upstream file scope: '.$relative);
            $path=reaSkillFile($base,'upstream/'.$id.'/'.$relative);
            if(filesize($path)!==$file['bytes']||hash_file('sha256',$path)!==$file['sha256'])throw new RuntimeException('Upstream reference drift: '.$id.'/'.$relative);
            $paths[$relative]=true;
            if(preg_match('~^skills/[a-z0-9-]+/SKILL\.md$~D',$relative))$originals['upstream/'.$id.'/'.$relative]=$id;
        }
        if(!isset($paths['LICENSE'],$paths['README.md']))throw new RuntimeException('Missing upstream attribution.');
        $listed=array_keys($paths);sort($listed,SORT_STRING);
        if(reaSkillTree($base.'/upstream/'.$id)!==$listed)throw new RuntimeException('Untracked upstream reference file or omitted inventory entry.');
        $sources[$id]=$source;
    }
    if(count($sources)!==count($allowedSources))throw new RuntimeException('Missing upstream skill collection.');
    $capabilities=array_column($register['rows']??[],null,'id');
    $seen=[];$covered=[];$routed=[];$reviewHashes=[];$stale=[];
    foreach($index['skills'] as $skill){
        $id=$skill['id']??null;
        $source=$skill['source']??null;
        $upstream=$skill['upstream']??null;
        $adapted=$skill['adapted']??null;
        if(!is_string($id)||!preg_match('/^rea-[a-z0-9-]+$/D',$id)||isset($seen[$id])
            ||!is_string($source)||!isset($sources[$source])||!is_string($upstream)
            ||($originals[$upstream]??null)!==$source||isset($covered[$upstream])
            ||$id!=='rea-'.basename(dirname($upstream))||$adapted!=='skills/'.$id.'/SKILL.md')throw new RuntimeException('Duplicate, missing or mismatched native skill adaptation.');
        $body=file_get_contents(reaSkillFile($base,$adapted));
        if(!is_string($body)||!str_starts_with($body,"---\nname: ".$id."\n")
            ||!str_contains($body,'## REA evidence and current native boundary')
            ||!str_contains($body,'## Native implementation workflow')
            ||!str_contains($body,'## Acceptance and failure modes'))throw new RuntimeException('Incomplete native skill '.$id);
        $ids=$skill['capability_ids']??null;
        if(!is_array($ids)||$ids===[]||count(array_unique($ids))!==count($ids)
            ||!str_contains($body,'capability_ids: ['.implode(', ',$ids).']'))throw new RuntimeException('Invalid capability routing for '.$id);
        $findings=[];
        foreach($ids as $cap){
            if(!is_string($cap)||!isset($capabilities[$cap]))throw new RuntimeException('Unknown capability '.$cap.' in '.$id);
            $routed[$cap]=true;
            $finding=$capabilities[$cap];
            $findings[]=array_intersect_key($finding,array_flip(['id','title','status','finding','references']));
        }
        foreach(['local_sources','acceptance_tests'] as $field){
            if(!is_array($skill[$field]??null)||$skill[$field]===[])throw new RuntimeException('Missing native evidence for '.$id);
            foreach($skill[$field] as $p)reaSkillFile($root,$p);
        }
        foreach($skill['upstream_references']??[] as $p)reaSkillFile($base,dirname($upstream).'/'.$p);
        // A skill review binds its actual guide and mapped behavioral findings,
        // not changing implementation-source hashes or raw provider captures.
        $hash=hash('sha256',json_encode(['upstream_revision'=>$sources[$source]['revision'],
            'guide_sha256'=>hash('sha256',$body),'findings'=>$findings,
            'local_sources'=>$skill['local_sources'],'acceptance_tests'=>$skill['acceptance_tests'],
            'upstream_references'=>$skill['upstream_references']??[]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $reviewHashes[$id]=$hash;
        if(($skill['review']['sha256']??null)!==$hash
            ||!is_string($skill['review']['reason']??null)||trim($skill['review']['reason'])===''
            ||!is_string($skill['review']['date']??null))$stale[$id]=true;
        $seen[$id]=$skill;$covered[$upstream]=true;
    }
    if(count($covered)!==count($originals))throw new RuntimeException('Every upstream skill must have one native adaptation.');
    if(array_diff_key($capabilities,$routed))throw new RuntimeException('Unrouted REA capability: '.implode(', ',array_keys(array_diff_key($capabilities,$routed))));
    $adaptedPaths=array_column($seen,'adapted');sort($adaptedPaths,SORT_STRING);
    $actual=array_map(static fn(string $p):string=>'skills/'.$p,reaSkillTree($base.'/skills'));sort($actual,SORT_STRING);
    if($actual!==$adaptedPaths)throw new RuntimeException('Unmapped native skill file.');
    $args=array_slice($argv,1);
    if($args===[]||$args===['--check']){
        if($stale)throw new RuntimeException('Mapped findings or native skill changed; review and record: '.implode(', ',array_keys($stale)));
        echo 'REA native skill inventory verified: '.count($seen).' adaptations, '.array_sum(array_map(static fn($s)=>count($s['files']),$sources)).' pinned upstream files, '.count($routed)." mapped obligations.\n";
    }elseif($args===['--list']){
        foreach($seen as $id=>$skill)echo $id.'  '.implode(', ',$skill['capability_ids']).(isset($stale[$id])?'  [review required]':'')."\n";
    }elseif($args===['--affected']){
        foreach($stale as $id=>$_)echo $id."\n";
    }elseif(count($args)===2&&$args[0]==='--show'&&isset($seen[$args[1]])){
        if(isset($stale[$args[1]]))fwrite(STDERR,"Mapped findings changed; review this guide before implementation.\n");
        echo file_get_contents(reaSkillFile($base,$seen[$args[1]]['adapted']));
    }elseif(count($args)===2&&$args[0]==='--for'&&isset($capabilities[$args[1]])){
        foreach($seen as $id=>$skill)if(in_array($args[1],$skill['capability_ids'],true))echo $id.'  '.$skill['adapted'].(isset($stale[$id])?'  [review required]':'')."\n";
    }elseif(count($args)===4&&$args[0]==='--record'&&isset($seen[$args[1]])&&$args[2]==='--reason'){
        if(strlen(trim($args[3]))<20)throw new RuntimeException('Record a specific skill review rationale.');
        foreach($index['skills'] as &$skill)if($skill['id']===$args[1]){
            $skill['review']=['date'=>gmdate('Y-m-d'),'reason'=>trim($args[3]),'sha256'=>$reviewHashes[$skill['id']]];
            break;
        }
        unset($skill);
        $encoded=json_encode($index,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($base.'/skill-map.json',$encoded,LOCK_EX)!==strlen($encoded))throw new RuntimeException('Could not write skill review.');
        echo 'Reviewed '.$args[1]."; no capability status changed.\n";
    }else throw new RuntimeException('Usage: php cli/rea-skills.php [--check|--list|--affected|--show SKILL|--for CAPABILITY_ID|--record SKILL --reason "review"]');
}catch(Throwable $error){fwrite(STDERR,'REA skills: '.$error->getMessage()."\n");exit(1);}
