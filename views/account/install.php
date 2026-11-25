<div class="section account-page">
    <div class="account-layout">
        <?php require __DIR__ . '/_nav.php'; ?>
        <div class="account-content account-install">
            <header class="account-install__header">
                <h1>Install the app</h1>
                <p>Open the site directly from your home screen or desktop app menu.</p>
            </header>

            <section class="account-install__status" aria-live="polite" data-pwa-state="manual">
                <span class="account-install__status-icon" aria-hidden="true">↓</span>
                <div>
                    <strong id="pwa-status-title">Install from your browser menu</strong>
                    <p id="pwa-status-copy">Follow the steps below for your device.</p>
                </div>
                <div class="account-install__actions">
                    <button class="btn btn-primary" id="pwa-install-action" type="button" hidden>Install app</button>
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

            <p class="account-install__note">Use the same account to sign in on the installed app. Sign out when you finish on a shared device.</p>
        </div>
    </div>
</div>

<script>
(() => {
    let promptEvent = null;
    const status = document.querySelector('.account-install__status');
    const title = document.getElementById('pwa-status-title');
    const copy = document.getElementById('pwa-status-copy');
    const action = document.getElementById('pwa-install-action');

    const render = (state, heading, description) => {
        status.dataset.pwaState = state;
        title.textContent = heading;
        copy.textContent = description;
        action.hidden = state !== 'available';
    };
    const installed = () => render('installed', 'App installed', 'You can open it from your home screen or desktop app menu.');
    const manual = () => render('manual', 'Install from your browser menu', 'Follow the steps below for your device.');

    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        installed();
        return;
    }
    if (!window.isSecureContext) {
        render('not-secure', 'Open the secure site to install', 'Use the HTTPS version of this page and follow the steps below.');
        return;
    }
    if (!('serviceWorker' in navigator)) {
        render('no-service-worker', 'Installation is unavailable in this browser', 'Try opening this page in a browser that supports app installation.');
        return;
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        promptEvent = event;
        render('available', 'Ready to install', 'Select Install app and follow your browser’s instructions.');
    });
    window.addEventListener('appinstalled', () => {
        promptEvent = null;
        installed();
    });
    action.addEventListener('click', async () => {
        if (!promptEvent) return;
        const nextPrompt = promptEvent;
        promptEvent = null;
        action.disabled = true;
        try {
            await nextPrompt.prompt();
            const choice = await nextPrompt.userChoice;
            if (status.dataset.pwaState === 'installed') return;
            if (choice.outcome === 'accepted') {
                render('installing', 'Installation started', 'Follow your browser’s confirmation to finish.');
            } else {
                manual();
            }
        } catch (error) {
            if (status.dataset.pwaState !== 'installed') manual();
        } finally {
            action.disabled = false;
        }
    });
})();
</script>
