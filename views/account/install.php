<div class="section account-install-section">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content account-install">
            <header class="account-install__header">
                <span class="eyebrow">Your account</span>
                <h1>Install Sri Panchami Spiritual</h1>
                <p>Keep the shop, orders, and consultation bookings available from your home screen or desktop app menu.</p>
            </header>

            <section class="account-install__status" aria-live="polite" data-pwa-state="checking">
                <span class="account-install__status-icon" aria-hidden="true">↓</span>
                <div>
                    <strong id="pwa-status-title">Checking this browser</strong>
                    <p id="pwa-status-copy">Checking if this device supports app installation…</p>
                </div>
                <button class="btn btn-primary" id="pwa-install-action" type="button" hidden>Install App</button>
            </section>

            <div class="account-install__instructions">
                <section>
                    <h2>Android, Chrome, or Edge</h2>
                    <ol>
                        <li>Open the browser menu or select the install icon in the address bar.</li>
                        <li>Choose <strong>Install app</strong> or <strong>Add to Home screen</strong>.</li>
                        <li>Confirm the installation.</li>
                    </ol>
                </section>
                <section>
                    <h2>iPhone or iPad</h2>
                    <ol>
                        <li>Open this page in Safari.</li>
                        <li>Select <strong>Share</strong>, then <strong>Add to Home Screen</strong>.</li>
                        <li>Select <strong>Add</strong>.</li>
                    </ol>
                </section>
            </div>

            <p class="account-install__note">Installation does not create another account. Sign in with the same details, and use Logout when you finish on a shared device.</p>
        </div>
    </div>
</div>

<script>
(function(){
    var deferredPrompt=null;
    var status=document.querySelector('.account-install__status');
    var title=document.getElementById('pwa-status-title');
    var copy=document.getElementById('pwa-status-copy');
    var action=document.getElementById('pwa-install-action');
    var standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
    var isiOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
    var reasons=[];

    function render(state){
        status.dataset.pwaState=state;
        action.hidden=true;
        if(state==='installed'){
            title.textContent='App installed';
            copy.textContent='Sri Panchami Spiritual is already running as an installed app on this device.';
        }else if(state==='available'){
            title.textContent='Ready to install';
            copy.textContent='This browser can install the app directly. Tap the button below.';
            action.hidden=false;
        }else if(state==='ios'){
            title.textContent='Install from Safari';
            copy.textContent='Use Share, then Add to Home Screen. Apple devices do not show a browser install prompt.';
        }else if(state==='installing'){
            title.textContent='Installation started';
            copy.textContent='Follow the browser confirmation. This page will update when installation finishes.';
        }else if(state==='no-service-worker'){
            title.textContent='Not available in this browser';
            copy.textContent='This browser does not support service workers, which are required for app installation. Try the latest Chrome, Edge, or Safari.';
        }else if(state==='not-secure'){
            title.textContent='HTTPS required';
            copy.textContent='App installation requires a secure (HTTPS) connection. Visit this page over HTTPS to install.';
        }else if(state==='iframe'){
            title.textContent='Opened in an embedded frame';
            copy.textContent='Installation is not available inside an embedded view. Open this page in your main browser window.';
        }else if(state==='no-event'){
            title.textContent='Not installable in this browser';
            copy.textContent='Your browser did not signal that this site can be installed. Try updating your browser or use Chrome, Edge, or Safari on a supported device.'+(reasons.length?' Possible reasons: '+reasons.join(', ')+'.':'');
        }else{
            title.textContent='Install from your browser menu';
            copy.textContent='Use Install app or Add to Home screen in the browser menu. If the option is missing, update your browser and reload this page.';
        }
    }

    if(!('serviceWorker' in navigator)){
        render('no-service-worker');
        return;
    }
    if(window.location.protocol!=='https:'&&window.location.hostname!=='localhost'&&window.location.hostname!=='127.0.0.1'){
        reasons.push('page not served over HTTPS');
    }
    try{if(window.self!==window.top){reasons.push('page loaded in an iframe');}}catch(e){}

    window.addEventListener('beforeinstallprompt',function(event){
        event.preventDefault();
        deferredPrompt=event;
        render('available');
    });
    window.addEventListener('appinstalled',function(){
        deferredPrompt=null;
        render('installed');
    });
    action.addEventListener('click',function(){
        if(!deferredPrompt)return;
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(choice){
            deferredPrompt=null;
            render(choice.outcome==='accepted'?'installing':(isiOS?'ios':'no-event'));
        });
    });
    if(standalone){
        render('installed');
    }else if(isiOS){
        render('ios');
    }else if(reasons.length>0&&reasons.indexOf('page loaded in an iframe')!==-1){
        render('iframe');
    }else if(reasons.length>0&&reasons.indexOf('page not served over HTTPS')!==-1){
        render('not-secure');
    }else{
        setTimeout(function(){
            if(!deferredPrompt)render('no-event');
        },3000);
    }
})();
</script>
