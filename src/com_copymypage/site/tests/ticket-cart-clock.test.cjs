// Run with node --test components/com_copymypage/tests/ticket-cart-clock.test.cjs.
// No dependencies or real reservations: execute both shipped runtimes with a controlled clock/DOM.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function setup(asset, {skew = 0, seconds = 900, elapsed = 0} = {}) {
    let now = elapsed;
    let wall = 1800000000000 + skew;
    const listeners = new Map();
    const intervals = [];
    const timeouts = new Map();
    const messages = [];
    let timer = 0;
    class Element {
        constructor() {
            this.attrs = new Map(); this.dataset = {}; this.hidden = false;
            this.textContent = ''; this.classes = new Set();
            this.classList = {add: (...v) => v.forEach(x => this.classes.add(x)),
                remove: (...v) => v.forEach(x => this.classes.delete(x))};
        }
        setAttribute(k, v) { this.attrs.set(k, v); }
        getAttribute(k) { return this.attrs.get(k) ?? null; }
        removeAttribute(k) { this.attrs.delete(k); }
        replaceChildren() {}
        querySelectorAll() { return []; }
        addEventListener() {}
        matches(s) { return s === '[data-cmp-ticket-cart]'; }
    }
    class Time extends Element { constructor() { super(); this.dateTime = '2026-10-08T20:00:00Z'; } }
    class Anchor extends Element {}
    const expiry = new Time();
    const empty = new Element();
    const summary = new Element();
    const indicator = new Element();
    const action = new Anchor();
    action.dataset.cmpTicketSelectionContinueUrl = '/checkout';
    const root = new Element();
    const nodes = {cartExpiry: expiry, cartEmpty: empty, cartSummary: summary,
        cartItems: new Element(), continue: action};
    const selectors = Object.fromEntries(Object.keys(nodes).map(k => [k, `[${k}]`]));
    Object.assign(selectors, {cart: '[data-cmp-ticket-cart]', basketIndicator: '[indicator]'});
    root.querySelector = s => nodes[Object.keys(selectors).find(k => selectors[k] === s)] || null;
    const config = {
        rootSelector: '[data-cmp-ticket-selection]',
        initializedAttribute: 'data-cmp-ticket-selection-initialized', selectors,
        attributes: {basketIndicator: 'data-cmp-ticket-cart-indicator', basketIndicatorExpiry: 'data-cmp-ticket-cart-indicator-expires'},
        initialCart: {active: true, expiresAt: expiry.dateTime, secondsLeft: seconds, cartRevision: 1},
        endpoints: {state: '/state'}, strings: {countdown: '%s Min.', expired: 'expired'},
        basketMessageType: 'copymypage:ticket-cart-state', refreshIntervalMs: 30000,
    };
    const document = {
        hidden: false, createDocumentFragment: () => ({querySelector: () => null}),
        querySelectorAll: s => s === config.rootSelector ? [root] : s === '[indicator]' ? [indicator] : [],
        addEventListener: (k, f) => listeners.set(k, f),
    };
    const window = {
        performance: {now: () => now, timeOrigin: 1800000000000, getEntriesByType: () => [{responseStart: 0}]},
        location: {origin: 'http://localhost'},
        addEventListener: (k, f) => listeners.set(k, f),
        setInterval: f => { intervals.push(f); return ++timer; },
        setTimeout: f => { timeouts.set(++timer, f); return timer; },
        clearTimeout: id => timeouts.delete(id),
        fetch: async () => { throw Error('offline'); },
    };
    window.parent = window;
    class DeviceDate extends Date { static now() { return wall; } }
    window.Joomla = {getOptions: () => ({com: {ticketCart: config}}), renderMessages: m => messages.push(m)};
    const context = vm.createContext({window, document, Date: DeviceDate, console,
        Element, HTMLTimeElement: Time, HTMLAnchorElement: Anchor,
        HTMLInputElement: class extends Element {}, HTMLFormElement: class extends Element {},
        HTMLButtonElement: class extends Element {}});
    const code = fs.readFileSync(path.resolve(__dirname, '../../../media/com_copymypage/js', asset), 'utf8');
    vm.runInContext(code, context);
    window.CopyMyPageTicketCart.init(document);
    return {window, document, config, root, expiry, indicator, summary, action, messages, intervals, timeouts,
        tick(ms) { now += ms; intervals.forEach(f => f()); },
        changeWall(ms) { wall += ms; },
        dispatch(k, data) { listeners.get(k)?.(data); },
        reloadScript() { vm.runInContext(code, context); window.CopyMyPageTicketCart.init(document); },
        remaining() { return window.CopyMyPageTicketCart.cartDeadline - now; },
    };
}

for (const asset of ['copymypage-ticket-cart.js', 'copymypage-ticket-cart.min.js']) {
    for (const skew of [0, -300000, 300000]) {
        test(`${asset}: clock skew ${skew} and clock change during countdown`, () => {
            const env = setup(asset, {skew});
            assert.equal(env.expiry.textContent, '15:00 Min.');
            env.tick(123000);
            assert.equal(env.expiry.textContent, '12:57 Min.');
            env.changeWall(7200000);
            env.tick(1000);
            assert.equal(env.expiry.textContent, '12:56 Min.');
            env.changeWall(-14400000);
            assert.equal(env.remaining(), 776000);
        });
    }
    test(`${asset}: initial delivery, reload remainder and idempotent initialization`, () => {
        const env = setup(asset, {seconds: 537, elapsed: 2300});
        assert.equal(env.remaining(), 534700);
        assert.equal(env.expiry.textContent, '8:55 Min.');
        env.tick(5000);
        env.reloadScript();
        assert.equal(env.remaining(), 529700);
        assert.equal(env.intervals.length, 2);
    });
    test(`${asset}: delayed response, extension and reuse of one response`, () => {
        const env = setup(asset);
        env.tick(200000);
        const cart = {active: true, expiresAt: '2026-10-08T21:00:00Z', secondsLeft: 900, cartRevision: 2};
        const runtime = env.window.CopyMyPageTicketCart;
        runtime.setBasketState(true, cart.expiresAt, undefined, true, cart, 2300);
        assert.equal(env.remaining(), 897700);
        env.tick(10000);
        runtime.setBasketState(true, cart.expiresAt, undefined, true, cart);
        assert.equal(env.remaining(), 887700);
    });
    test(`${asset}: throttled background timers and exact expiry boundary`, () => {
        const env = setup(asset, {seconds: 900});
        env.tick(899999);
        assert.equal(env.expiry.textContent, '0:01 Min.');
        assert.equal(env.indicator.getAttribute('data-cmp-ticket-cart-indicator'), 'active');
        env.tick(1);
        assert.equal(env.expiry.textContent, '0:00 Min.');
        assert.equal(env.indicator.getAttribute('data-cmp-ticket-cart-indicator'), 'empty');
        assert.equal(env.summary.hidden, true);
        assert.equal(env.action.getAttribute('aria-disabled'), 'true');
        assert.equal(env.messages.length, 1);
        env.tick(900000);
        assert.equal(env.messages.length, 1);
    });
    test(`${asset}: resume after a paused monotonic clock reads server remainder`, async () => {
        const env = setup(asset);
        await new Promise(resolve => setImmediate(resolve));
        env.window.fetch = async () => ({ok: true, json: async () => ({success: true,
            data: {cart: {...env.config.initialCart, secondsLeft: 321}}})});
        env.dispatch('pageshow', {persisted: true});
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(env.remaining(), 321000);
        assert.equal(env.expiry.textContent, '5:21 Min.');
    });
    test(`${asset}: older refresh cannot undo a mutation`, async () => {
        const env = setup(asset);
        await new Promise(resolve => setImmediate(resolve));
        let release;
        env.window.fetch = () => new Promise(resolve => { release = resolve; });
        env.dispatch('focus');
        const cart = {active: true, expiresAt: '2026-10-08T21:00:00Z', secondsLeft: 900, cartRevision: 2};
        env.window.CopyMyPageTicketCart.setBasketState(true, cart.expiresAt, undefined, true, cart);
        release({ok: true, json: async () => ({success: true, data: {cart: {...env.config.initialCart, secondsLeft: 5}}})});
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(env.remaining(), 900000);
        assert.equal(env.window.CopyMyPageTicketCart.cartRevision, 2);
    });
    test(`${asset}: delayed drawer message deducts queue time`, () => {
        const env = setup(asset);
        env.tick(12000);
        env.dispatch('message', {origin: 'http://localhost', data: {type: env.config.basketMessageType,
            active: true, sentAt: 1800000002000,
            state: {cart: {active: true, expiresAt: env.expiry.dateTime, secondsLeft: 600}}}});
        assert.equal(env.remaining(), 590000);
        assert.equal(env.expiry.textContent, '9:50 Min.');
    });
    test(`${asset}: drawer expiry also clears a parent cart and its continue action`, () => {
        const env = setup(asset);
        env.window.CopyMyPageTicketCart.receiveState(false);
        assert.equal(env.indicator.getAttribute('data-cmp-ticket-cart-indicator'), 'empty');
        assert.equal(env.summary.hidden, true);
        assert.equal(env.action.getAttribute('aria-disabled'), 'true');
        assert.equal(env.expiry.textContent, '0:00 Min.');
    });
    test(`${asset}: failed resume request cannot renew a reservation`, async () => {
        const env = setup(asset);
        await new Promise(resolve => setImmediate(resolve));
        env.tick(200000);
        env.dispatch('visibilitychange');
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(env.remaining(), 700000);
        env.tick(700000);
        assert.equal(env.indicator.getAttribute('data-cmp-ticket-cart-indicator'), 'empty');
    });
}
