<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Native chat presentation of an already-authorized answer, never a public tool.
 * No retrieval, tool execution, model-generated components or conversation writes.
 */
final class AgentAnswerPresentationService
{
    public static function safeActionPath(string $path,string $scope): bool
    {
        if(PublicA2uiService::safePublicPath($path))return true;
        if(preg_match('~^/(?:login|cart|checkout|account/dashboard(?:/orders|/install)?)$~D',$path))return true;
        if($scope!=='admin')return false;
        return (bool)preg_match('~^/admin/(?:support-tickets|docs/[a-z0-9-]{1,80}|tax-report\?report=stock|tax-report\?report=(?:sales|tax|products|customers|coupons)&from=\d{4}-\d{2}-\d{2}&to=\d{4}-\d{2}-\d{2})$~D',$path);
    }

    /** History uses only recorded evidence; legacy traces may have it in tool spans. */
    public static function toolsForTurn(array $turn): array
    {
        $tools=array_values(array_filter((array)($turn['tools']??[]),'is_array'));
        if(array_filter($tools,static fn(array $tool):bool=>!empty($tool['evidence']['facts'])||!empty($tool['evidence']['report'])))return array_slice($tools,0,32);
        $spans=array_slice(array_values(array_filter((array)($turn['trace']['spans']??[]),
            static fn($span):bool=>is_array($span)&&($span['type']??'')==='tool')),0,32);
        return $spans!==[]?$spans:array_slice($tools,0,32);
    }

    /** Presentation is optional: it must never turn a successful answer into an error. */
    public function responseData(string $answer,string $scope='support',array $actions=[],?array $references=null,array $tools=[]): array
    {
        try { return ['a2ui'=>$this->present($answer,$scope,$actions,$references,$tools)]; }
        catch(\Throwable $error) {
            error_log('Chat presentation unavailable ('.get_class($error).')');
            return [];
        }
    }

    /**
     * Labels and values come only from completed, allowlisted aggregate evidence.
     * The original tool result/prose never enters this projection. Missing values
     * stay missing; an error or unknown scope never becomes a zero/success card.
     */
    private static function factGroups(array $tools): array
    {
        $definitions=[
            'sales_summary'=>['title'=>'Sales summary','source'=>'orders','sourceLabel'=>'Orders',
                'fields'=>['orders'=>['Paid orders','count'],'revenue'=>['Net revenue','money']]],
            'gst_register_summary'=>['title'=>'GST register','source'=>'orders','sourceLabel'=>'Issued invoices',
                'fields'=>['issued_invoices'=>['Issued invoices','count'],'review_invoices'=>['Invoices needing review','count'],
                    'taxable'=>['Taxable value','money'],'tax'=>['GST','money'],'cgst'=>['CGST','money'],
                    'sgst'=>['SGST','money'],'igst'=>['IGST','money']]],
            'support_enquiries'=>['title'=>'Support enquiries','source'=>'support_tickets','sourceLabel'=>'Support tickets',
                'fields'=>['total'=>['Matching tickets','count']]],
            'store_count'=>['title'=>'Business records','source'=>'business_counts','sourceLabel'=>'Business counts',
                'fields'=>['count'=>['Records','count']]],
        ];
        $groups=[];$seen=[];
        // Prefer the latest completed observation when a tool ran again.
        foreach(array_reverse(array_slice($tools,0,32)) as $entry) {
            if(!is_array($entry)||($entry['status']??'')!=='complete'||!isset($definitions[$entry['name']??'']))continue;
            $tool=AgentTraceService::safeTool($entry);
            $definition=$definitions[$tool['name']];$evidence=$tool['evidence'];
            if($evidence['source']!==$definition['source'])continue;
            $filters=$evidence['filters'];$title=$definition['title'];
            if($tool['name']==='store_count') {
                $title=match($filters['entity']??null){
                    'users'=>'User accounts','products'=>'Product records','appointments'=>'Historical appointment records',default=>null};
                if($title===null)continue;
            }
            $context=$definition['sourceLabel'];
            $hasDates=isset($filters['from'],$filters['to'])&&self::validDate($filters['from'])&&self::validDate($filters['to']);
            $context.=$hasDates?' · '.$filters['from'].' to '.$filters['to']:' · Date range not recorded';
            foreach(['topic','status'] as $key)if(is_string($filters[$key]??null))$context.=' · '.ucfirst($key).': '.str_replace('_',' ',$filters[$key]);
            $context.=$evidence['observed_at']!==null?' · Observed '.str_replace(['T','Z'],[' ',' UTC'],$evidence['observed_at']):' · Observation time unavailable';
            $metrics=[];
            foreach($definition['fields'] as $key=>[$label,$type]) {
                $value=$evidence['facts'][$key]??null;
                if((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||abs((float)$value)>1e15)continue;
                if($type==='count'&&($value<0||floor((float)$value)!=(float)$value))continue;
                $metrics[]=['label'=>$label,'value'=>$type==='money'?'₹'.number_format((float)$value,2):number_format((float)$value,0)];
            }
            if(!$metrics)continue;
            $signature=$tool['name'].':'.json_encode($filters,JSON_THROW_ON_ERROR);
            if(isset($seen[$signature]))continue;
            $seen[$signature]=true;
            $primary=$tool['name']==='sales_summary'?array_search('Net revenue',array_column($metrics,'label'),true):0;
            $group=['title'=>$title,'context'=>mb_substr($context,0,320),'metrics'=>$metrics,'primary'=>$primary===false?0:$primary];
            if(is_string($evidence['source_path'])&&self::safeActionPath($evidence['source_path'],'admin')) {
                $group['source']=['label'=>$definition['title'].' source','path'=>$evidence['source_path']];
            }
            $groups[]=$group;
            if(count($groups)===2)break;
        }
        return array_reverse($groups);
    }

    /** One bounded, typed report from recorded evidence; never the raw result or answer prose. */
    private static function commerceReport(array $tools): ?array
    {
        foreach(array_reverse(array_slice($tools,0,32)) as $entry) {
            if(!is_array($entry)||($entry['name']??'')!=='commerce_report'||($entry['status']??'')!=='complete')continue;
            $tool=AgentTraceService::safeTool($entry);$evidence=$tool['evidence'];$report=$evidence['report']??null;
            if(($evidence['source']??'')!=='commerce_reports'||!is_array($report))continue;
            $kind=$report['type'];
            $titles=['products'=>'Product performance','stock'=>'Stock status','customers'=>'Customer frequency','coupons'=>'Coupon performance'];
            $summaryFields=[
                'products'=>['products'=>['Products','count'],'units'=>['Units purchased','count'],
                    'gross_line_value'=>['Gross line value','money'],'allocated_net'=>['Allocated net','money']],
                'stock'=>['products'=>['Product records','count'],'in_stock'=>['Available status','count'],
                    'out_of_stock'=>['Out of stock','count'],'inactive'=>['Inactive status','count'],
                    'draft'=>['Draft status','count'],'unknown_status'=>['Unknown status','count']],
                'customers'=>['customers'=>['Identified customers','count'],'repeat_customers'=>['Repeat customers','count'],
                    'paid_orders'=>['Paid orders','count'],'unidentified_orders'=>['Orders without customer identity','count'],
                    'gross_paid'=>['Gross paid','money'],'recorded_refunds'=>['Recorded refunds','money'],'net_paid'=>['Net paid','money']],
                'coupons'=>['coupons'=>['Coupons used','count'],'uses'=>['Uses','count'],
                    'discount'=>['Discount value','money'],'net'=>['Associated net','money']],
            ];
            $rowFields=[
                'products'=>['units'=>['Units purchased','count'],'order_lines'=>['Order lines','count'],
                    'gross'=>['Gross line value','money'],'net'=>['Allocated net','money']],
                'stock'=>['price'=>['Listed price','money']],
                'customers'=>['customers'=>['Customers','count'],'orders'=>['Paid orders','count'],
                    'gross'=>['Gross paid','money'],'refunded'=>['Recorded refunds','money'],'net'=>['Net paid','money']],
                'coupons'=>['uses'=>['Uses','count'],'discount'=>['Discount value','money'],'net'=>['Associated net','money']],
            ];
            if(!isset($titles[$kind]))continue;
            $format=static fn(int|float $value,string $type):string=>($type==='money'?'₹':'').number_format((float)$value,$type==='money'?2:0);
            $metrics=[];
            foreach($summaryFields[$kind] as $key=>[$label,$type])$metrics[]=['label'=>$label,'value'=>$format($report['summary'][$key],$type)];
            $context='Commerce reports · '.($kind==='stock'?'Current stock-status snapshot':$report['from'].' to '.$report['to']);
            $context.=$evidence['observed_at']!==null?' · Observed '.str_replace(['T','Z'],[' ',' UTC'],$evidence['observed_at']):' · Observation time unavailable';
            $rows=[];
            foreach(array_slice($report['rows'],0,6) as $row) {
                $title=match($kind) {
                    'customers'=>match($row['band']){'one_order'=>'One paid order','two_to_four'=>'Two to four paid orders','five_or_more'=>'Five or more paid orders'},
                    'coupons'=>$row['coupon'],default=>$row['product'],
                };
                $values=[];
                if($kind==='stock')$values[]=['label'=>'Stock status','value'=>match($row['status']) {
                    'in_stock','active'=>'Available','out_of_stock'=>'Out of stock','inactive'=>'Inactive','draft'=>'Draft',default=>'Unknown status',
                }];
                foreach($rowFields[$kind] as $key=>[$label,$type])$values[]=['label'=>$label,'value'=>$format($row[$key],$type)];
                if($kind==='stock')$values[]=['label'=>'Current offer','value'=>$row['offer_price']===null?'No current offer':$format($row['offer_price'],'money')];
                $rows[]=['title'=>$title,'values'=>$values];
            }
            $shown=count($rows);$total=$report['total_rows'];
            $coverage=$total===0?($kind==='customers'?'No identified customer groups for this period.':'No matching records.')
                :'Showing '.$shown.' of '.number_format($total).' rows.'.($shown<$total?' Open the full report for remaining rows.':'');
            return [
                'fact'=>['title'=>$titles[$kind],'context'=>mb_substr($context,0,320),'metrics'=>$metrics,
                    'primary'=>match($kind){'products'=>3,'customers'=>1,'coupons'=>2,default=>0}],
                'report'=>['kind'=>$kind,'title'=>$titles[$kind].' details','context'=>mb_substr($context,0,320),
                    'basis'=>mb_substr($report['basis'],0,500),'coverage'=>$coverage,'rows'=>$rows],
                'source'=>['label'=>'Open '.$titles[$kind].' report','path'=>$report['report_url']],
            ];
        }
        return null;
    }

    private static function validDate(mixed $value): bool
    {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))return false;
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        return $date!==false&&$date->format('Y-m-d')===$value;
    }

    /** Preserve the complete accepted answer, with scoped facts and safe navigation. */
    public function present(string $answer,string $scope='support',array $actions=[],?array $references=null,array $tools=[]): array
    {
        if(!in_array($scope,['support','admin'],true))throw new \InvalidArgumentException('Unknown chat surface.');
        if(!mb_check_encoding($answer,'UTF-8')||mb_strlen($answer)>12000)throw new \InvalidArgumentException('Answer exceeds presentation budget.');
        $chunks=preg_split('/\n\s*\n/u',trim($answer),8)?:[''];
        $sections=array_map(static fn(string $chunk):array=>['body'=>$chunk],$chunks);
        $facts=$scope==='admin'?self::factGroups($tools):[];
        $commerce=$scope==='admin'?self::commerceReport($tools):null;
        $reports=$commerce!==null?[$commerce['report']]:[];
        if($commerce!==null){array_unshift($facts,$commerce['fact']);$facts=array_slice($facts,0,2);$actions[]=$commerce['source'];}
        foreach($facts as $fact)if(isset($fact['source']))$actions[]=$fact['source'];
        $safeActions=[];$seen=[];
        foreach(array_slice($actions,0,16) as $action) {
            if(!is_array($action))continue;
            $path=(string)($action['path']??'');$label=trim((string)($action['label']??''));
            if($label===''||isset($seen[$path])||!self::safeActionPath($path,$scope))continue;
            $seen[$path]=true;$safeActions[]=['label'=>mb_substr($label,0,80),'path'=>$path];
            if(count($safeActions)===6)break;
        }
        foreach($facts as &$fact)unset($fact['source']);unset($fact);
        $components=[['id'=>'root','component'=>'Column','children'=>['heading']],
            ['id'=>'heading','component'=>'Text','text'=>['@path'=>'/heading']]];
        if($facts) {
            $components[0]['children'][]='facts';
            $components[]=['id'=>'facts','component'=>'Column','children'=>array_map(static fn(int $i):string=>'fact_'.$i,array_keys($facts))];
            foreach($facts as $i=>$fact) {
                $children=['fact_title_'.$i,'fact_context_'.$i];
                $components[]=['id'=>'fact_'.$i,'component'=>'Card','child'=>'fact_body_'.$i];
                $components[]=['id'=>'fact_title_'.$i,'component'=>'Text','text'=>['@path'=>"/facts/$i/title"]];
                $components[]=['id'=>'fact_context_'.$i,'component'=>'Text','variant'=>'caption','text'=>['@path'=>"/facts/$i/context"]];
                foreach($fact['metrics'] as $j=>$metric) {
                    $id="metric_{$i}_$j";$children[]=$id;
                    $components[]=['id'=>$id,'component'=>'Row','children'=>[$id.'_label',$id.'_value']];
                    $components[]=['id'=>$id.'_label','component'=>'Text','text'=>['@path'=>"/facts/$i/metrics/$j/label"]];
                    $components[]=['id'=>$id.'_value','component'=>'Text','text'=>['@path'=>"/facts/$i/metrics/$j/value"]];
                }
                $components[]=['id'=>'fact_body_'.$i,'component'=>'Column','children'=>$children];
            }
        }
        if($reports) {
            $components[0]['children'][]='report';
            $components[]=['id'=>'report','component'=>'Column','children'=>['report_title','report_context','report_basis','report_coverage','report_rows']];
            foreach(['title','context','basis','coverage'] as $key) {
                $component=['id'=>'report_'.$key,'component'=>'Text','text'=>['@path'=>'/reports/0/'.$key]];
                if($key!=='title')$component['variant']='caption';
                $components[]=$component;
            }
            $components[]=['id'=>'report_rows','component'=>'Column','children'=>array_map(static fn(int $i):string=>'report_row_'.$i,array_keys($reports[0]['rows']))];
            foreach($reports[0]['rows'] as $i=>$row) {
                $children=['report_row_title_'.$i];
                $components[]=['id'=>'report_row_'.$i,'component'=>'Card','child'=>'report_row_body_'.$i];
                $components[]=['id'=>'report_row_title_'.$i,'component'=>'Text','text'=>['@path'=>"/reports/0/rows/$i/title"]];
                foreach($row['values'] as $j=>$value) {
                    $id="report_value_{$i}_$j";$children[]=$id;
                    $components[]=['id'=>$id,'component'=>'Row','children'=>[$id.'_label',$id.'_value']];
                    foreach(['label','value'] as $key)$components[]=['id'=>$id.'_'.$key,'component'=>'Text','text'=>['@path'=>"/reports/0/rows/$i/values/$j/$key"]];
                }
                $components[]=['id'=>'report_row_body_'.$i,'component'=>'Column','children'=>$children];
            }
        }
        foreach($sections as $i=>$section) {
            $components[0]['children'][]='answer_'.$i;
            $components[]=['id'=>'answer_'.$i,'component'=>'Card','child'=>'answer_text_'.$i];
            $components[]=['id'=>'answer_text_'.$i,'component'=>'Text','text'=>['@path'=>"/sections/$i/body"]];
        }
        if($safeActions) {
            $components[0]['children'][]='actions';
            $components[]=['id'=>'actions','component'=>'Column','children'=>array_map(static fn(int $i):string=>'action_'.$i,array_keys($safeActions))];
            foreach($safeActions as $i=>$action) {
                $components[]=['id'=>'action_label_'.$i,'component'=>'Text','text'=>['@path'=>"/actions/$i/label"]];
                $components[]=['id'=>'action_'.$i,'component'=>'Button','child'=>'action_label_'.$i,
                    'action'=>['functionCall'=>['@call'=>'openUrl','args'=>['url'=>['@path'=>"/actions/$i/path"]]]]];
            }
        }
        $messages=[['version'=>PublicA2uiService::VERSION,'createSurface'=>[
            'surfaceId'=>'bapx_'.$scope.'_'.bin2hex(random_bytes(8)),
            'catalogId'=>PublicA2uiService::CATALOG,'components'=>$components,
            'dataModel'=>['heading'=>$scope==='admin'?'Enterprise answer':'Support answer',
                'presentation'=>$facts||$reports?'report_summary':'answer_sections',
                'sections'=>$sections,'facts'=>$facts,'reports'=>$reports,'actions'=>$safeActions],
        ]]];
        // History/replay deliberately omit current public references: no new prices.
        if(isset($references['messages'][0])&&count($references['messages'])===1)$messages[]=$references['messages'][0];
        return ['messages'=>$messages];
    }
}
