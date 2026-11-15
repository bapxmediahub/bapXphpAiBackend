/* Native progressive motion for the real PHP application. No animation is a visibility prerequisite. */
(function (factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else {
        var api = factory();
        window.SPSMotion = api;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { api.init(window, document); }, { once: true });
        else api.init(window, document);
    }
})(function () {
    'use strict';
    function motionAllowed(state) {
        return state.supported === true && !state.reducedMotion && !state.saveData && !state.hidden;
    }
    function entrance(index, surface) {
        var admin = surface === 'admin';
        var n = Number.isFinite(index) ? Math.max(0, index) : 0;
        return {
            keyframes: [
                { opacity: 0.82, transform: 'translateY(' + (admin ? 4 : 10) + 'px)' },
                { opacity: 1, transform: 'none' }
            ],
            options: { duration: admin ? 180 : 300, delay: Math.min(120, n * 28), easing: 'cubic-bezier(.22,.75,.25,1)', fill: 'none' }
        };
    }
    function showReturnControl(scrollY, viewportHeight) {
        return Number.isFinite(scrollY) && Number.isFinite(viewportHeight) && scrollY > Math.max(640, viewportHeight * 0.9);
    }
    function init(win, doc) {
        if (doc.documentElement.dataset.spsMotionInitialized === 'true') return;
        doc.documentElement.dataset.spsMotionInitialized = 'true';
        var surface = doc.querySelector('.admin-shell') ? 'admin' : 'public';
        var media = typeof win.matchMedia === 'function' ? win.matchMedia('(prefers-reduced-motion: reduce)') : { matches: true };
        var active = new Set(), lifecycle = [];
        var supported = !!(win.Element && typeof win.Element.prototype.animate === 'function');
        var canMove = function () {
            return motionAllowed({ supported: supported, reducedMotion: media.matches,
                saveData: !!(win.navigator.connection && win.navigator.connection.saveData), hidden: doc.hidden });
        };
        function cancelMotion() {
            active.forEach(function (animation) { try { animation.cancel(); } catch (e) {} });
            active.clear();
        }
        function play(el, index) {
            if (!canMove() || !el || el.hidden || !el.getClientRects().length || el.closest('[hidden]')) return;
            var recipe = entrance(index, surface);
            try {
                var animation = el.animate(recipe.keyframes, recipe.options);
                active.add(animation);
                var done = function () { active.delete(animation); };
                animation.addEventListener('finish', done, { once: true });
                animation.addEventListener('cancel', done, { once: true });
            } catch (e) { /* Browser without working WAAPI still has fully visible content. */ }
        }
        function preferenceChanged() {
            if (!canMove()) cancelMotion();
            doc.documentElement.dataset.spsMotion = canMove() ? 'enabled' : 'reduced';
            lifecycle.forEach(function (update) { update(); });
        }
        if (typeof media.addEventListener === 'function') media.addEventListener('change', preferenceChanged);
        else if (typeof media.addListener === 'function') media.addListener(preferenceChanged);
        doc.addEventListener('visibilitychange', preferenceChanged);
        win.addEventListener('pagehide', cancelMotion);
        win.addEventListener('pageshow', preferenceChanged);
        // Remove legacy visibility gating even when a cached old class is restored.
        doc.documentElement.classList.remove('motion-ready');
        preferenceChanged();

        var candidates = doc.querySelectorAll('main h1,.product-card,.blog-card,.temple-card,.auth-card,.section-title,.page-title,.admin-stat,.report-kpi,.admin-card > h2,.enterprise-chat__intro');
        if (typeof win.IntersectionObserver === 'function') {
            var observer = new win.IntersectionObserver(function (entries) {
                var stagger = 0;
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    observer.unobserve(entry.target);
                    play(entry.target, stagger++);
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });
            candidates.forEach(function (el) { observer.observe(el); });
        }

        // State changes are owned by existing controls, not scripted illustration clicks.
        if (typeof win.MutationObserver === 'function') {
            ['support-panel', 'agent-history-panel', 'admin-sidebar', 'primary-nav'].forEach(function (id) {
                var el = doc.getElementById(id); if (!el) return;
                var visible = !el.hidden && el.getClientRects().length > 0;
                new win.MutationObserver(function () {
                    var next = !el.hidden && el.getClientRects().length > 0;
                    if (next && !visible) play(el, 0);
                    visible = next;
                }).observe(el, { attributes: true, attributeFilter: ['hidden', 'class', 'open'] });
            });
            ['agent-messages', 'support-log', 'toast-container'].forEach(function (id) {
                var el = doc.getElementById(id); if (!el) return;
                new win.MutationObserver(function (records) {
                    var count = 0;
                    records.forEach(function (record) { record.addedNodes.forEach(function (node) {
                        if (count >= 4 || node.nodeType !== 1 || !node.matches('.agent-row,p,.toast,.support-feedback')) return;
                        play(node, count++);
                    }); });
                }).observe(el, { childList: true });
            });
        }

        var header = doc.querySelector(surface === 'admin' ? '.admin-topbar' : '#site-header');
        function updateHeaderOffset() {
            var height = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
            doc.documentElement.style.setProperty('--sps-scroll-offset', (height + 16) + 'px');
        }
        updateHeaderOffset();
        if (header && typeof win.ResizeObserver === 'function') new win.ResizeObserver(updateHeaderOffset).observe(header);
        else win.addEventListener('resize', updateHeaderOffset, { passive: true });
        var returnButton = doc.createElement('button');
        returnButton.type = 'button'; returnButton.className = 'sps-return-top'; returnButton.hidden = true;
        returnButton.setAttribute('aria-label', 'Return to top'); returnButton.title = 'Return to top';
        returnButton.innerHTML = '<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 7-7 7 7M12 5v14"/></svg>';
        doc.body.appendChild(returnButton);
        returnButton.addEventListener('click', function () {
            var destination = doc.querySelector('main');
            if (destination) {
                var hadTabindex = destination.hasAttribute('tabindex');
                if (!hadTabindex) destination.setAttribute('tabindex', '-1');
                destination.focus({ preventScroll: true });
                if (!hadTabindex) destination.addEventListener('blur', function () { destination.removeAttribute('tabindex'); }, { once: true });
            }
            win.scrollTo({ top: 0, behavior: canMove() ? 'smooth' : 'instant' });
        });
        var queued = false;
        function updateScroll() {
            queued = false;
            if (header && surface === 'public') header.classList.toggle('scrolled', win.scrollY > 8);
            returnButton.hidden = !showReturnControl(win.scrollY, win.innerHeight);
        }
        win.addEventListener('scroll', function () {
            if (queued || doc.hidden) return;
            queued = true;
            (win.requestAnimationFrame || function (callback) { return win.setTimeout(callback, 16); })(updateScroll);
        }, { passive: true });
        win.addEventListener('pageshow', updateScroll); updateScroll();
        // Standard wheel, touch, anchor and keyboard scrolling are never intercepted.

        doc.querySelectorAll('[data-varahi-slider]').forEach(function (root) {
            var slides = Array.from(root.querySelectorAll('.varahi-slide'));
            var dots = Array.from(root.querySelectorAll('.varahi-dot'));
            var pause = root.querySelector('[data-slideshow-pause]');
            if (!slides.length || slides.length !== dots.length) return;
            var current = Math.max(0, slides.findIndex(function (el) { return el.classList.contains('is-active'); }));
            var timer = null, visible = false, hovered = false, focused = false, userPaused = false;
            function stop() { if (timer !== null) win.clearInterval(timer); timer = null; }
            function show(next) {
                current = (next + slides.length) % slides.length;
                slides.forEach(function (el, i) { el.classList.toggle('is-active', i === current); });
                dots.forEach(function (el, i) {
                    el.classList.toggle('is-active', i === current);
                    el.setAttribute('aria-current', String(i === current));
                    el.setAttribute('aria-selected', String(i === current));
                    el.tabIndex = i === current ? 0 : -1;
                });
                root.dataset.currentSlide = slides[current].classList.contains('hero-guruji') ? 'guruji' : 'deity';
            }
            function update() {
                stop();
                if (pause) {
                    pause.hidden = media.matches || !!(win.navigator.connection && win.navigator.connection.saveData);
                    pause.textContent = userPaused ? 'Play slides' : 'Pause slides';
                    pause.setAttribute('aria-pressed', String(userPaused));
                }
                if (!userPaused && !hovered && !focused && visible && canMove()) timer = win.setInterval(function () { show(current + 1); }, 5000);
            }
            dots.forEach(function (dot, i) {
                dot.addEventListener('click', function () { show(i); update(); });
                dot.addEventListener('keydown', function (event) {
                    var target = event.key === 'ArrowRight' ? current + 1 : event.key === 'ArrowLeft' ? current - 1 : event.key === 'Home' ? 0 : event.key === 'End' ? dots.length - 1 : null;
                    if (target === null) return;
                    event.preventDefault(); show(target); dots[current].focus(); update();
                });
            });
            if (pause) pause.addEventListener('click', function () { userPaused = !userPaused; update(); });
            root.addEventListener('mouseenter', function () { hovered = true; update(); });
            root.addEventListener('mouseleave', function () { hovered = false; update(); });
            root.addEventListener('focusin', function () { focused = true; update(); });
            root.addEventListener('focusout', function (event) { if (!root.contains(event.relatedTarget)) { focused = false; update(); } });
            if (typeof win.IntersectionObserver === 'function') {
                new win.IntersectionObserver(function (entries) { visible = entries[0].isIntersecting; update(); }, { threshold: 0.05 }).observe(root);
            }
            lifecycle.push(update); win.addEventListener('pagehide', stop); show(current); update();
        });
    }
    return { motionAllowed: motionAllowed, entrance: entrance, showReturnControl: showReturnControl, init: init };
});
