<?php
namespace App\Services;

final class SupportTicketService {
    public function __construct(private DatabaseService $store = new DatabaseService()) {}

    public function all(): array {
        return $this->store->read('support_tickets');
    }

    public function forUser(string $email): array {
        $email = trim($email);
        if ($email === '') return [];
        $rows = $this->store->query('SELECT id, _data FROM support_tickets WHERE _owner = ?', [$email]);
        $tickets = [];
        foreach ($rows as $row) {
            $ticket = json_decode((string)($row['_data'] ?? ''), true);
            if (!is_array($ticket) || strcasecmp(trim((string)($ticket['customer_email'] ?? '')), $email) !== 0) continue;
            $ticket['id'] = (string)$row['id'];
            $tickets[] = $ticket;
        }
        return $tickets;
    }

    public static function actionIdentity(string $email, array $handoff): string {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)
            || !preg_match('/^[a-f0-9]{32}$/D',(string)($handoff['conversation_id']??''))
            || !preg_match('/^[a-f0-9]{16}$/D',(string)($handoff['trace_id']??''))) {
            throw new \InvalidArgumentException('Invalid support action identity.');
        }
        return substr(hash('sha256','support-ticket:v1:'.$email.':'.$handoff['conversation_id'].':'.$handoff['trace_id']),0,16);
    }

    public function create(string $customerEmail, string $message, string $context = '', ?array $handoff = null): array {
        $customerEmail=strtolower(trim($customerEmail));
        if(!filter_var($customerEmail,FILTER_VALIDATE_EMAIL)||trim($message)==='')throw new \InvalidArgumentException('An account and support question are required.');
        $ticket=['id'=>$handoff===null?bin2hex(random_bytes(8)):self::actionIdentity($customerEmail,$handoff),
            'customer_email'=>$customerEmail,'message'=>mb_substr($message,0,2000),
            'context'=>AgentTranscriptSanitizer::scrub(mb_substr($context,0,1000)),
            'status'=>'open','work_state'=>'active','priority'=>'normal','version'=>0,
            'created_at'=>gmdate('c'),'updated_at'=>gmdate('c')];
        if($handoff!==null){
            $ticket['conversation_id']=$handoff['conversation_id'];$ticket['trace_id']=$handoff['trace_id'];
            if(preg_match('/^[a-f0-9]{32}$/D',(string)($handoff['turn_id']??'')))$ticket['turn_id']=$handoff['turn_id'];
            $ticket['handoff_reason']='customer_support_escalation';
            $ticket['request_hash']=hash('sha256',$message);
        }
        $stored=$this->store->insertIfAbsent('support_tickets',$ticket)['record'];
        if(strcasecmp((string)($stored['customer_email']??''),$customerEmail)!==0
            || ($handoff!==null&&!hash_equals((string)($stored['request_hash']??''),$ticket['request_hash']))) {
            throw new \RuntimeException('Support action identity conflicts with the saved request.');
        }
        return $stored;
    }

    public function receipt(string $email,array $handoff): ?array {
        $record=$this->store->find('support_tickets',self::actionIdentity($email,$handoff));
        return $record!==null&&strcasecmp((string)($record['customer_email']??''),trim($email))===0?$record:null;
    }

    /** Pure lifecycle contract used only by the DB-host atomic mutation below. */
    public static function transition(array $ticket,string $action,array $fields,array $actor,\DateTimeImmutable $now): array {
        if(($actor['role']??'')!=='admin'&&empty($actor['is_admin']))throw new \InvalidArgumentException('Admin role required.');
        $actorId=(string)($actor['sub']??$actor['id']??'');
        if($actorId==='')throw new \InvalidArgumentException('Authenticated admin identity required.');
        $at=$now->setTimezone(new \DateTimeZone('UTC'))->format('c');
        $before=(string)($ticket['status']??'open');
        switch($action){
            case 'assign':
                $assignee=(string)($fields['assigned_admin_id']??$actorId);
                if($assignee!==$actorId)throw new \InvalidArgumentException('Use claim to assign the ticket to yourself.');
                if(!empty($ticket['assigned_admin_id'])&&$ticket['assigned_admin_id']!==$actorId)throw new \InvalidArgumentException('Another admin owns this ticket; release it before reassignment.');
                $priority=(string)($fields['priority']??'normal');
                if(!in_array($priority,['low','normal','high','urgent'],true))throw new \InvalidArgumentException('Invalid priority.');
                $ticket['assigned_admin_id']=$actorId;$ticket['priority']=$priority;break;
            case 'release':
                if(!empty($ticket['assigned_admin_id'])&&$ticket['assigned_admin_id']!==$actorId)throw new \InvalidArgumentException('Only the assigned admin can release the ticket.');
                $ticket['assigned_admin_id']=null;break;
            case 'reply':
                $reply=trim((string)($fields['reply']??''));
                if($reply===''||mb_strlen($reply)>10000)throw new \InvalidArgumentException('Enter a reply up to 10,000 characters.');
                if($before==='closed')throw new \InvalidArgumentException('Reopen this ticket before replying.');
                $ticket['reply']=$reply;$ticket['status']='answered';$ticket['work_state']='waiting';$ticket['waiting_on']='customer';
                $ticket['first_human_response_at']??=$at;$ticket['last_human_response_at']=$at;break;
            case 'close':
                if($before==='closed')return $ticket;
                $ticket['status']='closed';$ticket['work_state']='closed';$ticket['resolved_at']=$at;$ticket['waiting_on']='none';break;
            case 'reopen':
                if($before!=='closed')throw new \InvalidArgumentException('Only a closed ticket can be reopened.');
                $ticket['resolution_episodes'][]=['resolved_at'=>$ticket['resolved_at']??null,'reopened_at'=>$at];
                $ticket['status']='open';$ticket['work_state']='active';$ticket['waiting_on']='team';
                $ticket['reopen_count']=(int)($ticket['reopen_count']??0)+1;$ticket['resolved_at']=null;break;
            case 'wait':
                $waiting=(string)($fields['waiting_on']??'customer');
                if(!in_array($waiting,['customer','team'],true)||$before==='closed')throw new \InvalidArgumentException('Invalid waiting state.');
                $ticket['work_state']='waiting';$ticket['waiting_on']=$waiting;break;
            case 'snooze':
                if($before==='closed')throw new \InvalidArgumentException('Closed tickets cannot be snoozed.');
                $date=(string)($fields['snoozed_until']??'');
                if(!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?(?:Z|[+-]\d{2}:\d{2})?$/D',$date))throw new \InvalidArgumentException('Enter an explicit snooze date and time.');
                try{$until=new \DateTimeImmutable($date,new \DateTimeZone('UTC'));}catch(\Throwable){throw new \InvalidArgumentException('Invalid snooze time.');}
                $dateErrors=\DateTimeImmutable::getLastErrors();
                if(is_array($dateErrors)&&($dateErrors['warning_count']||$dateErrors['error_count']))throw new \InvalidArgumentException('Invalid snooze date.');
                if($until<=$now||$until>$now->modify('+30 days'))throw new \InvalidArgumentException('Snooze must be within the next 30 days.');
                $ticket['work_state']='snoozed';$ticket['snoozed_until']=$until->format('c');break;
            default:throw new \InvalidArgumentException('Unknown ticket transition.');
        }
        $ticket['version']=(int)($ticket['version']??0)+1;$ticket['updated_at']=$at;
        $ticket['lifecycle'][]=['version'=>$ticket['version'],'action'=>$action,'actor_id'=>$actorId,'at'=>$at,'from'=>$before,'to'=>$ticket['status']??$before];
        return $ticket;
    }

    public function update(string $id,string $action,array $fields,array $actor,int $expectedVersion): array {
        $ticket=$this->store->updateSupportTicket($id,$action,$fields,$actor,$expectedVersion);
        (new AuditLogService())->record('save','support_tickets',$id,['action'=>$action,'version'=>$ticket['version']]);
        return $ticket;
    }
    public function reply(string $ticketId,string $reply): ?array {
        $ticket=$this->store->find('support_tickets',$ticketId);if($ticket===null)return null;
        return $this->update($ticketId,'reply',['reply'=>$reply],$_SESSION['user']??[],(int)($ticket['version']??0));
    }
    public function close(string $ticketId): bool {
        $ticket=$this->store->find('support_tickets',$ticketId);if($ticket===null)return false;
        $this->update($ticketId,'close',[],$_SESSION['user']??[],(int)($ticket['version']??0));return true;
    }
}
