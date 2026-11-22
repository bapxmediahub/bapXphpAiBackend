<?php
declare(strict_types=1);
namespace App\Services;

/** Pure transitions called only while the database host owns the relevant row locks. */
final class RemoteCheckoutStateService
{
    private static function fail(string $code,string $message,int $status=409):never
    { throw new RemoteCartException($code,$message,$status); }

    private static function hash(mixed $value):bool
    { return is_string($value)&&preg_match('/^[a-f0-9]{64}$/D',$value)===1; }

    private static function id(mixed $value):bool
    { return is_string($value)&&preg_match('/^[a-f0-9]{32}$/D',$value)===1; }

    /** Cart and optional new attempt are saved in the same transaction. */
    public static function cart(?array $record,?array $attempt,string $operation,array $args,int $now):array
    {
        $authenticated=RemoteCartService::transition($record,'get',$args,$now);$record=$authenticated['record'];
        if(isset($record['checkout_receipt'])&&$attempt===null)self::fail('checkout_unavailable','The confirmed checkout is temporarily unavailable.',503);
        if($operation==='read_cart')return ['record'=>$record,'attempt'=>$attempt,'now'=>$now,'replayed'=>false];
        if($operation==='begin_payment'&&$attempt!==null) {
            if(($args['expectedRevision']??null)!==$attempt['cart_revision']||($args['quoteId']??null)!==$attempt['quote_id']) {
                self::fail('checkout_locked','This cart already has a different confirmed checkout.');
            }
            return ['record'=>$record,'attempt'=>$attempt,'now'=>$now,'created'=>false,'replayed'=>true];
        }
        if($record['status']!=='open')self::fail('checkout_locked','This checkout is locked. Check payment status before starting a different cart.');
        if(!is_int($args['expectedRevision']??null)||$args['expectedRevision']!==$record['revision']) {
            self::fail('cart_conflict','The cart changed. Get its current revision before continuing.');
        }
        if($operation==='save_review') {
            $draft=$args['draft']??null;
            if(!is_array($draft)||!is_array($draft['delivery']??null)||!is_string($draft['coupon_code']??null)
                ||!is_array($draft['totals']??null)||!is_array($draft['pending_order']??null)||!self::hash($draft['digest']??null)
                ||strlen(json_encode($draft,JSON_THROW_ON_ERROR))>65536)self::fail('invalid_checkout','Invalid checkout review.',400);
            $changed=RemoteCheckoutService::digest([$draft['delivery'],$draft['coupon_code']])
                !==RemoteCheckoutService::digest([$record['checkout']['delivery']??[],$record['checkout']['coupon_code']??'']);
            $revision=$record['revision']+($changed?1:0);
            if($revision>RemoteCartService::MAX_REVISION)self::fail('cart_limit','Create a new cart to continue.');
            $digest=RemoteCheckoutService::digest(['revision'=>$revision,'pending'=>$draft['pending_order'],'totals'=>$draft['totals']]);
            if(!hash_equals($digest,$draft['digest']))self::fail('invalid_checkout','Checkout review identity is invalid.',400);
            $quote=$draft['quote']??null;
            if($quote!==null) {
                if(!is_array($quote)||!is_string($quote['id']??null)||!preg_match('/^quote_[a-f0-9]{32}$/D',$quote['id'])
                    ||($quote['revision']??null)!==$revision||($quote['digest']??null)!==$digest
                    ||!is_int($quote['expires_at']??null)||$quote['expires_at']<=$now
                    ||$quote['expires_at']>$now+RemoteCheckoutService::QUOTE_TTL
                    ||$quote['expires_at']>strtotime($record['expires_at'].' UTC'))self::fail('quote_expired','The review expired. Request a fresh quote.');
                $old=$record['checkout']['quote']??null;
                if(is_array($old)&&$old['digest']===$digest&&$old['expires_at']>$now)$draft['quote']=$old;
            }
            if($changed){$record['revision']=$revision;unset($record['handoff'],$record['last_change']);}
            $record['checkout']=$draft;$record['updated_at']=gmdate('Y-m-d H:i:s',$now);
            return ['record'=>$record,'attempt'=>null,'now'=>$now,'replayed'=>!$changed];
        }
        if($operation!=='begin_payment')self::fail('invalid_operation','Unknown checkout operation.',400);
        $quote=$record['checkout']['quote']??null;$candidate=$args['attempt']??null;
        if(!is_array($quote)||($args['quoteId']??null)!==$quote['id']||($args['quoteDigest']??null)!==$quote['digest']
            ||$quote['revision']!==$record['revision']||$quote['expires_at']<=$now)self::fail('quote_expired','Review and confirm the current quote before payment.');
        if(!is_array($candidate)||!self::id($candidate['id']??null)||$candidate['cart_id']!==$record['id']
            ||$candidate['cart_revision']!==$record['revision']||$candidate['quote_id']!==$quote['id']||$candidate['quote_digest']!==$quote['digest']
            ||$candidate['reference_id']!=='ac_'.$candidate['id']||!in_array($candidate['mode']??null,['test','live'],true)
            ||($candidate['currency']??null)!=='INR'||!is_int($candidate['amount_paise']??null)||$candidate['amount_paise']<RemoteCheckoutService::MIN_AMOUNT_PAISE
            ||$candidate['amount_paise']>RemoteCheckoutService::MAX_AMOUNT_PAISE
            ||$candidate['amount_paise']!==$record['checkout']['totals']['totalPaise']
            ||!self::hash($candidate['status_hash']??null)||!self::hash($candidate['handoff_hash']??null)
            ||!is_int($candidate['status_expires_at']??null)||$candidate['status_expires_at']<=$now
            ||$candidate['status_expires_at']>$now+RemoteCheckoutService::STATUS_TTL
            ||!is_int($candidate['payment_expires_at']??null)||$candidate['payment_expires_at']<$now+900
            ||$candidate['payment_expires_at']>$now+RemoteCheckoutService::PAYMENT_TTL
            ||RemoteCheckoutService::digest($candidate['pending_order']??null)!==RemoteCheckoutService::digest($record['checkout']['pending_order'])) {
            self::fail('invalid_checkout','The confirmed payment attempt is invalid.',400);
        }
        $candidate['state']='preparing';$candidate['payment_status']='pending';$candidate['order_status']='not_created';
        $candidate['created_at']=gmdate('Y-m-d H:i:s',$now);$candidate['updated_at']=$candidate['created_at'];$candidate['checked_at']=$now;
        $record['checkout_receipt']=['attempt_id'=>$candidate['id'],'status_hash'=>$candidate['status_hash'],
            'status_expires_at'=>$candidate['status_expires_at']];
        $record['status']='checkout_locked';$record['updated_at']=gmdate('Y-m-d H:i:s',$now);unset($record['handoff'],$record['last_change']);
        return ['record'=>$record,'attempt'=>$candidate,'now'=>$now,'created'=>true,'replayed'=>false];
    }

    public static function attempt(?array $attempt,string $operation,array $args,int $now):array
    {
        if(!self::id($args['id']??null))self::fail('invalid_capability','Invalid checkout capability.',400);
        $hashField=match($operation){'read_attempt'=>'status_hash','read_handoff'=>'handoff_hash',default=>null};
        $given=$args[$operation==='read_handoff'?'handoffHash':'statusHash']??null;
        if($attempt===null||($hashField!==null&&(!self::hash($given)||!self::hash($attempt[$hashField]??null)||!hash_equals($attempt[$hashField],$given)))) {
            self::fail('checkout_not_found','This checkout capability is unknown or unavailable.',404);
        }
        if($operation==='read_attempt'&&$attempt['status_expires_at']<=$now)self::fail('checkout_expired','The seven-day status capability has expired.',410);
        if($operation==='read_handoff'&&$attempt['payment_expires_at']<=$now)self::fail('checkout_expired','This payment link expired. Check the existing attempt’s status before starting another checkout.',410);
        if(in_array($operation,['read_attempt','read_handoff','read_internal'],true))return ['attempt'=>$attempt,'now'=>$now];
        if($operation==='claim_reconcile') {
            if($attempt['order_status']==='saved'||($attempt['reconcile_after']??0)>$now)return ['attempt'=>$attempt,'now'=>$now,'claimed'=>false];
            $attempt['reconcile_after']=$now+30;$attempt['updated_at']=gmdate('Y-m-d H:i:s',$now);
            return ['attempt'=>$attempt,'now'=>$now,'claimed'=>true];
        }
        if($operation!=='update_attempt')self::fail('invalid_operation','Unknown checkout operation.',400);
        $change=$args['change']??null;
        if(!in_array($change,['link','uncertain','not_found','verified','save_pending','completed'],true))self::fail('invalid_operation','Unknown checkout update.',400);
        // A delayed failure/expired event cannot downgrade a verified payment or order.
        if($attempt['order_status']==='saved')return ['attempt'=>$attempt,'now'=>$now];
        if($attempt['payment_status']==='captured'&&in_array($change,['link','uncertain','not_found'],true))return ['attempt'=>$attempt,'now'=>$now];
        if($change==='link') {
            if(!is_string($args['linkId']??null)||!preg_match('/^plink_[A-Za-z0-9]{1,64}$/D',$args['linkId'])
                ||!RemoteCheckoutService::validProviderUrl($args['providerUrl']??null)
                ||!in_array($args['paymentStatus']??null,['pending','expired','cancelled'],true)
                ||(isset($attempt['provider_link_id'])&&$attempt['provider_link_id']!==$args['linkId']))self::fail('payment_mismatch','The provider checkout does not match this attempt.',503);
            $attempt['provider_link_id']=$args['linkId'];$attempt['provider_url']=$args['providerUrl'];
            $attempt['payment_status']=$args['paymentStatus'];$attempt['state']=$args['paymentStatus']==='pending'?'ready':$args['paymentStatus'];unset($attempt['failure_code']);
        }elseif($change==='uncertain') {
            $attempt['state']='uncertain';$attempt['payment_status']='unknown';
            $attempt['failure_code']=($args['failure']??null)==='provider_configuration'?'provider_configuration':'provider_unavailable';
        }elseif($change==='not_found') {
            // An empty authenticated reference lookup does not prove a provider
            // declined/expired the link (for example, credentials may have changed).
            $attempt['state']='uncertain';$attempt['payment_status']='unknown';
        }elseif($change==='verified') {
            if(!is_string($args['paymentId']??null)||!preg_match('/^pay_[A-Za-z0-9]{1,64}$/D',$args['paymentId'])
                ||!is_string($args['providerOrderId']??null)||!preg_match('/^order_[A-Za-z0-9]{1,64}$/D',$args['providerOrderId'])
                ||(isset($attempt['verified_payment_id'])&&$attempt['verified_payment_id']!==$args['paymentId']))self::fail('payment_mismatch','The verified payment identity changed.',503);
            $attempt['verified_payment_id']=$args['paymentId'];$attempt['provider_order_id']=$args['providerOrderId'];
            $attempt['payment_status']='captured';$attempt['order_status']='save_pending';$attempt['state']='verified';unset($attempt['failure_code']);
        }else {
            if($attempt['payment_status']!=='captured'||empty($attempt['verified_payment_id']))self::fail('payment_unverified','The payment must be verified before recording an order.',503);
            if($change==='completed') {
                if(!is_string($args['orderId']??null)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$args['orderId']))self::fail('order_unavailable','The verified order could not be recorded.',503);
                $attempt['order_id']=$args['orderId'];$attempt['order_status']='saved';$attempt['state']='completed';
                // The canonical paid order now owns delivery details; the status receipt does not need a second copy.
                unset($attempt['pending_order']);
            }else{$attempt['order_status']='save_pending';$attempt['state']='verified';}
        }
        $attempt['checked_at']=$now;$attempt['updated_at']=gmdate('Y-m-d H:i:s',$now);$attempt['reconcile_after']=$now+5;
        return ['attempt'=>$attempt,'now'=>$now];
    }
}
