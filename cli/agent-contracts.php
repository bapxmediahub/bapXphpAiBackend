<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\{AgentCapabilityPolicy,AgentInstructionService,EnterpriseToolRegistry,SupportToolRegistry};

if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$arguments=array_slice($argv,1);
if(array_diff($arguments,['--check'])!==[]){fwrite(STDERR,"Usage: php cli/agent-contracts.php [--check]\n");exit(2);}
$surfaces=[
    'support'=>['title'=>'Support','registry'=>'SupportToolRegistry','tools'=>(new SupportToolRegistry(null))->declarations(),'policy'=>'support'],
    'admin'=>['title'=>'Enterprise Admin','registry'=>'EnterpriseToolRegistry','tools'=>(new EnterpriseToolRegistry())->declarations(),'policy'=>'enterprise'],
];
$lines=['---','type: doc','title: Generated Agent Tool and Instruction Contracts',
    'description: Source-derived PHP capability schemas and instruction versions; CI rejects drift.','category: docs','---','',
    '# Agent tool and instruction contracts','',
    'Generated from the actual registries, AgentCapabilityPolicy and AgentInstructionService.',
    'Regenerate with `php cli/agent-contracts.php` or `./bapXphp update`; validate with `php cli/agent-contracts.php --check`.',
    'No provider calls, credentials or business-data queries are made during generation. No conversation SQL table is introduced.','',
    'Discovery is not authorization. Every invocation still passes its PHP execution policy. These registries expose only named reads; ticket creation and owner-approved draft saves remain distinct controller/service actions.',
    'The historical `customer_ticket_lookup` activity label is not an invocable support capability.',
    'Both model surfaces use the bounded AiClient tool loop through RuntimeAgentToolRegistry. The model adapter filters declarations by actor and the relevant public module settings. The same eligible capability set selects private runtime skills for activation by identifier. The file manifest below lists the role documents. Model prompts start with the constitution and shared runtime contract, then activate relevant skills.',''];
foreach($surfaces as $surface=>$config){
    $names=array_column($config['tools'],'name');
    if($names!==AgentCapabilityPolicy::tools($config['policy']))throw new RuntimeException('Registry/policy capability mismatch: '.$surface);
    $lines[]='## '.$config['title'];$lines[]='';
    $lines[]='Source: `app/Services/'.$config['registry'].'.php`; instruction version '.AgentInstructionService::VERSION.'.';$lines[]='';
    // Offline metadata generation has an explicit fixture role; production auth is unchanged.
    $manifest=AgentInstructionService::manifestForSurface($surface,$surface==='admin'?['role'=>'admin']:null);
    $lines[]='### Runtime instruction sources';$lines[]='';
    $lines[]='Private runtime Markdown is not duplicated into developer documentation.';$lines[]='';
    foreach($manifest['files'] as $file)$lines[]='- `'.$file['path'].'` — SHA-256 `'.$file['sha256'].'`';
    $lines[]='';$lines[]='Bundle SHA-256: `'.$manifest['sha256'].'`.';$lines[]='';
    foreach($config['tools'] as $tool){
        $lines[]='### `'.$tool['name'].'`';$lines[]='';$lines[]=$tool['description'];$lines[]='';
        $access = match (true) {
            $surface === 'admin' => 'Authenticated admin, enforced server-side.',
            !AgentCapabilityPolicy::allows($config['policy'], $tool['name'], null) =>
                'Authenticated customer. PHP supplies the account identity.',
            $tool['name'] === 'own_cart' => 'Guest or authenticated customer, limited to the current browser-session cart.',
            default => 'Guest or authenticated customer, limited to approved public data.',
        };
        $module = AgentCapabilityPolicy::requiredModule($config['policy'], $tool['name']);
        if ($module !== null) $access .= ' Requires the public '.ucfirst($module).' module.';
        $lines[]='**Access:** '.$access;$lines[]='';$lines[]='```json';
        $lines[]=json_encode($tool['parameters'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$lines[]='```';$lines[]='';
    }
}
$content=rtrim(implode("\n",$lines))."\n";$target=app_path('docs/agent-tool-contracts.md');
if(in_array('--check',$arguments,true)){
    if(!is_file($target)||file_get_contents($target)!==$content){fwrite(STDERR,"Agent tool documentation is stale. Run php cli/agent-contracts.php.\n");exit(1);}
    echo "Agent tool and instruction documentation matches PHP source.\n";exit;
}
if(file_put_contents($target,$content,LOCK_EX)!==strlen($content))throw new RuntimeException('Unable to write tool documentation');
echo "Generated docs/agent-tool-contracts.md.\n";
