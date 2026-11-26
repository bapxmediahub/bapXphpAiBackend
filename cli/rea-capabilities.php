<?php
declare(strict_types=1);

/** Offline developer inventory: no provider, application bootstrap, DB or network. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$registerPath = $root.'/docs/research/capability-register.json';
$reportPath = $root.'/docs/research/capability-progress.md';
function readJson(string $path): array {
    $raw = file_get_contents($path);
    if ($raw === false || strlen($raw)>2000000) throw new RuntimeException('Missing or oversized ledger JSON.');
    return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
}
function digestFor(array $row, string $root): string {
    $paths=array_values(array_unique(array_merge($row['local_sources'],$row['acceptance_evidence'])));
    sort($paths,SORT_STRING);
    $content='';
    foreach ($paths as $path) {
        if (!is_string($path) || !preg_match('~^(?:app|assets|cli|config|content|docs|integrations|storage/schema|tests|views|\.agents|\.bapX|\.github)/[A-Za-z0-9_.@/-]+$~D',$path)
            || in_array('..',explode('/',$path),true) || str_contains($path,'/.env')) throw new RuntimeException('Invalid source path for '.$row['id']);
        $file=realpath($root.'/'.$path);
        if ($file===false || !str_starts_with($file,$root.'/') || !is_file($file) || is_link($root.'/'.$path)) {
            throw new RuntimeException('Missing mapped source '.$row['id'].': '.$path);
        }
        $content.=$path."\0".hash_file('sha256',$file)."\n";
    }
    return hash('sha256',$content);
}
function validDate(mixed $value): bool {
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)===1
        && ($d=DateTimeImmutable::createFromFormat('!Y-m-d',$value))!==false && $d->format('Y-m-d')===$value;
}
function hexHash(mixed $value,int $size=64): bool {
    return is_string($value) && preg_match('/^[a-f0-9]{'.$size.'}$/D',$value)===1;
}
function validate(array $register,array $index,string $root,bool $checkHashes=true): array {
    if (($register['schema_version']??null)!==1 || ($index['schema_version']??null)!==1
        || !hexHash($register['baseline_commit']??null,40) || !is_array($register['groups']??null)
        || !is_array($register['rows']??null) || !array_is_list($register['rows'])) throw new RuntimeException('Invalid capability register.');
    $sources=[];
    foreach($index['sources']??[] as $source) {
        $id=$source['id']??'';
        if (!is_string($id)||$id===''||array_key_exists($id,$sources)||empty($source['revision'])) throw new RuntimeException('Invalid reference source.');
        $paths=[];
        foreach($source['files']??[] as $file) {
            if (!hexHash($file['sha256']??null)||!is_string($file['path']??null)||isset($paths[$file['path']])) throw new RuntimeException('Invalid reference file.');
            $paths[$file['path']]=true;
        }
        $sources[$id]=isset($source['files'])?$paths:null;
    }
    foreach($index['historical_artifacts']??[] as $artifact) {
        $path=$artifact['path']??'';
        if (!is_string($path)||!preg_match('/^[a-z0-9-]+\.json$/D',$path)
            || !is_file($root.'/docs/research/evidence/'.$path)
            || hash_file('sha256',$root.'/docs/research/evidence/'.$path)!==($artifact['sha256']??null)) throw new RuntimeException('Historical REA artifact drift.');
    }
    $seen=[];$errors=[];
    foreach($register['rows'] as $row) {
        $id=$row['id']??'';
        if (!is_string($id)||!preg_match('/^[A-Z]+[0-9]+$/D',$id)||isset($seen[$id])) throw new RuntimeException('Missing or duplicate capability ID.');
        $seen[$id]=true;
        if (!isset($register['groups'][$row['group']??'']) || !in_array($row['status']??null,['missing','partial','implemented','verified','excluded'],true)
            || !is_string($row['title']??null)||trim($row['title'])===''||!is_string($row['finding']??null)||trim($row['finding'])===''
            || !is_array($row['issues']??null)||$row['issues']===[]||!is_array($row['references']??null)||$row['references']===[]
            || !is_array($row['local_sources']??null)||!is_array($row['acceptance_evidence']??null)
            || !is_array($row['production_evidence']??null)||!validDate($row['review']['date']??null)
            || !hexHash($row['review']['source_sha256']??null)||!is_string($row['review']['reason']??null)||trim($row['review']['reason'])==='') throw new RuntimeException('Incomplete capability '.$id);
        foreach($row['issues'] as $issue) if (!is_int($issue)||$issue<1) throw new RuntimeException('Invalid issue link '.$id);
        foreach($row['references'] as $ref) {
            $source=$ref['source']??'';$path=$ref['path']??'';
            if(!array_key_exists($source,$sources)||!is_string($path)||$path===''||
                ($sources[$source]!==null&&!isset($sources[$source][$path]))||
                ($sources[$source]===null&&!str_starts_with($path,'https://'))) throw new RuntimeException('Unpinned reference '.$id.': '.$path);
        }
        if ($row['status']!=='excluded' && ($row['local_sources']===[]||$row['acceptance_evidence']===[])) throw new RuntimeException('Missing native evidence '.$id);
        $digest=digestFor($row,$root);
        if ($checkHashes && $digest!==$row['review']['source_sha256']) $errors[]=$id.': mapped source/evidence changed; inspect and record an explicit capability review.';
        $matching=false;
        foreach($row['production_evidence'] as $proof) {
            if (!hexHash($proof['deployed_sha']??null,40)||!validDate($proof['date']??null)||!hexHash($proof['source_sha256']??null)
                || !is_string($proof['url']??null)||!str_starts_with($proof['url'],'https://github.com/bapxmediahub/bapXphpAiBackend/')
                || !is_string($proof['scope']??null)||trim($proof['scope'])==='') throw new RuntimeException('Invalid live evidence '.$id);
            if ($proof['source_sha256']===$row['review']['source_sha256']) $matching=true;
        }
        if ($row['status']==='verified' && !$matching) throw new RuntimeException('Verified status without matching deployed acceptance '.$id);
    }
    return $errors;
}
function cell(string $value): string {return str_replace(["|","\r","\n"],['\\|','',' '],$value);}
function report(array $r): string {
    $s="---\ntype: doc\ntitle: REA native capability completion register\ndescription: Generated scope-based implementation and production acceptance coverage; not a task backlog.\ncategory: docs\n---\n\n# REA native capability coverage\n\nGenerated by `php cli/rea-capabilities.php --render`. Baseline source audit: `".$r['baseline_commit']."`. The review hashes track subsequent changes.\n\nThis measures selected native acceptance obligations, not a percentage of all upstream code/features. Implemented includes verified; partial receives no completion credit. Exclusions are outside the denominator. GitHub issues remain the only backlog and live acceptance record.\n\n";
    $s.="| Reference area | Scope | Implemented incl. verified | Partial | Missing | Production verified | Implementation | Verified |\n|---|---:|---:|---:|---:|---:|---:|---:|\n";
    $all=['implemented'=>0,'verified'=>0,'partial'=>0,'missing'=>0,'excluded'=>0];
    $count=static function(array $a):array {
        $n=array_sum($a)-$a['excluded'];$i=$a['implemented']+$a['verified'];
        return [$n,$i,$a['partial'],$a['missing'],$a['verified'],$n?number_format(100*$i/$n,1).'%':'N/A',$n?number_format(100*$a['verified']/$n,1).'%':'N/A'];
    };
    foreach($r['groups'] as $id=>$name){
        $a=array_fill_keys(array_keys($all),0);
        foreach($r['rows'] as $row)if($row['group']===$id)$a[$row['status']]++;
        foreach($a as $k=>$v)$all[$k]+=$v;
        $s.='| '.cell($name).' | '.implode(' | ',$count($a))." |\n";
    }
    $s.='| **Total** | '.implode(' | ',$count($all))." |\n\n".$all['excluded']." explicit exclusions. No percentage is a claim of live inference quality or external protocol conformance.\n\n";
    foreach($r['groups'] as $id=>$name){
        $s.='## '.cell($name)."\n\n| ID | Capability | Status | Evidence / remaining boundary | Issues |\n|---|---|---|---|---|\n";
        foreach($r['rows'] as $row)if($row['group']===$id){
            $issues=implode(', ',array_map(static fn(int $n):string=>'[#'.$n.'](https://github.com/bapxmediahub/bapXphpAiBackend/issues/'.$n.')',$row['issues']));
            $s.='| '.$row['id'].' | '.cell($row['title']).' | '.$row['status'].' | '.cell($row['finding']).' | '.$issues." |\n";
        }
        $s.="\n";
    }
    $s.="Exact upstream file hashes, native paths, review evidence and live acceptance receipts are in [capability-register.json](capability-register.json) and [reference-source-index.json](evidence/reference-source-index.json). Review workflow: [REA engineering workflow](rea-engineering-workflow.md).\n";
    return $s;
}
try {
    $r=readJson($registerPath);$index=readJson($root.'/docs/research/evidence/reference-source-index.json');
    $args=array_slice($argv,1);
    if(count($args)===4&&$args[0]==='--record'&&$args[2]==='--reason'){
        validate($r,$index,$root,false);
        if(mb_strlen(trim($args[3]))<20) throw new RuntimeException('Record a specific review rationale (at least 20 characters).');
        $found=false;
        foreach($r['rows'] as &$row)if($row['id']===$args[1]){
            $found=true;$hash=digestFor($row,$root);
            if($row['status']==='verified'&&$hash!==$row['review']['source_sha256']){
                $row['status']='implemented';
                echo "Changed verified row downgraded to implemented; retain prior evidence, require new production acceptance.\n";
            }
            $row['review']=['date'=>gmdate('Y-m-d'),'reason'=>trim($args[3]),'source_sha256'=>$hash];
            break;
        }
        unset($row);
        if(!$found)throw new RuntimeException('Unknown capability ID.');
        $json=json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        if(file_put_contents($registerPath,$json,LOCK_EX)===false)throw new RuntimeException('Could not write ledger.');
        echo 'Reviewed '.$args[1]."; update evidence/status manually, then --render.\n";exit;
    }
    if(!in_array($args,[[],['--check'],['--render']],true)) throw new RuntimeException('Usage: php cli/rea-capabilities.php [--check|--render|--record ID --reason "reviewed behavior"]');
    $errors=validate($r,$index,$root);
    if($errors){foreach($errors as $e)fwrite(STDERR,$e."\n");exit(1);}
    $output=report($r);
    if($args===['--render']){
        if(file_put_contents($reportPath,$output,LOCK_EX)===false)throw new RuntimeException('Could not generate report.');
        echo "Generated REA capability report.\n";exit;
    }
    if(!is_file($reportPath)||file_get_contents($reportPath)!==$output)throw new RuntimeException('REA report stale; run --render after reviewed ledger updates.');
    echo 'REA capability register, source hashes and derived percentages passed ('.count($r['rows'])." obligations including exclusions).\n";
} catch(Throwable $e) {fwrite(STDERR,'REA ledger: '.$e->getMessage()."\n");exit(1);}
