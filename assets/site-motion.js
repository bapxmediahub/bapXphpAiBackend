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
        if (surface === 'section-heading' || surface === 'section-panel') {
            var panel = surface === 'section-panel';
            return {
                keyframes: [{ opacity: panel ? .72 : .77, transform: 'translateY(' + (panel ? 28 : 20) + 'px)' },
                    { opacity: 1, transform: 'none' }],
                options: { duration: panel ? 520 : 460, delay: 0, easing: 'cubic-bezier(.2,.65,.3,1)', fill: 'none' }
            };
        }
        if (surface === 'product-photo') {
            return {
                keyframes: [{ transform: 'scale(1.025)' }, { transform: 'none' }],
                options: { duration: 320, delay: 0, easing: 'cubic-bezier(.22,.75,.25,1)', fill: 'none' }
            };
        }
        if (surface === 'home-panel') {
            return {
                keyframes: [{ opacity: .8, transform: 'translateY(24px)' }, { opacity: 1, transform: 'none' }],
                options: { duration: 440, delay: 0, easing: 'cubic-bezier(.2,.65,.3,1)', fill: 'none' }
            };
        }
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
        var active = new Set();
        var supported = !!(win.Element && typeof win.Element.prototype.animate === 'function');
        var canMove = function () {
            return motionAllowed({ supported: supported, reducedMotion: media.matches,
                saveData: !!(win.navigator.connection && win.navigator.connection.saveData), hidden: doc.hidden });
        };
        function cancelMotion() {
            active.forEach(function (animation) { try { animation.cancel(); } catch (e) {} });
            active.clear();
        }
        function play(el, index, kind) {
            if (!canMove() || !el || el.hidden || !el.getClientRects().length || el.closest('[hidden]') || el.contains(doc.activeElement)) return;
            var recipe = entrance(index, kind || surface);
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
        }
        if (typeof media.addEventListener === 'function') media.addEventListener('change', preferenceChanged);
        else if (typeof media.addListener === 'function') media.addListener(preferenceChanged);
        doc.addEventListener('visibilitychange', preferenceChanged);
        doc.addEventListener('focusin', function (event) {
            active.forEach(function (animation) {
                if (animation.effect?.target?.contains(event.target)) animation.cancel();
            });
        });
        win.addEventListener('pagehide', cancelMotion);
        win.addEventListener('pageshow', preferenceChanged);
        // Remove legacy visibility gating even when a cached old class is restored.
        doc.documentElement.classList.remove('motion-ready');
        preferenceChanged();

        var productPhotoSelector = '.home-product-card .product-card__image img';
        var homePhotoSelector = productPhotoSelector + ',main [data-home-enter="photo"]';
        var homePanelSelector = 'main [data-home-enter="panel"]';
        var sectionHeadingSelector = 'main h1,main section > h2,main .section-header > :is(h1,h2),main [data-page-section]:is(h1,h2,h3)';
        var panelSelector = 'main [data-section-motion]';
        var nativeSections = surface === 'public' && win.CSS?.supports('animation-timeline', 'view(block 0px)')
            && win.CSS.supports('animation-range', 'entry 0% cover 38%');
        var candidates = doc.querySelectorAll(panelSelector + ',' + sectionHeadingSelector + ',' + homePhotoSelector + ',' + homePanelSelector + ',.product-card:not(.home-product-card),.blog-card,.temple-card,.auth-card,.section-title,.page-title,.admin-stat,.report-kpi,.admin-card > h2,.enterprise-chat__intro');
        var entranceTargets = Array.from(candidates).filter(function (target) {
            // Full homepage sections are already the motion surface. Avoid simultaneous nested timelines.
            if (target.closest('[data-home-stack]')) return false;
            if (!nativeSections) return true;
            if (target.matches(homePhotoSelector) || target.matches(homePanelSelector)) {
                target.classList.add('sps-home-scroll');
                return false;
            }
            if (target.closest('a')) return true;
            if (target.matches(panelSelector) || (target.matches(sectionHeadingSelector) && !target.closest(panelSelector))) {
                target.classList.add('sps-section-scroll');
                return false;
            }
            return !target.closest(panelSelector);
        });
        if (typeof win.IntersectionObserver === 'function') {
            var threshold = 0.12;
            var observer = new win.IntersectionObserver(function (entries) {
                var stagger = 0;
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting || entry.intersectionRatio < threshold) return;
                    var target = entry.target;
                    observer.unobserve(target);
                    if (target.matches(panelSelector)) play(target, 0, 'section-panel');
                    else if (target.closest(panelSelector)) return;
                    else if (target.matches(homePhotoSelector)) play(target, 0, 'product-photo');
                    else if (target.matches(homePanelSelector)) play(target, 0, 'home-panel');
                    else if (surface === 'public' && target.matches(sectionHeadingSelector) && !target.closest('a'))
                        play(target, 0, 'section-heading');
                    else if (!target.closest('a')) play(target, stagger++);
                });
            }, { threshold: threshold, rootMargin: '0px 0px -24px 0px' });
            entranceTargets.forEach(function (el) { observer.observe(el); });
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
        var stack = doc.querySelector('[data-home-stack]');
        // Keep a single footer, with the same navigation and contentinfo landmark.
        // Move only when the native sticky stack can actually be enhanced.
        var footer = stack ? doc.querySelector('body > .site-footer') : null;
        if (stack && typeof win.ResizeObserver === 'function' && win.CSS?.supports('overflow-x', 'clip')) {
            if (footer) {
                footer.setAttribute('role', 'contentinfo');
                stack.append(footer);
            }
            var cards = Array.from(stack.children).filter(function (card) {
                return card.matches('.home-opening,.home-bestsellers,.home-collection,.home-chapter,.home-faq,.site-footer');
            });
            function measureCard(card, height) {
                var value = Math.ceil(height) + 'px';
                if (card.style.getPropertyValue('--home-card-height') !== value) card.style.setProperty('--home-card-height', value);
            }
            function measureHeader(height) {
                var value = Math.ceil(height) + 'px';
                if (stack.style.getPropertyValue('--home-header-height') !== value) stack.style.setProperty('--home-header-height', value);
                updateHeaderOffset(height);
            }
            measureHeader(header ? header.offsetHeight : 0);
            cards.forEach(function (card) { measureCard(card, card.offsetHeight); });
            var resize = new win.ResizeObserver(function (entries) {
                entries.forEach(function (entry) {
                    var height = entry.borderBoxSize?.[0]?.blockSize ?? entry.target.offsetHeight;
                    if (entry.target === header) measureHeader(height);
                    else measureCard(entry.target, height);
                });
            });
            if (header) resize.observe(header, { box: 'border-box' });
            cards.forEach(function (card) { resize.observe(card, { box: 'border-box' }); });
            stack.dataset.stackReady = 'true';
        }
        function updateHeaderOffset(measured) {
            var height = typeof measured === 'number' ? Math.ceil(measured) : (header ? Math.ceil(header.getBoundingClientRect().height) : 0);
            var value = (height + 16) + 'px';
            if (doc.documentElement.style.getPropertyValue('--sps-scroll-offset') !== value) doc.documentElement.style.setProperty('--sps-scroll-offset', value);
        }
        // The stack's observer already owns header measurements on the homepage.
        if (!stack || !stack.dataset.stackReady) {
            updateHeaderOffset();
            if (header && typeof win.ResizeObserver === 'function') new win.ResizeObserver(updateHeaderOffset).observe(header);
            else win.addEventListener('resize', updateHeaderOffset, { passive: true });
        }
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
            var controls = root.querySelector('[data-portrait-controls]');
            if (!controls || slides.length < 2) return;
            var dots = Array.from(controls.querySelectorAll('[data-portrait-index]'));
            var status = controls.querySelector('[data-portrait-status]');
            if (!status || dots.length !== slides.length) return;
            var current = 0, timer = null, request = 0;
            var pending = false, pendingAutomatic = false;
            var frame = root.querySelector(".deity-frame");
            var playing = true, hovering = false, visible = false;
            function stopTimer() {
                if (timer !== null) win.clearTimeout(timer);
                timer = null;
            }
            function isPlaying() {
                return playing && canMove();
            }
            function renderRotation() {
                var enabled = isPlaying();
                status.setAttribute('aria-live', enabled ? 'off' : 'polite');
            }
            function artworkVisible() {
                if (!visible || !frame) return false;
                var box = frame.getBoundingClientRect();
                var hit = doc.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
                return !!hit && root.contains(hit);
            }
            function canRotate() {
                return isPlaying() && !hovering && artworkVisible();
            }
            function cancelAutomatic() {
                stopTimer();
                if (pending && pendingAutomatic) {
                    request++;
                    pending = pendingAutomatic = false;
                }
            }
            function schedule() {
                stopTimer();
                renderRotation();
                if (pending || !canRotate()) return;
                timer = win.setTimeout(function () {
                    timer = null;
                    if (canRotate()) selectPortrait(current + 1, true);
                }, 2000);
            }
            function pause() {
                playing = false;
                cancelAutomatic();
                schedule();
            }
            function selectPortrait(index, automatic) {
                stopTimer();
                var next = (index + slides.length) % slides.length;
                var ticket = ++request;
                var image = slides[next];
                pending = true;
                pendingAutomatic = automatic;
                function finish() {
                    if (ticket !== request) return false;
                    pending = pendingAutomatic = false;
                    return true;
                }
                function fail() { if (finish()) schedule(); }
                function apply() {
                    if (!finish()) return;
                    if (automatic && !canRotate()) { schedule(); return; }
                    if (!image.naturalWidth) { schedule(); return; }
                    slides[current].classList.remove('is-active');
                    slides[current].setAttribute('aria-hidden', 'true');
                    current = next;
                    image.classList.add('is-active');
                    image.removeAttribute('aria-hidden');
                    root.dataset.currentSlide = image.classList.contains('hero-guruji') ? 'guruji' : 'deity';
                    root.dataset.activeIndex = String(current);
                    dots.forEach(function (dot, i) { dot.setAttribute('aria-current', String(i === current)); });
                    status.textContent = image.alt;
                    schedule();
                }
                // Do not fade to an unloaded image; keep the current artwork visible.
                image.loading = 'eager';
                if (typeof image.decode === 'function') image.decode().then(apply, fail);
                else if (image.complete) apply();
                else {
                    image.addEventListener('load', apply, { once: true });
                    image.addEventListener('error', fail, { once: true });
                }
            }
            controls.querySelectorAll('[data-portrait-step]').forEach(function (button) {
                button.addEventListener('click', function () { pause(); selectPortrait(current + Number(button.dataset.portraitStep), false); });
            });
            dots.forEach(function (dot, i) {
                dot.addEventListener('click', function () { pause(); selectPortrait(i, false); });
            });
            root.addEventListener('focusin', function () { pause(); });
            root.addEventListener('pointerenter', function (event) {
                if (event.pointerType === 'mouse') { hovering = true; cancelAutomatic(); schedule(); }
            });
            root.addEventListener('pointerleave', function () { hovering = false; schedule(); });
            if (typeof win.IntersectionObserver === 'function') {
                new win.IntersectionObserver(function (entries) {
                    visible = entries[0].isIntersecting && entries[0].intersectionRatio >= 0.5;
                    if (!visible) cancelAutomatic();
                    schedule();
                }, { threshold: [0, 0.5] }).observe(root);
            }
            // Sticky cards may be geometrically visible but painted under a later slide.
            // Check the actual artwork hit target during native scrolling.
            var coverCheckQueued = false;
            function checkCover() {
                if (coverCheckQueued || !playing) return;
                coverCheckQueued = true;
                win.requestAnimationFrame(function () {
                    coverCheckQueued = false;
                    if (!canRotate()) cancelAutomatic();
                    else if (timer === null && !pending) schedule();
                });
            }
            win.addEventListener('scroll', checkCover, { passive: true });
            doc.addEventListener('visibilitychange', function () { cancelAutomatic(); schedule(); });
            if (typeof media.addEventListener === 'function') media.addEventListener('change', function () {
                if (media.matches) playing = false;
                cancelAutomatic(); schedule();
            });
            win.addEventListener('pagehide', cancelAutomatic);
            win.addEventListener('pageshow', schedule);
            controls.hidden = false;
            schedule();
        });
    }
    return { motionAllowed: motionAllowed, entrance: entrance, showReturnControl: showReturnControl, init: init };
});
