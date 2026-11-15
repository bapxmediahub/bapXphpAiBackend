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
    'The historical `customer_ticket_lookup` activity label is not an invocable support capability. The Support bot uses local routing and scoped context; declarations do not imply that every model receives an unrestricted tool loop.',''];
foreach($surfaces as $surface=>$config){
    $names=array_column($config['tools'],'name');
    if($names!==AgentCapabilityPolicy::tools($config['policy']))throw new RuntimeException('Registry/policy capability mismatch: '.$surface);
    $lines[]='## '.$config['title'];$lines[]='';
    $lines[]='Source: `app/Services/'.$config['registry'].'.php`; instruction version '.AgentInstructionService::VERSION.'.';$lines[]='';
    $lines[]='### Instructions';$lines[]='';$lines[]=AgentInstructionService::forSurface($surface);$lines[]='';
    foreach($config['tools'] as $tool){
        $lines[]='### `'.$tool['name'].'`';$lines[]='';$lines[]=$tool['description'];$lines[]='';
        $access=$surface==='admin'?'Authenticated admin, enforced server-side.':($tool['name']==='own_ticket_status'?'Authenticated customer; identity comes from PHP, never tool arguments.':'Guest or authenticated customer; published/public data only.');
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
