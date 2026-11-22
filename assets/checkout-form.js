/* Trusted native checkout form, shared by Support and the MCP Apps resource.
 * Fields are local until an explicit Review action. No arbitrary A2UI action,
 * provider iframe, external script, raw payment credential or model-generated HTML.
 * https://apps.extensions.modelcontextprotocol.io/api/documents/overview.html
 */
(() => {
    'use strict';
    const cartPattern = /^cart_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    const paymentPattern = /^payment_[a-f0-9]{32}\.[a-f0-9]{64}$/;
    const quotePattern = /^quote_[a-f0-9]{32}$/;
    const record = value => value && typeof value === 'object' && !Array.isArray(value);
    const integer = value => Number.isSafeInteger(value) && value >= 0 && value <= 200000000000000;
    const date = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/.test(value) && Number.isFinite(Date.parse(value));
    const text = (value, maximum, empty = true) => typeof value === 'string' && (empty || value.length > 0)
        && [...value].length <= maximum && !/[\u0000-\u001f\u007f]/u.test(value);
    const money = value => new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR' }).format(value / 100);
    const fields = [
        ['name','Full name','text',120,'shipping name'],
        ['email','Email','email',254,'shipping email'],
        ['phone','Phone','tel',30,'shipping tel'],
        ['address','Delivery address','textarea',500,'shipping street-address'],
        ['city','City','text',100,'shipping address-level2'],
        ['pincode','PIN code','text',6,'shipping postal-code'],
        ['state','State or union territory','select',80,'shipping address-level1'],
        ['gstin','GSTIN (optional)','text',15,'off']
    ];
    const blank = () => Object.fromEntries([...fields.map(([key]) => [key,'']),['couponCode','']]);
    const node = (tag, value, className) => {
        const element = document.createElement(tag);
        if (value !== undefined) element.textContent = value;
        if (className) element.className = className;
        return element;
    };

    window.BapxCheckout = Object.freeze({ create(options) {
        const { container, origin, callTool, openExternal, capabilities, validateCart, onCartChange,
            onBusyChange, onCheckoutContext, onRecoveryChange, onConflict, resize } = options;
        const canShop = options.canShop !== false;
        const states = Array.isArray(options.deliveryStates) && options.deliveryStates.length <= 50
            && options.deliveryStates.every(value => text(value,80,false))
            && new Set(options.deliveryStates).size === options.deliveryStates.length ? [...options.deliveryStates] : [];
        let target = null;
        let draft = blank();
        let review = null;
        let attempt = null;
        let verified = null;
        let receipt = null;
        let editing = true;
        let dirty = false;
        let couponDirty = false;
        let visible = false;
        let busy = false;
        let closed = false;
        let epoch = 0;
        let message = '';
        let expiryTimer = 0;
        let form = null;
        const hasTools = () => !!record(capabilities().serverTools);
        const hasLinks = () => !!record(capabilities().openLinks);
        const active = () => target && cartPattern.test(target.cartToken);
        const currentQuote = () => review?.quote && !dirty && Date.parse(review.quote.expiresAt) > Date.now();
        const locked = () => !!attempt || (review && !['details_required','review_ready'].includes(review.stage));
        const recovery = () => receipt ? { paymentToken:receipt.paymentToken, expiresAt:receipt.expiresAt, kind:receipt.kind } : null;
        const notifyRecovery = () => onRecoveryChange?.(recovery());
        const setMessage = value => { message = value; if (!closed) draw(); };

        function checkedReceipt(value, kind) {
            if (!record(value) || !paymentPattern.test(value.paymentToken) || !date(value.statusExpiresAt)
                || !['current','buy_now'].includes(kind)) throw Error();
            return { paymentToken:value.paymentToken, expiresAt:value.statusExpiresAt, kind };
        }
        function checkedReview(value) {
            if (!record(value) || !['details_required','review_ready','payment_preparing','payment_ready','payment_uncertain','completed'].includes(value.stage)
                || !integer(value.cartRevision) || typeof value.deliveryComplete !== 'boolean'
                || !Array.isArray(value.missingFields) || value.missingFields.length > 8
                || !value.missingFields.every(key => fields.some(([name]) => name === key))
                || (value.deliveryComplete && value.missingFields.length) || typeof value.couponApplied !== 'boolean'
                || (value.deliveryRegion !== null && !states.includes(value.deliveryRegion))
                || !text(value.notice,500) || !record(value.payment) || value.payment.provider !== 'razorpay'
                || typeof value.payment.available !== 'boolean' || !['test','live','unavailable'].includes(value.payment.mode)
                || !['external_provider','unavailable'].includes(value.payment.presentation)) throw Error();
            const cart = validateCart(value.cart);
            if (cart.revision !== value.cartRevision) throw Error();
            const totals = value.totals;
            if (!record(totals) || totals.currency !== 'INR' || totals.taxIncluded !== true
                || !['itemsPaise','discountPaise','shippingPaise','totalPaise'].every(key => integer(totals[key]))
                || totals.discountPaise > totals.itemsPaise
                || totals.itemsPaise - totals.discountPaise + totals.shippingPaise !== totals.totalPaise) throw Error();
            if (totals.tax !== null && (!record(totals.tax)
                || !['gstPaise','cgstPaise','sgstPaise','igstPaise'].every(key => integer(totals.tax[key]))
                || Math.abs(totals.tax.cgstPaise + totals.tax.sgstPaise + totals.tax.igstPaise - totals.tax.gstPaise) > 1)) throw Error();
            if (value.quote !== null && (!record(value.quote) || !quotePattern.test(value.quote.id) || !date(value.quote.expiresAt))) throw Error();
            return { stage:value.stage, cartRevision:value.cartRevision, cart, deliveryComplete:value.deliveryComplete,
                missingFields:[...value.missingFields], deliveryRegion:value.deliveryRegion, couponApplied:value.couponApplied,
                totals:{ currency:'INR', itemsPaise:totals.itemsPaise, discountPaise:totals.discountPaise,
                    shippingPaise:totals.shippingPaise, totalPaise:totals.totalPaise, tax:totals.tax ? { ...totals.tax } : null },
                quote:value.quote ? { id:value.quote.id, expiresAt:value.quote.expiresAt } : null,
                payment:{ available:value.payment.available, provider:'razorpay',mode:value.payment.mode,presentation:value.payment.presentation }, notice:value.notice };
        }
        function checkedAttempt(value) {
            if (!record(value) || !['payment_preparing','payment_ready','payment_uncertain','completed'].includes(value.stage)
                || !quotePattern.test(value.quoteId) || typeof value.attemptId !== 'string' || !/^[a-f0-9]{32}$/.test(value.attemptId)
                || !paymentPattern.test(value.paymentToken) || value.paymentToken.slice(8,40) !== value.attemptId
                || !date(value.statusExpiresAt) || value.provider !== 'razorpay'
                || !['test','live','unavailable'].includes(value.mode) || !integer(value.amountPaise) || value.currency !== 'INR'
                || !text(value.notice,500)) throw Error();
            let url = null;
            if (value.paymentUrl !== undefined) {
                if (typeof value.paymentUrl !== 'string' || !date(value.paymentExpiresAt)) throw Error();
                const parsed = new URL(value.paymentUrl);
                if (parsed.origin !== origin || parsed.username || parsed.password || parsed.pathname !== '/checkout/payment'
                    || parsed.hash || !/^\?ticket=pay_[a-f0-9]{32}\.[a-f0-9]{64}$/.test(parsed.search)
                    || parsed.search.slice(12,44) !== value.attemptId) throw Error();
                url = parsed.href;
            }
            return { stage:value.stage, quoteId:value.quoteId, attemptId:value.attemptId, paymentToken:value.paymentToken,
                statusExpiresAt:value.statusExpiresAt, amountPaise:value.amountPaise, currency:'INR', mode:value.mode,
                paymentUrl:url, paymentExpiresAt:url ? value.paymentExpiresAt : null, notice:value.notice };
        }
        function checkedStatus(value) {
            if (!record(value) || !['pending','authorized','captured','failed','expired','cancelled','unknown'].includes(value.paymentStatus)
                || !['not_created','saved','save_pending'].includes(value.orderStatus) || !integer(value.amountPaise)
                || value.currency !== 'INR' || !date(value.checkedAt) || !date(value.statusExpiresAt) || !text(value.notice,500)
                || (value.orderReference !== undefined && (!text(value.orderReference,160,false) || value.orderStatus !== 'saved'))
                || (value.orderStatus === 'saved' && (value.paymentStatus !== 'captured' || !value.orderReference))) throw Error();
            return { paymentStatus:value.paymentStatus, orderStatus:value.orderStatus, amountPaise:value.amountPaise,
                currency:'INR', checkedAt:value.checkedAt, statusExpiresAt:value.statusExpiresAt,
                ...(value.orderReference ? { orderReference:value.orderReference } : {}), notice:value.notice };
        }
        function publishCheckout() {
            if (!receipt) return;
            const data = { paymentToken:receipt.paymentToken, statusExpiresAt:receipt.expiresAt, kind:receipt.kind };
            if (attempt) Object.assign(data, { attemptId:attempt.attemptId, quoteId:attempt.quoteId,
                stage:attempt.stage, amountPaise:attempt.amountPaise, currency:'INR' });
            if (verified) Object.assign(data, { paymentStatus:verified.paymentStatus, orderStatus:verified.orderStatus,
                amountPaise:verified.amountPaise, currency:'INR', checkedAt:verified.checkedAt,
                ...(verified.orderReference ? { orderReference:verified.orderReference } : {}) });
            onCheckoutContext?.(data,target?.cartToken ?? null);
        }
        function acceptReview(data) {
            review = checkedReview(data);
            if (target) {
                target.revision = review.cartRevision;
                target.expiresAt = review.cart.expiresAt;
                onCartChange?.(target.kind,review.cart,target.cartToken);
                if (review.cart.checkout) receipt = checkedReceipt(review.cart.checkout,target.kind);
            }
            dirty = false; couponDirty = false; editing = !review.deliveryComplete;
            notifyRecovery();
        }
        function errorMessage(error) {
            const code = error.cartCode;
            if (['cart_conflict','quote_expired','quote_changed','invalid_quote'].includes(code)) {
                review = null;
                if (code === 'cart_conflict') onConflict?.(target?.kind);
                return 'This checkout changed or its quote expired. Refresh the cart, then review the current details and totals again.';
            }
            if (['checkout_locked','cart_checkout_locked','payment_in_progress'].includes(code)) {
                review = null;
                return 'A payment attempt already exists. Refresh the cart to recover its payment status; do not create another purchase.';
            }
            if (['cart_expired','cart_not_found','payment_expired','payment_not_found','status_expired','checkout_expired','checkout_not_found','invalid_capability'].includes(code)) {
                review = null;
                if (['payment_expired','payment_not_found','status_expired','checkout_expired','checkout_not_found','invalid_capability'].includes(code)) receipt = null;
                return 'This checkout access expired or is unavailable. Keep any existing payment receipt and contact the store if payment was made.';
            }
            if (code === 'rate_limited') return 'Too many checkout requests. Wait before checking again.';
            if (['invalid_delivery','invalid_arguments','invalid_coupon','coupon_invalid','coupon_unavailable'].includes(code)) {
                return 'Check the delivery fields and coupon, then choose Review checkout again. No new payment was confirmed.';
            }
            if (['payment_uncertain','provider_uncertain','payment_unavailable','provider_unavailable'].includes(code)) {
                review = null;
                return 'Payment preparation could not be confirmed. Refresh this cart to recover any existing attempt, then check its status. Do not start another payment.';
            }
            return 'This checkout action could not be confirmed. Refresh the cart or check an existing payment status before trying another payment.';
        }
        async function run(action, pendingMessage) {
            if (busy || closed || !hasTools()) return;
            const operation = ++epoch;
            busy = true; message = pendingMessage;
            onBusyChange?.(); draw();
            try {
                await action(() => !closed && operation === epoch);
            } catch (error) {
                if (!closed && operation === epoch) message = errorMessage(error);
            } finally {
                if (!closed && operation === epoch) { busy = false; notifyRecovery(); draw(); onBusyChange?.(); }
            }
        }
        function reviewNow() {
            if (!canShop || !active() || locked()) return;
            const args = { cartToken:target.cartToken, expectedRevision:target.revision };
            if (editing) {
                if (!form?.reportValidity()) return;
                args.delivery = Object.fromEntries(fields.map(([key]) => [key,draft[key].trim()]));
            }
            if (couponDirty) args.couponCode = draft.couponCode.trim();
            void run(async current => {
                const data = await callTool('review_checkout',args);
                if (!current()) return;
                acceptReview(data);
                message = review.deliveryComplete ? 'Review the delivery region and exact checkout total before confirming payment.' : 'Complete the required delivery details, then review this checkout.';
            },'Reviewing delivery and current checkout totals…');
        }
        function prepareNow() {
            if (!canShop || !active() || !currentQuote() || locked() || !review.deliveryComplete
                || !review.payment.available || review.totals.totalPaise <= 0) return;
            const args = { cartToken:target.cartToken, expectedRevision:review.cartRevision, quoteId:review.quote.id, confirm:true };
            const amount = review.totals.totalPaise;
            void run(async current => {
                const data = checkedAttempt(await callTool('prepare_checkout_payment',args));
                if (!current()) return;
                if (data.quoteId !== args.quoteId || data.amountPaise !== amount) throw Error();
                attempt = data; verified = null; receipt = checkedReceipt(data,target.kind);
                draft = blank(); editing = false; dirty = false; couponDirty = false;
                publishCheckout();
                message = data.stage === 'payment_ready'
                    ? 'Payment is prepared. Use the separate payment button when ready; no payment or order success has been confirmed.'
                    : 'The payment attempt is recorded. Check its status before any further payment action.';
            },'Preparing the exact confirmed checkout…');
        }
        function refreshStatus(publish = true) {
            if (!receipt || Date.parse(receipt.expiresAt) <= Date.now()) return Promise.resolve(false);
            const token = receipt.paymentToken;
            return run(async current => {
                const data = checkedStatus(await callTool('get_checkout_status',{ paymentToken:token }));
                if (!current()) return;
                verified = data; receipt.expiresAt = data.statusExpiresAt;
                if (publish) publishCheckout();
                message = data.paymentStatus === 'captured'
                    ? (data.orderStatus === 'saved' ? 'Payment is verified and the order is saved.' : 'Payment is verified. The order is still being saved; check status again before placing another order.')
                    : data.paymentStatus === 'authorized' ? 'Payment is authorized but not yet captured. The order is not confirmed.'
                    : 'The current payment and order status is shown below.';
            },'Verifying payment and saved order status…');
        }
        function button(label, action, enabled = true, primary = false) {
            const element = node('button',label,primary ? 'mcp-shop__primary' : '');
            element.type = 'button'; element.disabled = busy || !enabled;
            element.addEventListener('click',() => { if (!element.disabled) action(); });
            return element;
        }
        function deliveryForm() {
            form = node('form',undefined,'bapx-checkout__form');
            form.noValidate = false;
            form.addEventListener('submit',event => { event.preventDefault(); reviewNow(); });
            const grid = node('div',undefined,'bapx-checkout__fields');
            fields.forEach(([key,label,type,maximum,autocomplete]) => {
                const wrapper = node('label',undefined,'bapx-checkout__field' + (key === 'address' ? ' bapx-checkout__field--wide' : ''));
                wrapper.append(node('span',label));
                const input = node(type === 'textarea' ? 'textarea' : type === 'select' ? 'select' : 'input');
                if (type !== 'textarea' && type !== 'select') input.type = type;
                if (type === 'select') {
                    const empty = node('option','Select a state'); empty.value = ''; input.append(empty);
                    states.forEach(state => { const option = node('option',state); option.value = state; input.append(option); });
                } else input.maxLength = maximum;
                input.name = key; input.autocomplete = autocomplete; input.required = key !== 'gstin';
                input.value = draft[key]; input.disabled = busy || !active() || !hasTools();
                if (key === 'pincode') { input.inputMode = 'numeric'; input.pattern = '[0-9]{6}'; }
                if (key === 'gstin') { input.pattern = '[A-Z0-9]{15}'; input.autocapitalize = 'characters'; }
                input.addEventListener('input',() => {
                    draft[key] = input.value; dirty = true;
                    const confirmation = container.querySelector('[data-checkout-confirm]'); if (confirmation) confirmation.disabled = true;
                });
                input.addEventListener('keydown',event => {
                    if (event.key === 'Enter' && !event.isComposing && type !== 'textarea' && type !== 'select') { event.preventDefault(); reviewNow(); }
                });
                wrapper.append(input); grid.append(wrapper);
            });
            form.append(grid);
            return form;
        }
        function totalsView(value) {
            const list = node('dl',undefined,'bapx-checkout__totals');
            [['Items',value.itemsPaise],['Discount',-value.discountPaise],['Delivery',value.shippingPaise],['Total including applicable GST',value.totalPaise]].forEach(([label,amount]) => {
                list.append(node('dt',label),node('dd',money(amount)));
            });
            if (value.tax) list.append(node('dt','GST included in the total'),node('dd',money(value.tax.gstPaise)));
            return list;
        }
        function draw() {
            clearTimeout(expiryTimer); expiryTimer = 0;
            container.hidden = !visible || closed;
            if (!visible || closed) { container.replaceChildren(); return; }
            const root = node('section',undefined,'bapx-checkout');
            root.setAttribute('aria-label',target?.kind === 'buy_now' || receipt?.kind === 'buy_now' ? 'Buy now checkout review' : 'Checkout review');
            root.setAttribute('aria-busy',String(busy));
            root.append(node('h2',target?.kind === 'buy_now' || receipt?.kind === 'buy_now' ? 'Buy now checkout' : 'Checkout'));
            if (message) { const status = node('p',message,'bapx-checkout__status'); status.setAttribute('role','status'); root.append(status); }
            if (!hasTools()) root.append(node('p','This host cannot submit checkout fields or verify payment. The chat answer retains the current information.','mcp-shop__muted'));
            if (canShop && !receipt && !locked()) {
                root.append(node('p','Delivery and contact fields stay in this form until you choose Review checkout. Payment details are entered only with the payment provider.','mcp-shop__muted'));
                if (editing) root.append(deliveryForm());
                else if (review?.deliveryComplete) root.append(node('p','Delivery details are complete' + (review.deliveryRegion ? ' for ' + review.deliveryRegion : '') + '.'),
                    button('Edit delivery details',() => { editing = true; dirty = true; draw(); },active() && hasTools()));
                if (!editing && draft.name && draft.address) {
                    const summary = node('div',undefined,'bapx-checkout__delivery');
                    summary.append(node('p','Delivery entered in this view','mcp-shop__muted'),node('p',draft.name),
                        node('p',draft.address),node('p',draft.city + ' · ' + draft.pincode + ' · ' + draft.state));
                    root.append(summary);
                }
                const coupon = node('label',undefined,'bapx-checkout__field'); coupon.append(node('span','Coupon (optional)'));
                const input = node('input'); input.type = 'text'; input.maxLength = 40; input.autocomplete = 'off'; input.value = draft.couponCode;
                input.disabled = busy || !active() || !hasTools(); input.setAttribute('aria-label','Coupon (optional)');
                input.addEventListener('input',() => { draft.couponCode = input.value; couponDirty = true; dirty = true;
                    const confirmation = container.querySelector('[data-checkout-confirm]'); if (confirmation) confirmation.disabled = true;
                });
                input.addEventListener('keydown',event => { if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); reviewNow(); } });
                coupon.append(input); root.append(coupon);
                if (review?.couponApplied) root.append(node('p','A coupon is applied. Its code is not returned to the chat.','mcp-shop__muted'),
                    button('Remove coupon on next review',() => { draft.couponCode = ''; couponDirty = true; dirty = true; draw(); },active() && hasTools()));
                root.append(button('Review checkout',reviewNow,active() && hasTools() && states.length > 0,true));
            }
            if (review) {
                root.append(totalsView(review.totals),node('p',review.notice,'mcp-shop__muted'));
                if (review.quote) {
                    const fresh = currentQuote();
                    root.append(node('p',fresh ? 'Quote valid until ' + new Date(review.quote.expiresAt).toLocaleTimeString() + '.'
                        : 'This quote expired or details changed. Review checkout again.','mcp-shop__muted'));
                    if (fresh) expiryTimer = setTimeout(draw,Math.min(600000,Math.max(1,Date.parse(review.quote.expiresAt) - Date.now() + 20)));
                }
                if (canShop && !locked() && !receipt) {
                    root.append(node('p',review.payment.mode === 'test' ? 'Test payment mode. This is not a live purchase.'
                        : review.payment.available ? 'Review this total, then explicitly confirm to prepare payment.'
                        : 'Payment is currently unavailable. No payment has been prepared.','mcp-shop__muted'));
                    const confirm = button('Confirm ' + money(review.totals.totalPaise) + ' and prepare payment',prepareNow,
                        active() && hasTools() && currentQuote() && review.deliveryComplete && review.payment.available && review.totals.totalPaise > 0,true);
                    confirm.dataset.checkoutConfirm = 'true'; root.append(confirm);
                }
            }
            if (attempt) {
                root.append(node('p','Confirmed amount: ' + money(attempt.amountPaise),'mcp-shop__price'));
                if (attempt.mode === 'test') root.append(node('p','Test payment mode','mcp-shop__muted'));
                root.append(node('p',attempt.notice,'mcp-shop__muted'));
                if (canShop && attempt.paymentUrl && !verified?.paymentStatus?.match(/^(captured|authorized)$/)) {
                    root.append(node('p','This host uses a separate merchant payment page. Your delivery form and order review stay here; payment credentials never enter this chat.','mcp-shop__muted'));
                    root.append(button('Continue to payment',() => {
                        if (!hasLinks() || Date.parse(attempt.paymentExpiresAt) <= Date.now()) return;
                        const openingEpoch = epoch;
                        const openingAttempt = attempt;
                        void openExternal(attempt.paymentUrl).then(opened => {
                            if (closed || !visible || epoch !== openingEpoch || attempt !== openingAttempt) return;
                            setMessage(opened === false ? 'This host declined the payment link. Keep this checkout open and use the verified payment link from the tool response.'
                                : 'Payment page opened. Return here to check verified payment and order status.');
                        }).catch(() => {
                            if (!closed && visible && epoch === openingEpoch && attempt === openingAttempt) setMessage('This host could not open the payment link. Keep this checkout open and check its status before another payment action.');
                        });
                    },hasLinks() && Date.parse(attempt.paymentExpiresAt) > Date.now(),true));
                    if (!hasLinks()) root.append(node('p','This host cannot open payment links. Checkout remains prepared; no payment success is implied.','mcp-shop__muted'));
                }
            }
            if (verified) {
                const facts = node('dl',undefined,'bapx-checkout__totals');
                facts.append(node('dt','Verified payment'),node('dd',verified.paymentStatus),node('dt','Order'),node('dd',verified.orderStatus.replaceAll('_',' ')),
                    node('dt','Amount'),node('dd',money(verified.amountPaise)),node('dt','Checked'),node('dd',new Date(verified.checkedAt).toLocaleString()));
                if (verified.orderReference) facts.append(node('dt','Order reference'),node('dd',verified.orderReference));
                root.append(facts,node('p',verified.notice,'mcp-shop__muted'));
            }
            if (receipt) root.append(button('Check payment status',() => { void refreshStatus(); },hasTools() && Date.parse(receipt.expiresAt) > Date.now()),
                node('p','Status access expires ' + new Date(receipt.expiresAt).toLocaleString() + ' (up to 7 days). A prepared payment is not a saved order.','mcp-shop__muted'));
            if (!active() && !receipt) root.append(node('p','This result has no checkout access in this view. Ask the assistant to reopen the chosen cart before continuing.','mcp-shop__muted'));
            root.append(button('Close checkout',() => { ++epoch; busy = false; visible = false; draft = blank(); dirty = false; couponDirty = false; form = null; draw(); onBusyChange?.(); }));
            container.replaceChildren(root); resize?.();
        }
        function clear() {
            ++epoch; clearTimeout(expiryTimer); busy = false; visible = false; target = null; draft = blank();
            review = null; attempt = null; verified = null; receipt = null; form = null; message = ''; editing = true; dirty = false; couponDirty = false;
        }
        function validResult(kind,data,args) {
            try {
                if (kind === 'review') checkedReview(data);
                else if (kind === 'payment') checkedAttempt(data);
                else if (kind === 'status') {
                    if (!record(args) || typeof args.paymentToken !== 'string' || !paymentPattern.test(args.paymentToken)) return false;
                    checkedStatus(data);
                } else return false;
                return true;
            } catch (_) { return false; }
        }
        return Object.freeze({
            open(value) {
                if (!canShop || closed || busy || !record(value) || !cartPattern.test(value.cartToken) || !integer(value.revision)
                    || !['current','buy_now'].includes(value.kind)) return false;
                if (target?.cartToken !== value.cartToken) { clear(); target = { cartToken:value.cartToken,revision:value.revision,kind:value.kind,expiresAt:value.expiresAt }; }
                else if (target.revision !== value.revision) { target.revision = value.revision; review = null; }
                visible = true;
                if (value.checkout) { receipt = checkedReceipt(value.checkout,value.kind); editing = false; }
                message = receipt ? 'Check the existing payment status before any new payment.' : 'Enter delivery details, then review the exact total here.';
                draw(); container.querySelector('h2')?.setAttribute('tabindex','-1'); container.querySelector('h2')?.focus();
                if (receipt) void refreshStatus(false);
                return true;
            },
            renderResult(kind,data,args,cartKind = 'current') {
                if (closed || !validResult(kind,data,args)) return false;
                try {
                    if (kind === 'status' && receipt?.paymentToken === args?.paymentToken && verified) {
                        const candidate = checkedStatus(data);
                        if (Date.parse(candidate.checkedAt) < Date.parse(verified.checkedAt)
                            || (verified.paymentStatus === 'captured' && candidate.paymentStatus !== 'captured')
                            || (verified.orderStatus === 'saved' && candidate.orderStatus !== 'saved')) return true;
                    }
                    if (kind === 'payment' && receipt?.paymentToken === data?.paymentToken && verified) {
                        checkedAttempt(data); return true;
                    }
                    clear(); visible = true;
                    if (record(args) && cartPattern.test(args.cartToken)) target = { cartToken:args.cartToken,revision:args.expectedRevision,kind:cartKind };
                    if (kind === 'review') acceptReview(data);
                    else if (kind === 'payment') { attempt = checkedAttempt(data); receipt = checkedReceipt(data,cartKind); editing = false; }
                    else {
                        if (!record(args) || !paymentPattern.test(args.paymentToken)) throw Error();
                        verified = checkedStatus(data); receipt = { paymentToken:args.paymentToken,expiresAt:verified.statusExpiresAt,kind:cartKind }; editing = false;
                    }
                    message = kind === 'status' ? 'Current server-verified payment and order status.' : 'Review this checkout before continuing.';
                    notifyRecovery(); draw(); return true;
                } catch (_) { clear(); draw(); return false; }
            },
            cartChanged(value) {
                if (target?.cartToken !== value?.cartToken) return;
                if (target.revision !== value.revision) { target.revision = value.revision; review = null; message = 'Cart changed. Review checkout again before confirming payment.'; }
                if (value.checkout) { receipt = checkedReceipt(value.checkout,value.kind); editing = false; notifyRecovery(); }
                draw();
            },
            async restore(value) {
                if (closed || busy || !record(value) || !paymentPattern.test(value.paymentToken) || !date(value.expiresAt)
                    || Date.parse(value.expiresAt) <= Date.now() || !['current','buy_now'].includes(value.kind)) return false;
                clear(); receipt = { paymentToken:value.paymentToken,expiresAt:value.expiresAt,kind:value.kind }; visible = true; editing = false;
                message = 'Refreshing this checkout receipt from the server…'; draw();
                await refreshStatus(false); return !!verified;
            },
            recovery,
            validResult,
            toolError(error,args) {
                if ((!target || args?.cartToken !== target.cartToken) && (!receipt || args?.paymentToken !== receipt.paymentToken)) return false;
                message = errorMessage(error); notifyRecovery(); draw(); return true;
            },
            busy:() => busy,
            locks:token => !!token && target?.cartToken === token && (!!receipt || locked()),
            cancel() { clear(); draw(); },
            teardown() { clear(); closed = true; draw(); }
        });
    } });
})();
