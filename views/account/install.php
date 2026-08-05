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
                <div class="account-install__actions">
                    <button class="btn btn-primary" id="pwa-install-action" type="button">Install App</button>
                    <span class="account-install__fallback-note" id="pwa-fallback-note" hidden>Use browser menu → Install app</span>
                </div>
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
    var fallbackNote=document.getElementById('pwa-fallback-note');
    var standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
    var isiOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
    var reasons=[];

    function usable(){ action.hidden=false; fallbackNote.hidden=true; }
    function fallback(){ action.hidden=false; fallbackNote.hidden=false; }

    function render(state){
        status.dataset.pwaState=state;
        if(state==='installed'){
            title.textContent='App is installed';
            copy.textContent='Sri Panchami Spiritual is already running as an installed app on this device.';
            action.hidden=true; fallbackNote.hidden=true;
        }else if(state==='available'){
            title.textContent='Ready to install';
            copy.textContent='This browser can install the app directly. Tap the button below.';
            usable();
        }else if(state==='installing'){
            title.textContent='Installation started';
            copy.textContent='Follow the browser confirmation. This page will update when installation finishes.';
            action.hidden=true; fallbackNote.hidden=true;
        }else if(state==='ios'){
            title.textContent='Install from Safari';
            copy.textContent='Tap Share, then Add to Home Screen to install Sri Panchami Spiritual on your device.';
            fallback();
        }else if(state==='no-service-worker'){
            title.textContent='Not available in this browser';
            copy.textContent='This browser does not support service workers, which are required for app installation. Try the latest Chrome, Edge, or Safari.';
            fallback();
        }else if(state==='not-secure'){
            title.textContent='HTTPS required';
            copy.textContent='App installation requires a secure (HTTPS) connection. Visit this page over HTTPS to install.';
            action.hidden=true; fallbackNote.hidden=true;
        }else if(state==='no-event'){
            title.textContent='Install from browser menu';
            copy.textContent='Use Install app or Add to Home screen in your browser menu. If the option is missing, update your browser and reload.';
            fallback();
        }else{
            title.textContent='Install from your browser menu';
            copy.textContent='Use Install app or Add to Home screen in the browser menu.';
            fallback();
        }
    }

    if(standalone){ render('installed'); return; }

    if(!('serviceWorker' in navigator)){
        render('no-service-worker');
        return;
    }
    if(window.location.protocol!=='https:'&&window.location.hostname!=='localhost'&&window.location.hostname!=='127.0.0.1'){
        reasons.push('page not served over HTTPS');
    }
    try{if(window.self!==window.top){reasons.push('page loaded in an iframe');}}catch(e){}

    if(isiOS){
        render('ios');
        return;
    }

    action.addEventListener('click',function(){
        if(deferredPrompt){
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function(choice){
                deferredPrompt=null;
                if(choice.outcome==='accepted'){ render('installing'); }
                else { render('available'); }
            });
            return;
        }
        // Without a stored prompt the click did nothing at all, so the button looked
        // broken. Say why, and give the manual route every browser supports.
        if(standalone){ render('installed'); return; }
        render('no-event');
        if(window.showToast){
            showToast(isiOS
                ? 'Open the Share menu and choose "Add to Home Screen".'
                : 'Your browser has not offered installation yet. Use the browser menu and choose "Install app", or reload and try again.','info');
        }
    });

    window.addEventListener('beforeinstallprompt',function(event){
        event.preventDefault();
        deferredPrompt=event;
        render('available');
    });
    window.addEventListener('appinstalled',function(){
        deferredPrompt=null;
        render('installed');
    });
    setTimeout(function(){
        if(!deferredPrompt&&!standalone)render('no-event');
    },3000);
})();
</script>
