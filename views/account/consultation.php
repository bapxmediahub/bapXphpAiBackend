<?php $isAstrologer = ($currentUser['role'] ?? '') === 'astrologer'; ?>
<section class="section consultation-room" data-session-id="<?= e($session['id'] ?? '') ?>" data-user-id="<?= e($currentUser['sub'] ?? '') ?>" data-role="<?= e($currentUser['role'] ?? 'customer') ?>">
    <header class="consultation-room__header">
        <div><span class="eyebrow">Private Consultation</span><h1><?= e($isAstrologer ? ($session['customer_name'] ?? 'Customer') : ($session['astrologer_name'] ?? 'Astrologer')) ?></h1><p id="session-status">Status: <?= e(ucfirst(str_replace('_',' ',(string)($session['status'] ?? 'requested')))) ?></p></div>
        <a class="btn btn-sm btn-ghost" href="<?= $isAstrologer ? '/astrologer' : '/account/dashboard/sessions' ?>">Back</a>
    </header>
    <div class="consultation-room__grid">
        <div class="panel consultation-chat">
            <div class="consultation-chat__messages" id="message-list" aria-live="polite">
                <?php foreach($messages as $message): ?>
                    <article class="chat-message <?= ($message['sender_id']??'')===($currentUser['sub']??'')?'is-own':'' ?>" data-message-id="<?= e($message['id']??'') ?>" data-created="<?= e($message['created_at']??'') ?>"><strong><?= e($message['sender_name']??ucfirst($message['sender_role']??'User')) ?></strong><p><?= e($message['body']??'') ?></p><small><?= e(substr((string)($message['created_at']??''),11,5)) ?></small></article>
                <?php endforeach; ?>
            </div>
            <form id="message-form" class="consultation-chat__composer"><label class="sr-only" for="message-body">Message</label><textarea id="message-body" maxlength="2000" rows="2" placeholder="Write a private message" required></textarea><button class="btn btn-primary" aria-label="Send message">Send</button></form>
        </div>
        <aside class="panel consultation-call">
            <h2>Audio Call</h2><p>Calls connect directly between the customer and astrologer through the browser.</p>
            <div class="call-state" id="call-state">Ready</div>
            <audio id="remote-audio" autoplay></audio>
            <div class="call-actions"><button class="btn btn-primary" id="start-call" type="button">Start Call</button><button class="btn btn-ghost" id="end-call" type="button" disabled>End</button></div>
            <?php if($isAstrologer): ?><div class="session-controls"><button data-status="accepted">Accept</button><button data-status="active">Start Session</button><button data-status="completed">Complete</button><button data-status="declined">Decline</button></div><?php endif; ?>
            <dl class="session-facts"><div><dt>Mode</dt><dd><?= e($session['session_type']??$session['mode']??'Consultation') ?></dd></div><div><dt>Credits</dt><dd><?= e((string)($session['credits_spent']??0)) ?></dd></div><div><dt>Created</dt><dd><?= e(substr((string)($session['created_at']??''),0,16)) ?></dd></div></dl>
        </aside>
    </div>
</section>
<script>
(() => {
 const root=document.querySelector('.consultation-room'), id=root.dataset.sessionId, userId=root.dataset.userId;
 const list=document.getElementById('message-list'), form=document.getElementById('message-form'), body=document.getElementById('message-body');
  let messageAfter=list.lastElementChild?.dataset.created||'', signalAfter='', pc=null, localStream=null, iceQueue=[];
 const api=(suffix,options={})=>fetch('/api/consultations/'+id+suffix,{headers:{'Content-Type':'application/json'},...options}).then(async r=>{const data=await r.json();if(!r.ok)throw new Error(data.error||'Request failed');return data});
 const addMessage=m=>{if(document.querySelector('[data-message-id="'+m.id+'"]'))return;const a=document.createElement('article');a.className='chat-message '+(m.sender_id===userId?'is-own':'');a.dataset.messageId=m.id;a.dataset.created=m.created_at;a.innerHTML='<strong></strong><p></p><small></small>';a.querySelector('strong').textContent=m.sender_name||m.sender_role;a.querySelector('p').textContent=m.body;a.querySelector('small').textContent=(m.created_at||'').slice(11,16);list.appendChild(a);list.scrollTop=list.scrollHeight;messageAfter=m.created_at||messageAfter};
  form.addEventListener('submit',async e=>{e.preventDefault();const value=body.value.trim();if(!value)return;body.disabled=true;try{const d=await api('/messages',{method:'POST',body:JSON.stringify({body:value})});addMessage(d.message);body.value=''}catch(e){showToast(e.message,'error')}finally{body.disabled=false;body.focus()}});
 async function pollMessages(){try{const d=await api('/messages?after='+encodeURIComponent(messageAfter));d.messages.forEach(addMessage)}catch(e){}setTimeout(pollMessages,2500)}
 const signal=(type,payload={})=>api('/signals',{method:'POST',body:JSON.stringify({type,payload})});
 async function connection(){if(pc)return pc;localStream=await navigator.mediaDevices.getUserMedia({audio:true,video:false});pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});localStream.getTracks().forEach(t=>pc.addTrack(t,localStream));pc.ontrack=e=>document.getElementById('remote-audio').srcObject=e.streams[0];pc.onicecandidate=e=>{if(e.candidate)signal('ice',e.candidate.toJSON())};pc.onconnectionstatechange=()=>document.getElementById('call-state').textContent=pc.connectionState;document.getElementById('end-call').disabled=false;return pc}
 document.getElementById('start-call').addEventListener('click',async()=>{try{const c=await connection(),offer=await c.createOffer();await c.setLocalDescription(offer);await signal('offer',offer);document.getElementById('call-state').textContent='Calling'}catch(e){document.getElementById('call-state').textContent=e.message}});
 document.getElementById('end-call').addEventListener('click',async()=>{await signal('hangup');closeCall()});
 function closeCall(){pc?.close();localStream?.getTracks().forEach(t=>t.stop());pc=null;localStream=null;document.getElementById('call-state').textContent='Ended';document.getElementById('end-call').disabled=true}
 async function handleSignal(s){signalAfter=s.created_at||signalAfter;if(s.type==='hangup'){closeCall();return}const c=await connection();if(s.type==='offer'){await c.setRemoteDescription(s.payload);const answer=await c.createAnswer();await c.setLocalDescription(answer);await signal('answer',answer)}else if(s.type==='answer'){await c.setRemoteDescription(s.payload)}else if(s.type==='ice'&&s.payload){await c.addIceCandidate(s.payload)}}
 async function pollSignals(){try{const d=await api('/signals?after='+encodeURIComponent(signalAfter));for(const s of d.signals)await handleSignal(s)}catch(e){}setTimeout(pollSignals,1500)}
  document.querySelectorAll('[data-status]').forEach(b=>b.addEventListener('click',async()=>{try{const d=await api('/status',{method:'POST',body:JSON.stringify({status:b.dataset.status})});document.getElementById('session-status').textContent='Status: '+d.session.status.replaceAll('_',' ')}catch(e){showToast(e.message,'error')}}));
 list.scrollTop=list.scrollHeight;pollMessages();pollSignals();
})();
</script>
