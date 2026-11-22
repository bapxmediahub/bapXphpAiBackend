/* Fixed public shopping template for MCP Apps. Only a human control invokes tools/call.
 * https://apps.extensions.modelcontextprotocol.io/api/classes/app.App.html#callservertool
 * Cart capabilities stay in this closure; no DOM, URL, storage, logging or cross-widget merge.
 */
(() => {
    'use strict';
    const tokenPattern = /^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    const paymentPattern = /^payment_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    const segmentPattern = /^[\p{L}\p{N}_][\p{L}\p{N}\p{M}._ -]*$/u;
    const record = value => value && typeof value === 'object' && !Array.isArray(value);
    const text = (value, maximum, empty = true) => typeof value === 'string'
        && (empty || value.length > 0) && [...value].length <= maximum && !/[\u0000-\u0008\u000b\u000c\u000e-\u001f\u007f]/u.test(value);
    const integer = (value, maximum = 1000000) => Number.isSafeInteger(value) && value >= 0 && value <= maximum;
    const money = (value, maximum = 1980000000000) => typeof value === 'number' && Number.isFinite(value) && value >= 0 && value <= maximum;
    const date = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value) && Number.isFinite(Date.parse(value));
    const currency = value => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(value);
    const productPath = value => {
        if (typeof value !== 'string' || value.length > 2048 || !/^\/product\/[A-Za-z0-9%_.-]+$/.test(value)) return null;
        try { const slug = decodeURIComponent(value.slice(9)); return segmentPattern.test(slug) ? slug : null; }
        catch (_) { return null; }
    };
    const imagePath = value => {
        if (typeof value !== 'string' || value.length > 512 || !/^\/assets\/images\/[A-Za-z0-9%_./-]+\.(?:avif|gif|jpe?g|png|webp)$/i.test(value)) return false;
        try { return value.slice(15).split('/').every(part => segmentPattern.test(decodeURIComponent(part))); }
        catch (_) { return false; }
    };
    const sameArguments = (left, right, depth = 0) => depth < 3 && record(left) && record(right)
        && Object.keys(left).length === Object.keys(right).length
        && Object.keys(left).every(key => Object.hasOwn(right, key) && (left[key] === right[key]
            || sameArguments(left[key], right[key], depth + 1)));
    const node = (tag, value, className) => {
        const element = document.createElement(tag);
        if (value !== undefined) element.textContent = value;
        if (className) element.className = className;
        return element;
    };

    window.BapxShopping = Object.freeze({ create(options) {
        const { container, origin, request, openLink, setStatus, resize, capabilities } = options;
        const canShop = options.canShop !== false;
        let products = [];
        let productTotal = 0;
        let cart = null;
        let cartToken = null;
        let inputArguments = null;
        let separateCart = null;
        // Validated access receipts awaiting a successful refresh contain no cart or payment facts.
        let pendingRecovery = { currentCart:null,buyNowCart:null,checkout:null };
        let checkoutContext = null;
        let checkoutForm = null;
        let busy = false;
        let stale = false;
        let closed = false;
        let epoch = 0;
        let activeArguments = null;
        let echoedInput = false;
        let contextSequence = 0;
        let contextQueue = null;
        let contextSending = false;
        let contextNotice = '';
        let contextElement = null;
        const idleWaiters = new Set();
        const checkoutContainer = node('div'); checkoutContainer.hidden = true;
        const hasTools = () => !!record(capabilities().serverTools);
        const hasLinks = () => !!record(capabilities().openLinks);
        const hasContext = () => !!record(capabilities().updateModelContext?.structuredContent);
        const isBusy = () => busy || !!checkoutForm?.busy();
        const live = () => !!cart && Date.parse(cart.expiresAt) > Date.now();
        const canChange = () => canShop && !isBusy() && !stale && hasTools() && !!cartToken && live() && cart.status === 'open' && !checkoutForm?.locks(cartToken);
        const setMessage = message => { if (!closed) { setStatus(message); resize(); } };
        function signalIdle(cancelled = false) {
            if (!cancelled && !closed && isBusy()) return;
            for (const waiter of idleWaiters) waiter.finish(!cancelled && !closed);
        }
        function whenIdle(maximum = 45000, signal) {
            if (closed || signal?.aborted) return Promise.resolve(false);
            if (!isBusy()) return Promise.resolve(true);
            if (maximum <= 0) return Promise.resolve(false);
            return new Promise(resolve => {
                const waiter = { timer:0,finish:value => {
                    clearTimeout(waiter.timer); idleWaiters.delete(waiter);
                    signal?.removeEventListener('abort',abort); resolve(value);
                } };
                const abort = () => waiter.finish(false);
                waiter.timer = setTimeout(() => waiter.finish(false),Math.min(maximum,45000));
                idleWaiters.add(waiter);
                signal?.addEventListener('abort',abort,{once:true});
            });
        }

        function publicUrl(path, supplied) {
            const expected = origin + path;
            if (supplied !== undefined && supplied !== expected) throw Error('Invalid public URL.');
            return expected;
        }
        function product(value) {
            if (!record(value) || !text(value.name, 160, false) || !text(value.description, 280)
                || !money(value.priceInr) || typeof value.available !== 'boolean') throw Error();
            const slug = productPath(value.path);
            if (!slug) throw Error();
            const images = value.imagePaths ?? (value.imagePath ? [value.imagePath] : []);
            if (!Array.isArray(images) || images.length > 3 || new Set(images).size !== images.length || !images.every(imagePath)) throw Error();
            if (value.imagePath !== undefined && (!imagePath(value.imagePath) || (images.length && images[0] !== value.imagePath))) throw Error();
            if (value.imageUrl !== undefined) publicUrl(value.imagePath, value.imageUrl);
            if (value.imageUrls !== undefined && (!Array.isArray(value.imageUrls) || value.imageUrls.length !== images.length
                || value.imageUrls.some((url, index) => url !== origin + images[index]))) throw Error();
            const highlights = value.highlights ?? [];
            const specifications = value.specifications ?? [];
            if (!Array.isArray(highlights) || highlights.length > 4 || !highlights.every(item => text(item, 160, false))
                || !Array.isArray(specifications) || specifications.length > 6
                || !specifications.every(item => record(item) && text(item.label, 80, false) && text(item.value, 240, false))) throw Error();
            return { name: value.name, description: value.description, price: value.priceInr, available: value.available,
                slug, url: publicUrl(value.path, value.productUrl), images: images.map(path => origin + path), highlights, specifications };
        }
        function cartData(value) {
            if (!record(value) || !['open', 'handed_off', 'checkout_locked'].includes(value.status) || !integer(value.revision)
                || !date(value.expiresAt) || value.currency !== 'INR' || !integer(value.itemCount, 1980)
                || !Array.isArray(value.items) || value.items.length > 20 || !Array.isArray(value.unavailableSlugs)
                || value.unavailableSlugs.length > 20 || !text(value.notice, 500) || typeof value.replayed !== 'boolean'
                || (value.estimatedTotalInr !== null && !money(value.estimatedTotalInr))) throw Error();
            if (value.cartToken !== undefined && !tokenPattern.test(value.cartToken)) throw Error();
            const items = value.items.map(item => {
                if (!record(item) || !text(item.name, 160, false) || typeof item.slug !== 'string' || !productPath(item.path) || productPath(item.path) !== item.slug
                    || !integer(item.quantity, 99) || item.quantity < 1 || !money(item.unitPriceInr, 1000000000)
                    || !money(item.lineTotalInr, 99000000000) || typeof item.available !== 'boolean'
                    || Math.abs(Math.round(item.unitPriceInr * item.quantity * 100) - Math.round(item.lineTotalInr * 100)) > 1) throw Error();
                if (item.imagePath !== undefined && !imagePath(item.imagePath)) throw Error();
                if (item.imageUrl !== undefined && item.imagePath === undefined) throw Error();
                return { slug: item.slug, name: item.name, quantity: item.quantity, price: item.unitPriceInr,
                    total: item.lineTotalInr, available: item.available, url: publicUrl(item.path, item.productUrl),
                    image: item.imagePath ? publicUrl(item.imagePath, item.imageUrl) : null };
            });
            if (new Set(items.map(item => item.slug)).size !== items.length
                || new Set(value.unavailableSlugs).size !== value.unavailableSlugs.length
                || !value.unavailableSlugs.every(slug => typeof slug === 'string' && [...slug].length <= 2039 && segmentPattern.test(slug))) throw Error();
            if (items.some(item => !item.available && !value.unavailableSlugs.includes(item.slug))
                || (value.unavailableSlugs.length > 0 && value.estimatedTotalInr !== null)) throw Error();
            const shownCount = items.reduce((sum, item) => sum + item.quantity, 0);
            if (shownCount > value.itemCount || (!value.unavailableSlugs.length && shownCount !== value.itemCount)) throw Error();
            if (!value.unavailableSlugs.length && (value.estimatedTotalInr === null
                || Math.abs(Math.round(items.reduce((sum, item) => sum + item.total, 0) * 100) - Math.round(value.estimatedTotalInr * 100)) > 1)) throw Error();
            let checkout = null;
            if (value.checkout !== undefined) {
                if (value.status !== 'checkout_locked' || !record(value.checkout)
                    || typeof value.checkout.attemptId !== 'string' || !/^[a-f0-9]{32}$/.test(value.checkout.attemptId)
                    || !paymentPattern.test(value.checkout.paymentToken) || value.checkout.paymentToken.slice(8,40) !== value.checkout.attemptId
                    || !date(value.checkout.statusExpiresAt)) throw Error();
                checkout = { attemptId:value.checkout.attemptId, paymentToken:value.checkout.paymentToken, statusExpiresAt:value.checkout.statusExpiresAt };
            }
            return { status: value.status, revision: value.revision, expiresAt: value.expiresAt, items,
                itemCount: value.itemCount, total: value.estimatedTotalInr, unavailable: [...value.unavailableSlugs], notice: value.notice,
                ...(checkout ? { checkout } : {}) };
        }
        function errorFor(result) {
            const code = result?._meta?.['in.bapx/shoppingError']?.code;
            const messages = {
                cart_conflict: 'The cart changed elsewhere. Refresh it and review the quantities before updating.',
                cart_expired: 'This cart expired. Start a new cart to continue.',
                cart_not_found: 'This cart is no longer accessible. Reopen it with the assistant or start a new cart.',
                cart_handed_off: 'This cart was handed to the website. Refresh it, or start a new cart.',
                checkout_locked: 'This cart has a payment attempt. Refresh the cart and check its payment status before starting another purchase.',
                product_unavailable: 'This product is currently unavailable. Refresh the cart before continuing.',
                cart_limit: 'This cart reached its limit. Remove an item or start a new cart.',
                rate_limited: 'Too many cart requests. Wait before trying again.',
                checkout_unavailable: 'Checkout needs a nonempty cart of available, priced products. Refresh and review this cart.',
                checkout_expired: 'This checkout status access expired. Contact the store if payment was made.',
                checkout_not_found: 'This checkout status receipt is unavailable. Reopen the correct receipt before checking again.',
                shop_unavailable: 'Shopping is currently unavailable. Try again later.'
            };
            const error = Error(messages[code] || 'The shopping action could not be completed. Review the chosen cart or payment receipt before trying again.');
            error.cartCode = code;
            return error;
        }
        function invalidate() {
            epoch++;
            if (busy && cart) stale = true;
            if (checkoutForm?.busy()) checkoutForm.cancel();
            busy = false;
            activeArguments = null;
            echoedInput = false;
            contextSequence++; contextQueue = null; contextNotice = '';
        }
        function kindForToken(token) { return separateCart?.token === token ? 'buy_now' : 'current'; }
        function recoverySnapshot() {
            const entry = (value, token) => value && token && Date.parse(value.expiresAt) > Date.now()
                ? { cartToken:token, revision:value.revision, expiresAt:value.expiresAt } : null;
            const pending = key => pendingRecovery[key] && Date.parse(pendingRecovery[key].expiresAt) > Date.now() ? { ...pendingRecovery[key] } : null;
            return { version:1, currentCart:entry(cart,cartToken) ?? pending('currentCart'),
                buyNowCart:entry(separateCart?.cart,separateCart?.token) ?? pending('buyNowCart'),
                checkout:checkoutForm?.recovery() ?? pending('checkout') };
        }
        function notifyRecovery() {
            if (closed || typeof options.onRecoveryState !== 'function') return;
            try { options.onRecoveryState(recoverySnapshot()); }
            catch (_) { /* Native recovery is optional; a blocked store cannot fail a cart action. */ }
        }
        function contextCart(value, token) {
            return { cartToken: token, revision: value.revision, expiresAt: value.expiresAt, status: value.status,
                currency: 'INR', itemCount: value.itemCount, estimatedTotalInr: value.total,
                unavailableSlugs: [...value.unavailable], items: value.items.map(item => ({
                    slug: item.slug, name: item.name, quantity: item.quantity, unitPriceInr: item.price,
                    lineTotalInr: item.total, available: item.available
                })) };
        }
        // Optional standard host context, separate from persistence. Never put the handle in text content.
        // Only one context request is in flight; a later successful action replaces the queued snapshot.
        function shareContext() {
            if (!hasContext() || closed) return;
            const current = cart && cartToken && !stale && live() ? contextCart(cart, cartToken) : null;
            const separate = separateCart && Date.parse(separateCart.cart.expiresAt) > Date.now() ? contextCart(separateCart.cart, separateCart.token) : null;
            const checkout = checkoutContext && Date.parse(checkoutContext.statusExpiresAt) > Date.now() ? { ...checkoutContext } : null;
            if (!current && !separate && !checkout) return;
            contextQueue = { sequence: ++contextSequence, epoch, payload: {
                shoppingContextVersion: 1, siteOrigin: origin, currentCart: current, separateBuyNowCart: separate,
                checkout
            } };
            contextNotice = 'pending';
            void sendContext();
        }
        async function sendContext() {
            if (contextSending || !contextQueue || closed) return;
            const entry = contextQueue; contextQueue = null; contextSending = true;
            try {
                const result = await request('ui/update-model-context', { structuredContent: entry.payload });
                if (result?.isError) throw Error();
                if (!closed && entry.sequence === contextSequence && entry.epoch === epoch) { contextNotice = 'shared'; showContextNotice(); }
            } catch (_) {
                if (!closed && entry.sequence === contextSequence && entry.epoch === epoch) { contextNotice = 'declined'; showContextNotice(); }
            } finally {
                contextSending = false;
                if (!closed) void sendContext();
            }
        }
        async function call(name, args, operationEpoch) {
            const kinds = { create_cart:'cart', get_cart:'cart', set_cart_quantity:'cart', review_checkout:'review', prepare_checkout_payment:'payment', get_checkout_status:'status' };
            if (!hasTools() || !Object.hasOwn(kinds,name) || (!canShop && name !== 'get_checkout_status') || closed || operationEpoch !== epoch) throw Error('The cart action was interrupted.');
            activeArguments = args;
            let result;
            try { result = await request('tools/call', { name, arguments: args }); }
            finally { if (activeArguments === args) { activeArguments = null; echoedInput = false; } }
            if (closed || operationEpoch !== epoch) throw Error('The cart action was interrupted.');
            if (result?.isError) throw errorFor(result);
            const expected = kinds[name];
            if (result?._meta?.['in.bapx/shoppingView'] !== expected || !record(result.structuredContent)) throw Error('The cart response could not be verified. Refresh before trying again.');
            return result.structuredContent;
        }
        async function run(action, affectsCurrent = true) {
            if (isBusy() || closed || !hasTools()) return;
            const operationEpoch = ++epoch;
            contextSequence++; contextQueue = null; contextNotice = '';
            busy = true;
            setMessage('Updating this cart…');
            draw();
            try {
                const message = await action(operationEpoch);
                if (closed || epoch !== operationEpoch) return;
                shareContext();
                if (message) setMessage(message);
            } catch (error) {
                if (closed || epoch !== operationEpoch) return;
                if (cart && affectsCurrent) stale = true;
                if (affectsCurrent && ['cart_expired', 'cart_not_found'].includes(error.cartCode)) cartToken = null;
                setMessage(error.cartCode ? error.message : 'The cart action could not be confirmed. Refresh this cart before trying again.');
            } finally {
                if (!closed && epoch === operationEpoch) { busy = false; notifyRecovery(); draw(); }
            }
        }
        async function createCart(operationEpoch) {
            const data = await call('create_cart', {}, operationEpoch);
            const created = cartData(data);
            if (!tokenPattern.test(data.cartToken) || created.status !== 'open' || created.items.length || created.itemCount) throw Error();
            return { cart: created, token: data.cartToken };
        }
        async function addProduct(item, operationEpoch) {
            if (!cartToken || !cart || !live() || cart.status !== 'open') {
                const created = await createCart(operationEpoch);
                cart = created.cart; cartToken = created.token; stale = false; pendingRecovery.currentCart = null;
            }
            if (stale) throw Error();
            const current = cart.items.find(line => line.slug === item.slug)?.quantity ?? 0;
            if (current >= 99) { return 'This product already has the maximum quantity of 99.'; }
            const data = await call('set_cart_quantity', { cartToken, slug: item.slug,
                quantity: current + 1, expectedRevision: cart.revision }, operationEpoch);
            cart = cartData(data); stale = false;
            checkoutForm?.cartChanged({ ...cart, cartToken, kind:'current' });
            return 'Added to this cart. Review the quantity and estimated total below.';
        }
        async function changeQuantity(slug, quantity, operationEpoch) {
            if (!cartToken || !cart || stale || !live()) throw Error();
            const data = await call('set_cart_quantity', { cartToken, slug, quantity,
                expectedRevision: cart.revision }, operationEpoch);
            cart = cartData(data); stale = false;
            checkoutForm?.cartChanged({ ...cart, cartToken, kind:'current' });
            return quantity === 0 ? 'Removed from this cart.' : 'Quantity updated. The total is an item-price estimate.';
        }
        function openCheckout(kind = 'current') {
            if (!canShop || isBusy() || !checkoutForm || !hasTools()) return false;
            const selected = kind === 'buy_now' ? separateCart : (cart && cartToken ? { cart,token:cartToken } : null);
            if (!selected || Date.parse(selected.cart.expiresAt) <= Date.now() || selected.cart.itemCount < 1
                || (selected.cart.status !== 'checkout_locked' && (selected.cart.unavailable.length || selected.cart.total <= 0))
                || (kind === 'current' && stale)) { setMessage('Add available products and refresh this cart before checkout.'); return false; }
            draw();
            return checkoutForm.open({ ...selected.cart,cartToken:selected.token,kind });
        }
        async function buyProduct(item, operationEpoch) {
            const created = await createCart(operationEpoch);
            // Retain the new handle immediately: a lost quantity response can be recovered without another create.
            separateCart = created; pendingRecovery.buyNowCart = null;
            const updated = cartData(await call('set_cart_quantity', { cartToken: created.token,
                slug: item.slug, quantity: 1, expectedRevision: created.cart.revision }, operationEpoch));
            separateCart = { cart:updated, token:created.token };
            draw();
            checkoutForm?.open({ ...updated,cartToken:created.token,kind:'buy_now' });
            return 'A separate one-product checkout is ready here. Your current cart is unchanged. Review delivery and totals before payment.';
        }
        function button(label, action, enabled = true, primary = false) {
            const element = node('button', label, primary ? 'mcp-shop__primary' : '');
            element.type = 'button'; element.disabled = !enabled || isBusy();
            element.addEventListener('click', () => { if (!element.disabled) action(); });
            return element;
        }
        function link(label, url) {
            const element = node('a', label, 'mcp-shop__link'); element.href = url;
            if (hasLinks()) element.addEventListener('click', event => { event.preventDefault(); void openLink(url); });
            else { element.target = '_blank'; element.rel = 'noopener noreferrer'; }
            return element;
        }
        function photo(url, title, className = 'mcp-shop__photo') {
            const image = node('img', undefined, className); image.src = url; image.alt = title;
            image.loading = 'lazy'; image.decoding = 'async'; image.referrerPolicy = 'no-referrer';
            image.addEventListener('error', () => { image.replaceWith(node('p', 'Product photo is unavailable.', 'mcp-shop__muted')); resize(); }, { once: true });
            return image;
        }
        function productCard(item) {
            const card = node('article', undefined, 'mcp-shop__card');
            card.append(node('h3', item.name));
            if (item.images.length) {
                card.append(photo(item.images[0], item.name));
                if (item.images.length > 1) {
                    const gallery = node('div', undefined, 'mcp-shop__gallery');
                    item.images.slice(1).forEach(url => gallery.append(photo(url, item.name, '')));
                    card.append(gallery);
                }
            } else card.append(node('p', 'No published product photo.', 'mcp-shop__muted'));
            card.append(node('p', currency(item.price), 'mcp-shop__price'), node('p', item.available ? 'Available' : 'Currently unavailable', 'mcp-shop__muted'));
            if (item.description) card.append(node('p', item.description));
            if (item.highlights.length) { const list = node('ul'); item.highlights.forEach(value => list.append(node('li', value))); card.append(list); }
            if (item.specifications.length) {
                const list = node('dl'); item.specifications.forEach(value => list.append(node('dt', value.label), node('dd', value.value))); card.append(list);
            }
            const actions = node('div', undefined, 'mcp-shop__actions');
            const canAdd = hasTools() && item.available && item.price <= 1000000000 && !stale
                && !pendingRecovery.currentCart
                && (!cart || (!!cartToken && live() && cart.status === 'open' && !checkoutForm?.locks(cartToken)));
            if (canShop) actions.append(button('Add to cart', () => run(step => addProduct(item, step)), canAdd, true),
                button('Buy now', () => run(step => buyProduct(item, step), false), !!checkoutForm && hasTools() && item.available && item.price > 0 && item.price <= 1000000000));
            actions.append(link('View product', item.url));
            card.append(actions);
            return card;
        }
        function quantityControls(item) {
            const form = node('form', undefined, 'mcp-shop__quantity');
            const label = node('label', 'Quantity ');
            const input = node('input'); input.type = 'number'; input.min = '0'; input.max = '99'; input.step = '1';
            input.value = String(item.quantity); input.required = true; input.disabled = !canChange();
            input.setAttribute('aria-label', 'Quantity for ' + item.name); label.append(input);
            // A host sandbox need not grant allow-forms. Never rely on native form submission.
            const applyQuantity = () => {
                const quantity = Number(input.value);
                if (!canChange() || input.value.trim() === '' || !integer(quantity, 99) || !form.reportValidity()) return;
                void run(step => changeQuantity(item.slug, quantity, step));
            };
            const update = button('Update quantity', applyQuantity, canChange());
            const remove = button('Remove', () => run(step => changeQuantity(item.slug, 0, step)), canChange());
            form.addEventListener('submit', event => { event.preventDefault(); applyQuantity(); });
            input.addEventListener('keydown', event => {
                if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); applyQuantity(); }
            });
            form.append(label, update, remove);
            return form;
        }
        function contextText() {
            if (typeof options.onRecoveryState === 'function') return 'This browser tab can retain cart and payment access when session storage is available. Delivery and coupon drafts are not saved; reopened access is checked with the store.';
            return !hasContext() ? 'This host does not share shopping updates with follow-up chat. Continue in this view; reopening another view does not restore it.'
                : contextNotice === 'pending' ? 'Sharing this cart with the next chat turn…'
                : contextNotice === 'shared' ? 'Cart details were shared for the next chat turn. This does not save the view across reloads.'
                : contextNotice === 'declined' ? 'Cart updated here. This host did not accept the chat context; follow-up chat may need the cart reopened.'
                : 'This host can share successful cart changes with follow-up chat.';
        }
        function showContextNotice() {
            if (closed || !contextElement) return;
            contextElement.textContent = contextText(); resize();
        }
        function draw() {
            if (closed) return;
            const root = node('div', undefined, 'mcp-shop'); root.setAttribute('aria-busy', String(isBusy()));
            if (products.length) {
                root.append(node('h2', products.length === 1 ? 'Published product' : 'Published products'));
                const grid = node('div', undefined, 'mcp-shop__products'); products.forEach(item => grid.append(productCard(item))); root.append(grid);
                if (productTotal > products.length) root.append(node('p', 'Showing ' + products.length + ' of ' + productTotal + ' matching products. Ask the assistant for the next selection.', 'mcp-shop__muted'));
            } else if (productTotal === 0 && !cart) root.append(node('p', 'Choose a published product to start shopping.'));
            const section = node('section', undefined, 'mcp-shop__cart'); section.setAttribute('aria-label', 'Anonymous shopping cart');
            section.append(node('h2', canShop ? 'Your cart' : 'Checkout status'));
            if (!canShop) section.append(node('p','Shopping is currently unavailable. A saved payment status receipt can still be checked here. Cart-only access cannot recover a missing payment receipt while shopping is disabled.','mcp-shop__muted'),link('Contact the store',origin + '/contact'));
            if (Object.values(pendingRecovery).some(value => value && Date.parse(value.expiresAt) > Date.now())) {
                section.append(node('p','Saved access is retained, but some information could not be verified. Retry before continuing that cart or payment.','mcp-shop__muted'),
                    button('Retry saved access',() => { void api.restore(recoverySnapshot()); },hasTools() && (canShop || !!recoverySnapshot().checkout)));
            }
            if (!hasTools()) section.append(node('p', 'This chat host cannot run shopping controls. Use the product links to shop on the website.', 'mcp-shop__muted'));
            else if (!hasLinks()) section.append(node('p', 'Cart and checkout review are available here. This host cannot open a separate payment page.', 'mcp-shop__muted'));
            if (canShop && cart) {
                section.append(node('p', cart.itemCount + ' item(s) · ' + (cart.status === 'handed_off' ? 'Handed to website'
                    : cart.status === 'checkout_locked' || checkoutForm?.locks(cartToken) ? 'Checkout locked for payment' : 'Anonymous cart'), 'mcp-shop__muted'));
                cart.items.forEach(item => {
                    const row = node('article', undefined, 'mcp-shop__line'); row.append(node('h3', item.name));
                    if (item.image) row.append(photo(item.image, item.name));
                    row.append(node('p', item.available ? currency(item.price) + ' each · ' + currency(item.total) : 'Currently unavailable', 'mcp-shop__price'), quantityControls(item));
                    section.append(row);
                });
                cart.unavailable.filter(slug => !cart.items.some(item => item.slug === slug)).forEach(slug => {
                    const row = node('article', undefined, 'mcp-shop__line');
                    row.append(node('h3', 'Unavailable selection'), node('p', [...slug].slice(0, 80).join(''), 'mcp-shop__muted'),
                        button('Remove unavailable selection', () => run(step => changeQuantity(slug, 0, step)), canChange()));
                    section.append(row);
                });
                section.append(node('p', cart.total === null ? 'Item total unavailable until selections are resolved.' : 'Estimated item total: ' + currency(cart.total), 'mcp-shop__price'), node('p', cart.notice, 'mcp-shop__muted'));
                if (!live()) section.append(node('p', 'This cart expired. Start a new cart.'));
                else if (!cartToken) section.append(node('p', 'This view can show the cart but cannot edit it. Ask the assistant to reopen this cart before editing.'));
                else if (stale) section.append(node('p', 'Refresh this cart and review it before another change.'));
                const actions = node('div', undefined, 'mcp-shop__actions');
                actions.append(button('Refresh cart', () => run(async step => {
                    cart = cartData(await call('get_cart', { cartToken }, step)); stale = false;
                    checkoutForm?.cartChanged({ ...cart,cartToken,kind:'current' });
                    return 'Cart refreshed. Review current quantities, prices and availability.';
                }), hasTools() && !!cartToken && live()), button('Checkout', () => openCheckout(),
                    !!checkoutForm && hasTools() && !!cartToken && live() && !stale && cart.itemCount > 0
                    && (cart.status === 'checkout_locked' || (cart.status === 'open' && cart.unavailable.length === 0 && cart.total > 0)), true));
                section.append(actions);
            } else if (canShop) section.append(node('p', 'Add a product to create a cart. Buy now creates a separate one-product cart.'));
            if (canShop && hasTools()) section.append(button('Start a new cart', () => run(async step => {
                const created = await createCart(step); cart = created.cart; cartToken = created.token; stale = false; pendingRecovery.currentCart = null;
                return 'A new empty cart is ready in this view.';
            }, false)));
            if (canShop) section.append(node('p', 'Cart access lasts up to 24 hours. Checkout details and totals are reviewed here. Each cart remains separate; a new chat view needs its own supplied access.', 'mcp-shop__muted'));
            contextElement = node('p', contextText(), 'mcp-shop__muted'); section.append(contextElement);
            if (canShop && separateCart) section.append(button('Refresh separate Buy now cart',() => run(async step => {
                const token = separateCart.token;
                separateCart = { cart:cartData(await call('get_cart',{ cartToken:token },step)),token };
                checkoutForm?.cartChanged({ ...separateCart.cart,cartToken:token,kind:'buy_now' });
                return 'Separate Buy now cart refreshed. Review its current checkout before payment.';
            },false),hasTools() && Date.parse(separateCart.cart.expiresAt) > Date.now()),
                button('Open separate Buy now checkout',() => openCheckout('buy_now'),!!checkoutForm && hasTools()));
            root.append(section,checkoutContainer); container.replaceChildren(root); resize(); signalIdle();
        }
        checkoutForm = window.BapxCheckout?.create({ container:checkoutContainer,origin,deliveryStates:options.deliveryStates,canShop,
            callTool:(name,args) => call(name,args,epoch), openExternal:openLink,capabilities,validateCart:cartData,resize,
            onCartChange:(kind,value,token) => {
                if (kind === 'buy_now') { separateCart = { cart:value,token }; pendingRecovery.buyNowCart = null; }
                else { cart = value; cartToken = token; stale = false; pendingRecovery.currentCart = null; }
                notifyRecovery();
            },
            onBusyChange:draw,
            onConflict:kind => { if (kind !== 'buy_now' && cart) stale = true; },
            onRecoveryChange:notifyRecovery,
            onCheckoutContext:(value,token) => {
                checkoutContext = { ...value };
                // Preparing a durable attempt authoritatively locks exactly that cart.
                if (token && value.attemptId && value.stage) {
                    const recovery = { attemptId:value.attemptId,paymentToken:value.paymentToken,statusExpiresAt:value.statusExpiresAt };
                    if (token === cartToken && cart) cart = { ...cart,status:'checkout_locked',checkout:recovery };
                    else if (token === separateCart?.token) separateCart.cart = { ...separateCart.cart,status:'checkout_locked',checkout:recovery };
                }
                shareContext();
            }
        }) ?? null;
        function safeInput(args) {
            if (!record(args)) return null;
            const value = {};
            if (typeof args.cartToken === 'string' && tokenPattern.test(args.cartToken)) value.cartToken = args.cartToken;
            if (typeof args.paymentToken === 'string' && paymentPattern.test(args.paymentToken)) value.paymentToken = args.paymentToken;
            if (integer(args.expectedRevision)) value.expectedRevision = args.expectedRevision;
            return value;
        }
        function clearAll() {
            inputArguments = null; cartToken = null; cart = null; separateCart = null; checkoutContext = null;
            pendingRecovery = { currentCart:null,buyNowCart:null,checkout:null };
            products = []; productTotal = 0; checkoutForm?.cancel();
        }
        function recoveryEntry(value, payment = false) {
            if (value === null) return null;
            const keys = payment ? ['paymentToken','expiresAt','kind'] : ['cartToken','revision','expiresAt'];
            if (!record(value) || Object.keys(value).length !== keys.length || Object.keys(value).some(key => !keys.includes(key))
                || !date(value.expiresAt) || Date.parse(value.expiresAt) > Date.now() + (payment ? 7 * 86400000 : 86400000) + 300000
                || (payment ? !paymentPattern.test(value.paymentToken) || !['current','buy_now'].includes(value.kind)
                    : !tokenPattern.test(value.cartToken) || !integer(value.revision))) throw Error();
            return Date.parse(value.expiresAt) > Date.now() ? { ...value } : null;
        }
        const api = Object.freeze({
            open() { if (closed) return false; draw(); return true; },
            busy:isBusy,
            checkout:() => openCheckout(),
            productAction(slug,action) {
                if (!canShop || closed || isBusy() || !hasTools() || !['add','buy_now'].includes(action)) return false;
                const item = products.find(value => value.slug === slug);
                if (!item || !item.available || item.price > 1000000000) return false;
                if (action === 'add') {
                    if (stale || pendingRecovery.currentCart || (cart && (!cartToken || !live() || cart.status !== 'open' || checkoutForm?.locks(cartToken)))) return false;
                    void run(step => addProduct(item,step));
                } else {
                    if (!checkoutForm || item.price <= 0) return false;
                    void run(step => buyProduct(item,step),false);
                }
                return true;
            },
            onToolInput(params) {
                const args = params?.arguments;
                if (activeArguments && sameArguments(args, activeArguments)) { echoedInput = true; return; }
                invalidate();
                inputArguments = safeInput(args);
            },
            render(result) {
                if (closed) return false;
                const kind = result?._meta?.['in.bapx/shoppingView'];
                if (echoedInput && activeArguments) return true; // The direct tools/call response owns this user action.
                if (result?.isError && result?._meta?.['in.bapx/shoppingError']) {
                    const failedInput = inputArguments;
                    invalidate(); inputArguments = null;
                    const error = errorFor(result);
                    if (failedInput?.cartToken && failedInput.cartToken === cartToken) {
                        if (cart) stale = true;
                        if (['cart_expired','cart_not_found'].includes(error.cartCode)) cartToken = null;
                    }
                    if (failedInput?.cartToken === separateCart?.token && ['cart_expired','cart_not_found'].includes(error.cartCode)) separateCart = null;
                    if (!checkoutForm?.toolError(error,failedInput)) setMessage(error.message);
                    notifyRecovery(); draw(); return true;
                }
                if (!['products', 'cart', 'review', 'payment', 'status'].includes(kind)) return false;
                // Validate before touching an existing cart, local form or recovery receipt.
                // The caller may reject a forged/unsupported result while keeping its current UI.
                let parsedProducts;
                let parsedCart;
                try {
                    const data = result.structuredContent;
                    if (result.isError || !record(data)) return false;
                    if (kind === 'products') {
                        if (!Array.isArray(data.products) || data.products.length > 20 || !integer(data.total,Number.MAX_SAFE_INTEGER)) return false;
                        parsedProducts = data.products.map(product);
                    } else if (kind === 'cart') parsedCart = cartData(data);
                    else if (!checkoutForm?.validResult(kind,data,inputArguments)) return false;
                } catch (_) { return false; }
                invalidate();
                try {
                    const data = result.structuredContent;
                    if (result.isError || !record(data)) throw Error();
                    if (kind === 'products') {
                        products = parsedProducts.slice(0, 4); productTotal = data.total;
                    } else if (kind === 'cart') {
                        const parsed = parsedCart;
                        const token = typeof data.cartToken === 'string' && tokenPattern.test(data.cartToken) ? data.cartToken : inputArguments?.cartToken ?? null;
                        const cartKind = kindForToken(token);
                        if (cartKind === 'buy_now') { separateCart = { cart:parsed,token }; pendingRecovery.buyNowCart = null; }
                        else { cart = parsed; cartToken = token; stale = false; pendingRecovery.currentCart = null; }
                        checkoutForm?.cartChanged({ ...parsed,cartToken:token,kind:cartKind });
                    } else {
                        const cartKind = kindForToken(inputArguments?.cartToken);
                        if (!checkoutForm?.renderResult(kind,data,inputArguments,cartKind)) throw Error();
                    }
                    inputArguments = null; setMessage(''); notifyRecovery(); draw(); return true;
                } catch (_) {
                    clearAll();
                    return false;
                }
            },
            async restore(snapshot) {
                if (closed || isBusy() || !hasTools()) return false;
                let saved;
                try {
                    if (!record(snapshot) || snapshot.version !== 1 || Object.keys(snapshot).length !== 4
                        || Object.keys(snapshot).some(key => !['version','currentCart','buyNowCart','checkout'].includes(key))) throw Error();
                    saved = { currentCart:recoveryEntry(snapshot.currentCart), buyNowCart:recoveryEntry(snapshot.buyNowCart), checkout:recoveryEntry(snapshot.checkout,true) };
                    if (saved.currentCart && saved.buyNowCart?.cartToken === saved.currentCart.cartToken) throw Error();
                } catch (_) { setMessage('Saved shopping access could not be restored. Reopen the chosen cart from the assistant.'); return false; }
                invalidate(); clearAll(); busy = true;
                pendingRecovery = { ...saved };
                const operationEpoch = epoch;
                setMessage('Refreshing saved shopping access from the store…'); draw();
                try {
                    for (const kind of canShop ? ['currentCart','buyNowCart'] : []) {
                        const entry = saved[kind]; if (!entry) continue;
                        try {
                            const value = cartData(await call('get_cart',{ cartToken:entry.cartToken },operationEpoch));
                            if (kind === 'currentCart') { cart = value; cartToken = entry.cartToken; stale = false; }
                            else separateCart = { cart:value,token:entry.cartToken };
                            pendingRecovery[kind] = null;
                            if (!saved.checkout && value.checkout) saved.checkout = { paymentToken:value.checkout.paymentToken,
                                expiresAt:value.checkout.statusExpiresAt,kind:kind === 'currentCart' ? 'current' : 'buy_now' };
                        } catch (error) {
                            if (closed || operationEpoch !== epoch) return false;
                            if (['cart_expired','cart_not_found','invalid_capability'].includes(error.cartCode)) pendingRecovery[kind] = null;
                        }
                    }
                    if (closed || operationEpoch !== epoch) return false;
                    if (saved.checkout && checkoutForm) {
                        pendingRecovery.checkout = saved.checkout;
                        const verified = await checkoutForm.restore(saved.checkout);
                        if (verified || !checkoutForm.recovery()) pendingRecovery.checkout = null;
                    }
                    if (closed || operationEpoch !== epoch) return false;
                    const pending = Object.values(pendingRecovery).some(value => value !== null);
                    setMessage(pending ? 'Some saved access could not be verified. It is retained for retry; no unverified cart or payment details are shown.'
                        : cart || separateCart || checkoutForm?.recovery() ? 'Saved access was checked with the store. Review the current state before continuing.'
                        : 'Saved shopping access is no longer available. Start a new cart or reopen an existing receipt.');
                    return !!(cart || separateCart || checkoutForm?.recovery());
                } finally {
                    if (!closed && operationEpoch === epoch) { busy = false; notifyRecovery(); draw(); }
                }
            },
            async acceptToolResult(name,args,result,syncOptions = {}) {
                const signal = syncOptions.signal;
                if (signal?.aborted) return false;
                const kinds = { create_cart:'cart',get_cart:'cart',set_cart_quantity:'cart',review_checkout:'review',prepare_checkout_payment:'payment',get_checkout_status:'status' };
                if (!Object.hasOwn(kinds,name) || (!canShop && name !== 'get_checkout_status')
                    || (!result?.isError && result?._meta?.['in.bapx/shoppingView'] !== kinds[name])) return false;
                if (name !== 'create_cart' && name !== 'get_checkout_status' && !tokenPattern.test(args?.cartToken)) return false;
                if (name === 'get_checkout_status' && !paymentPattern.test(args?.paymentToken)) return false;
                if (!result?.isError && result?.structuredContent?.cartToken !== undefined && name !== 'create_cart'
                    && result.structuredContent.cartToken !== args.cartToken) return false;
                if (!result?.isError && name === 'prepare_checkout_payment' && typeof args?.quoteId === 'string'
                    && result?.structuredContent?.quoteId !== args.quoteId) return false;
                const deadline = Date.now() + 45000;
                while (isBusy()) { if (!await whenIdle(deadline - Date.now(),signal) || closed || signal?.aborted) return false; }
                if (closed || signal?.aborted) return false;
                const input = safeInput(args);
                if (!result?.isError) {
                    try {
                        if (kinds[name] === 'cart') cartData(result.structuredContent);
                        else if (!checkoutForm?.validResult(kinds[name],result.structuredContent,input)) return false;
                    } catch (_) { return false; }
                }
                const known = input?.cartToken === cartToken ? cart : (input?.cartToken === separateCart?.token ? separateCart.cart : null);
                const revision = kinds[name] === 'cart' ? result?.structuredContent?.revision : kinds[name] === 'review' ? result?.structuredContent?.cartRevision : null;
                if (!result?.isError && known && integer(revision) && revision < known.revision) return true;
                if (signal?.aborted) return false;
                api.onToolInput({ arguments:input ?? {} });
                return api.render(result);
            },
            whenIdle,
            cancel() { invalidate(); clearAll(); signalIdle(true); },
            teardown() { closed = true; invalidate(); clearAll(); checkoutForm?.teardown(); signalIdle(true); }
        });
        return api;
    } });
})();
