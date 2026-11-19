(() => {
    const main = document.querySelector('main');
    if (!main) return;

    const placeholder = main.querySelector('[data-section-index]');
    if (placeholder) {
        const sections = Array.from(main.querySelectorAll('[data-page-section][id]'))
            .filter(section => section.getClientRects().length && section.dataset.pageSection);
        if (sections.length > 1) {
            const nav = document.createElement('nav');
            nav.className = 'page-sections';
            nav.setAttribute('aria-label', 'On this page');
            const links = sections.map(section => {
                const link = document.createElement('a');
                link.href = '#' + encodeURIComponent(section.id);
                link.textContent = section.dataset.pageSection;
                nav.append(link);
                return link;
            });
            placeholder.replaceWith(nav);
            main.querySelectorAll('[data-section-fallback]').forEach(link => { link.hidden = true; });
            let current = -1;
            let scheduled = false;
            function updateCurrent() {
                scheduled = false;
                const offset = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
                const atEnd = window.scrollY + window.innerHeight >= document.scrollingElement.scrollHeight - 2;
                let next = -1;
                let anchored = -1;
                let latestTop = -Infinity;
                sections.forEach((section, index) => {
                    const top = section.getBoundingClientRect().top;
                    const margin = parseFloat(getComputedStyle(section).scrollMarginTop) || 0;
                    const reached = top <= offset + margin + 24;
                    if (links[index].hash === location.hash && top >= -24 && top < window.innerHeight
                        && (reached || atEnd || document.activeElement === section)) anchored = index;
                    if (reached && top > latestTop + 8) {
                        next = index;
                        latestTop = top;
                    }
                });
                if (anchored !== -1) next = anchored;
                if (next !== current) {
                    links[current]?.removeAttribute('aria-current');
                    links[next]?.setAttribute('aria-current', 'location');
                    current = next;
                }
            }
            function scheduleUpdate() {
                if (scheduled) return;
                scheduled = true;
                window.requestAnimationFrame(updateCurrent);
            }
            window.addEventListener('scroll', scheduleUpdate, {passive: true});
            window.addEventListener('resize', scheduleUpdate);
            window.addEventListener('pageshow', scheduleUpdate);
            window.addEventListener('hashchange', scheduleUpdate);
            main.addEventListener('focusin', scheduleUpdate);
            scheduleUpdate();
        }
    }

    const status = document.createElement('div');
    status.className = 'page-action-status';
    status.setAttribute('role', 'status');
    document.body.append(status);

    let pending;
    let showTimer;
    let clearTimer;
    function clearStatus() {
        window.clearTimeout(showTimer);
        window.clearTimeout(clearTimer);
        pending?.removeAttribute('data-action-pending');
        status.textContent = '';
        pending = null;
    }
    window.addEventListener('pageshow', clearStatus);

    document.addEventListener('submit', event => {
        const form = event.target;
        const button = event.submitter;
        if (!(form instanceof HTMLFormElement)) return;
        const method = button?.getAttribute('formmethod') || form.getAttribute('method') || 'get';
        const target = button?.getAttribute('formtarget') || form.getAttribute('target') || '_self';
        if (method.toLowerCase() !== 'post' || target.toLowerCase() !== '_self') return;
        const action = button?.getAttribute('formaction') ?? form.getAttribute('action') ?? '';
        let destination;
        try { destination = new URL(action, document.baseURI); }
        catch { return; }
        if (destination.origin !== location.origin) return;

        // Wait until checkout, cart and other existing handlers have had the chance
        // to cancel native navigation. Keep the real submitter in the POST payload.
        window.setTimeout(() => {
            if (event.defaultPrevented) return;
            clearStatus();
            showTimer = window.setTimeout(() => {
                if (document.hidden || !form.isConnected) return;
                pending = button;
                pending?.setAttribute('data-action-pending', '');
                status.textContent = 'Submitting…';
                clearTimer = window.setTimeout(clearStatus, 8000);
            }, 150);
        }, 0);
    });
})();
