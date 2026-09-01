/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

(function (window, document, Joomla) {
    'use strict';

    const runtime = window.CopyMyPageTicketCart || {};
    const instances = runtime.instances instanceof WeakSet
        ? runtime.instances
        : new WeakSet();
    const cartEmptyExpiredClass = 'cmp-ticket-cart__empty--expired';

    const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

    const isValidSelector = (selector) => {
        if (typeof selector !== 'string' || selector.trim() === '') {
            return false;
        }

        try {
            document.createDocumentFragment().querySelector(selector);

            return true;
        } catch (error) {
            return false;
        }
    };

    const isValidDataAttribute = (attribute) => typeof attribute === 'string'
        && /^data-[a-z0-9-]+$/.test(attribute.trim());

    const isValidFieldName = (field) => typeof field === 'string'
        && /^[A-Za-z][A-Za-z0-9_]*$/.test(field.trim());

    const getConfig = () => {
        if (!Joomla || typeof Joomla.getOptions !== 'function') {
            return {};
        }

        const options = Joomla.getOptions('copymypage.params', {}) || {};

        return isObject(options.com) && isObject(options.com.ticketCart)
            ? options.com.ticketCart
            : {};
    };

    const collectRoots = (context, selector) => {
        const roots = [];
        const scope = context && typeof context.querySelectorAll === 'function'
            ? context
            : document;

        if (scope instanceof Element && scope.matches(selector)) {
            roots.push(scope);
        }

        scope.querySelectorAll(selector).forEach((root) => {
            if (!roots.includes(root)) {
                roots.push(root);
            }
        });

        return roots;
    };

    const select = (root, selector) => isValidSelector(selector)
        ? root.querySelector(selector)
        : null;

    const selectAll = (root, selector) => isValidSelector(selector)
        ? Array.from(root.querySelectorAll(selector))
        : [];

    const getEventId = (element, config) => {
        const attribute = isObject(config.attributes) ? config.attributes.eventId : '';

        if (!(element instanceof Element) || !isValidDataAttribute(attribute)) {
            return 0;
        }

        const value = Number(element.getAttribute(attribute));

        return Number.isInteger(value) && value > 0 ? value : 0;
    };

    const getJsonData = (payload) => isObject(payload) && isObject(payload.data)
        ? payload.data
        : null;

    const normalizeCartRevision = (value) => {
        const revision = Number(value);

        return Number.isInteger(revision) && revision >= 0 ? revision : 0;
    };

    const updateRevisionFields = (root, revision, config) => {
        if (!isObject(config.selectors)) {
            return;
        }

        selectAll(root, config.selectors.revisionField).forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = String(normalizeCartRevision(revision));
            }
        });
    };

    const setBusy = (form, busy) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.toggleAttribute('aria-busy', busy);
        form.querySelectorAll('button, input, select').forEach((control) => {
            if (busy) {
                control.dataset.cmpTicketWasDisabled = control.disabled ? '1' : '0';
                control.disabled = true;

                return;
            }

            if (control.dataset.cmpTicketWasDisabled === '0') {
                control.disabled = false;
            }

            delete control.dataset.cmpTicketWasDisabled;
        });
    };

    const renderMessage = (message, error) => {
        if (typeof message !== 'string' || message.trim() === '') {
            return;
        }

        if (Joomla && typeof Joomla.renderMessages === 'function') {
            Joomla.renderMessages({
                [error ? 'error' : 'message']: [message],
            });

            return;
        }

        if (error) {
            console.error(message);
        }
    };

    const normalizeBasketExpiry = (value) => {
        if (typeof value !== 'string' || value.trim() === '') {
            return '';
        }

        const timestamp = Date.parse(value);

        return Number.isFinite(timestamp) ? new Date(timestamp).toISOString() : '';
    };

    const updateBasketIndicators = (
        active,
        config = getConfig(),
        context = document,
        expiresAt = null
    ) => {
        if (!isObject(config.selectors) || !isObject(config.attributes)) {
            return;
        }

        const selector = config.selectors.basketIndicator;
        const attribute = config.attributes.basketIndicator;
        const expiryAttribute = config.attributes.basketIndicatorExpiry;
        const scope = context && typeof context.querySelectorAll === 'function'
            ? context
            : document;

        if (!isValidSelector(selector) || !isValidDataAttribute(attribute)) {
            return;
        }

        scope.querySelectorAll(selector).forEach((indicator) => {
            indicator.setAttribute(attribute, active ? 'active' : 'empty');

            if (isValidDataAttribute(expiryAttribute) && expiresAt !== null) {
                const normalizedExpiry = active ? normalizeBasketExpiry(expiresAt) : '';

                if (normalizedExpiry === '') {
                    indicator.removeAttribute(expiryAttribute);
                } else {
                    indicator.setAttribute(expiryAttribute, normalizedExpiry);
                }
            }
        });
    };

    const getBasketExpiryTimestamp = (config = getConfig()) => {
        if (!isObject(config.selectors) || !isObject(config.attributes)) {
            return null;
        }

        const selector = config.selectors.basketIndicator;
        const stateAttribute = config.attributes.basketIndicator;
        const expiryAttribute = config.attributes.basketIndicatorExpiry;

        if (
            !isValidSelector(selector)
            || !isValidDataAttribute(stateAttribute)
            || !isValidDataAttribute(expiryAttribute)
        ) {
            return null;
        }

        const timestamps = Array.from(document.querySelectorAll(selector)).map((indicator) => {
            if (indicator.getAttribute(stateAttribute) !== 'active') {
                return Number.NaN;
            }

            return Date.parse(indicator.getAttribute(expiryAttribute) || '');
        }).filter((timestamp) => Number.isFinite(timestamp));

        return timestamps.length > 0 ? Math.min(...timestamps) : null;
    };

    const scheduleBasketExpiryCheck = (config = getConfig()) => {
        if (runtime.basketExpiryTimer) {
            window.clearTimeout(runtime.basketExpiryTimer);
            runtime.basketExpiryTimer = null;
        }

        const timestamp = getBasketExpiryTimestamp(config);

        if (timestamp === null) {
            return;
        }

        const remaining = timestamp - Date.now();

        if (remaining <= 0) {
            updateBasketIndicators(false, config, document, '');

            return;
        }

        runtime.basketExpiryTimer = window.setTimeout(() => {
            runtime.basketExpiryTimer = null;
            scheduleBasketExpiryCheck(getConfig());
        }, Math.min(remaining + 50, 2147483647));
    };

    const setBasketState = (active, expiresAt = null, config = getConfig(), continuable = null) => {
        const normalizedActive = active === true;
        const normalizedExpiry = normalizedActive ? expiresAt : '';

        updateBasketIndicators(normalizedActive, config, document, normalizedExpiry);
        if (!normalizedActive || typeof continuable === 'boolean') {
            const canContinue = normalizedActive && continuable === true;

            if (isValidSelector(config.rootSelector)) {
                collectRoots(document, config.rootSelector).forEach(
                    (root) => updateContinueAction(root, canContinue, config)
                );
            }
        }
        scheduleBasketExpiryCheck(config);
    };

    const notifyBasketState = (active, config, state = null) => {
        const cartState = isObject(state) && isObject(state.cart) ? state.cart : null;
        const expiresAt = cartState && typeof cartState.expiresAt === 'string'
            ? cartState.expiresAt
            : null;
        const continuable = cartState && typeof cartState.continuable === 'boolean'
            ? cartState.continuable
            : null;

        setBasketState(Boolean(active), expiresAt, config, continuable);

        if (
            window.parent === window
            || typeof config.basketMessageType !== 'string'
            || config.basketMessageType.trim() === ''
        ) {
            return;
        }

        try {
            // The document drawer is same-origin. Updating its parent directly
            // keeps the navbar indicator correct even if its message listener
            // has not completed initialization yet.
            const parentRuntime = window.parent.CopyMyPageTicketCart;

            if (parentRuntime && typeof parentRuntime.setBasketState === 'function') {
                parentRuntime.setBasketState(Boolean(active), expiresAt, undefined, continuable);
            } else {
                updateBasketIndicators(active, config, window.parent.document, expiresAt);
            }
        } catch (error) {
            // Cross-origin parents remain isolated and use postMessage below.
        }

        window.parent.postMessage({
            type: config.basketMessageType,
            active: Boolean(active),
            state: isObject(state) ? state : null,
        }, window.location.origin);
    };

    const request = async (url, body, config) => {
        if (typeof url !== 'string' || url.trim() === '') {
            throw new Error(config.strings.requestFailed || 'Request failed.');
        }

        const token = typeof config.csrfToken === 'string' ? config.csrfToken.trim() : '';

        if (token !== '' && !body.has(token)) {
            body.append(token, '1');
        }

        const response = await window.fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => null);

        if ((!response.ok && response.status !== 409) || !isObject(payload)) {
            throw new Error(config.strings.requestFailed || 'Request failed.');
        }

        return payload;
    };

    const replaceStatusClass = (element, prefix, status) => {
        if (!(element instanceof Element)) {
            return;
        }

        Array.from(element.classList).forEach((className) => {
            if (className.startsWith(prefix)) {
                element.classList.remove(className);
            }
        });
        element.classList.add(`${prefix}${status}`);
    };

    const hasSelectedQuantity = (form, config) => {
        if (!(form instanceof HTMLFormElement) || !isObject(config.selectors)) {
            return false;
        }

        return selectAll(form, config.selectors.quantity).some((input) => {
            if (!(input instanceof HTMLInputElement)) {
                return false;
            }

            const quantity = Number(input.value);

            return Number.isInteger(quantity) && quantity > 0;
        });
    };

    const updateEventSubmitState = (form, canReserve, config) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const button = form.querySelector('button[type="submit"]');

        if (button instanceof HTMLButtonElement) {
            button.disabled = !canReserve || !hasSelectedQuantity(form, config);
        }
    };

    const updateQuantitySubmitState = (input, config) => {
        if (!(input instanceof HTMLInputElement) || !isObject(config.selectors)) {
            return;
        }

        const formSelector = config.selectors.eventForm;
        const quantitySelector = config.selectors.quantity;

        if (!isValidSelector(formSelector) || !isValidSelector(quantitySelector)
            || !input.matches(quantitySelector)) {
            return;
        }

        updateEventSubmitState(input.closest(formSelector), true, config);
    };

    const updateEvent = (root, eventState, config) => {
        if (!isObject(eventState) || !isObject(config.selectors)) {
            return;
        }

        const eventElement = selectAll(root, config.selectors.event).find(
            (element) => getEventId(element, config) === Number(eventState.id)
        );

        if (!(eventElement instanceof Element)) {
            return;
        }

        const availability = isObject(eventState.availability) ? eventState.availability : {};
        const status = typeof availability.status === 'string'
            && /^[a-z-]+$/.test(availability.status)
            ? availability.status
            : 'unavailable';
        const statusElement = select(eventElement, config.selectors.eventStatus);
        const statusDot = eventElement.querySelector('.cmp-ticket-selection-event__status-dot');

        if (statusElement) {
            statusElement.textContent = availability.statusLabel || '';
        }

        replaceStatusClass(
            statusDot,
            'cmp-ticket-selection-event__status-dot--',
            status
        );

        const prices = Array.isArray(eventState.prices) ? eventState.prices : [];

        selectAll(eventElement, config.selectors.quantity).forEach((input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const match = input.name.match(/^quantities\[(\d+)]$/);
            const price = match
                ? prices.find((item) => Number(item.index) === Number(match[1]))
                : null;

            if (!isObject(price)) {
                return;
            }

            input.max = String(Math.max(0, Number(price.limit) || 0));
            input.value = String(Math.max(0, Number(price.quantity) || 0));
            input.disabled = !eventState.canReserve || Number(price.limit) < 1;
        });

        const form = select(eventElement, config.selectors.eventForm);
        const button = form instanceof HTMLFormElement ? form.querySelector('button[type="submit"]') : null;

        if (button instanceof HTMLButtonElement) {
            updateEventSubmitState(form, Boolean(eventState.canReserve), config);
            const label = button.querySelector('span:last-child');

            if (label) {
                label.textContent = eventState.submitLabel || config.strings.update || '';
            }
        }
    };

    const createCartItem = (item, config, cartRevision) => {
        const listItem = document.createElement('li');
        const copy = document.createElement('div');
        const title = document.createElement('strong');
        const date = document.createElement('small');
        const form = document.createElement('form');
        const eventInput = document.createElement('input');
        const returnInput = document.createElement('input');
        const revisionInput = document.createElement('input');
        const tokenInput = document.createElement('input');
        const button = document.createElement('button');
        const icon = document.createElement('span');

        listItem.className = 'cmp-ticket-cart-item';

        if (isValidDataAttribute(config.attributes.eventId)) {
            listItem.setAttribute(
                config.attributes.eventId,
                String(Math.max(0, Number(item.eventId) || 0))
            );
        }

        copy.className = 'cmp-ticket-cart-item__copy';
        title.textContent = item.title || '';
        date.textContent = item.dateLabel || '';
        copy.append(title, date);

        (Array.isArray(item.prices) ? item.prices : []).forEach((price) => {
            const line = document.createElement('span');
            line.textContent = price.summaryLabel || '';
            copy.append(line);
        });

        if (typeof item.selectedSeatsLabel === 'string' && item.selectedSeatsLabel !== '') {
            const seatCount = document.createElement('span');
            seatCount.className = 'cmp-ticket-cart-item__seat-count';
            seatCount.textContent = item.selectedSeatsLabel;
            copy.append(seatCount);
        }

        if (!item.continuable && typeof item.statusLabel === 'string' && item.statusLabel !== '') {
            const status = document.createElement('small');
            status.className = 'cmp-ticket-cart-item__status';
            status.textContent = item.statusLabel;
            copy.append(status);
        }

        form.method = 'post';
        form.action = config.fallbackActions.remove || '';

        if (isValidDataAttribute(config.attributes.removeForm)) {
            form.setAttribute(config.attributes.removeForm, '');
        }

        eventInput.type = 'hidden';
        eventInput.name = 'event_id';
        eventInput.value = String(Number(item.eventId) || 0);
        returnInput.type = 'hidden';
        returnInput.name = 'return_view';
        returnInput.value = 'basket';
        revisionInput.type = 'hidden';
        revisionInput.value = String(normalizeCartRevision(cartRevision));

        const revisionField = isObject(config.fields)
            ? config.fields.expectedCartRevision
            : '';

        if (isValidFieldName(revisionField)) {
            revisionInput.name = revisionField;

            if (isValidDataAttribute(config.attributes.revisionField)) {
                revisionInput.setAttribute(config.attributes.revisionField, '');
            }
        }

        tokenInput.type = 'hidden';
        tokenInput.name = config.csrfToken || '';
        tokenInput.value = '1';
        button.type = 'submit';
        button.className = 'uk-button uk-button-danger cmp-button cmp-button--danger '
            + 'cmp-button--icon cmp-ticket-cart-item__remove';
        button.setAttribute(
            'aria-label',
            (config.strings.removeAria || '').replace('%s', item.title || '')
        );
        icon.setAttribute('uk-icon', 'icon: trash');
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon);
        form.append(eventInput, returnInput);

        if (revisionInput.name !== '') {
            form.append(revisionInput);
        }

        form.append(tokenInput, button);
        listItem.append(copy, form);

        return listItem;
    };

    const formatCountdown = (seconds, config) => {
        const remaining = Math.max(0, Math.floor(seconds));
        const minutes = Math.floor(remaining / 60);
        const rest = String(remaining % 60).padStart(2, '0');
        const value = `${minutes}:${rest}`;

        return (config.strings.countdown || '%s Min.').replace('%s', value);
    };

    const updateContinueAction = (root, continuable, config) => {
        if (!isObject(config.selectors)) {
            return;
        }

        const action = select(root, config.selectors.continue);

        if (!(action instanceof HTMLAnchorElement)) {
            return;
        }

        const url = action.dataset.cmpTicketSelectionContinueUrl || '';
        const enabled = Boolean(continuable) && url !== '';

        if (enabled) {
            action.removeAttribute('aria-disabled');
            action.removeAttribute('disabled');
            action.href = url;
            action.target = '_top';
            action.removeAttribute('tabindex');

            return;
        }

        action.setAttribute('aria-disabled', 'true');
        action.setAttribute('disabled', '');
        action.removeAttribute('href');
        action.removeAttribute('target');
        action.tabIndex = -1;
    };

    const updateCart = (root, cart, config, state = null) => {
        if (!isObject(cart) || !isObject(config.selectors)) {
            return;
        }

        const itemsContainer = select(root, config.selectors.cartItems);
        const empty = select(root, config.selectors.cartEmpty);
        const summary = select(root, config.selectors.cartSummary);
        const total = select(root, config.selectors.cartTotal);
        const expiry = select(root, config.selectors.cartExpiry);
        const items = Array.isArray(cart.items) ? cart.items : [];
        const active = Boolean(cart.active) && items.length > 0;
        const cartRevision = normalizeCartRevision(cart.cartRevision);

        updateContinueAction(root, cart.continuable, config);

        if (itemsContainer) {
            itemsContainer.replaceChildren(
                ...items.map((item) => createCartItem(item, config, cartRevision))
            );
        }

        updateRevisionFields(root, cartRevision, config);

        if (empty) {
            empty.classList.remove(cartEmptyExpiredClass);
            empty.hidden = active;
            empty.textContent = config.strings.empty || '';
        }

        if (summary) {
            summary.hidden = !active;
        }

        if (total) {
            total.textContent = cart.totalFormatted || '';
        }

        if (expiry instanceof HTMLTimeElement) {
            expiry.dateTime = cart.expiresAt || '';
            expiry.dataset.cmpTicketExpiryHandled = '0';
            expiry.textContent = formatCountdown(cart.secondsLeft || 0, config);
        }

        if (window.UIkit && typeof window.UIkit.update === 'function') {
            window.UIkit.update(root);
        }

        notifyBasketState(active, config, state);
    };

    const expireClientCart = (root, config) => {
        const empty = select(root, config.selectors.cartEmpty);
        const summary = select(root, config.selectors.cartSummary);
        const items = select(root, config.selectors.cartItems);

        if (empty) {
            empty.classList.add(cartEmptyExpiredClass);
            empty.hidden = false;
            empty.textContent = config.strings.expired || '';
        }

        if (summary) {
            summary.hidden = true;
        }

        if (items) {
            items.replaceChildren();
        }

        selectAll(root, config.selectors.quantity).forEach((input) => {
            if (input instanceof HTMLInputElement) {
                input.value = '0';
            }
        });
        selectAll(root, config.selectors.eventForm).forEach((form) => {
            updateEventSubmitState(form, true, config);
        });
        updateContinueAction(root, false, config);
        updateRevisionFields(root, 0, config);

        notifyBasketState(false, config);
        renderMessage(config.strings.expired || '', true);
    };

    const updateCountdowns = () => {
        const config = getConfig();

        if (!isValidSelector(config.rootSelector) || !isObject(config.selectors)) {
            return;
        }

        document.querySelectorAll(config.rootSelector).forEach((root) => {
            const expiry = select(root, config.selectors.cartExpiry);

            if (!(expiry instanceof HTMLTimeElement) || expiry.dateTime === '') {
                return;
            }

            const timestamp = Date.parse(expiry.dateTime);

            if (!Number.isFinite(timestamp)) {
                return;
            }

            const seconds = Math.max(0, Math.ceil((timestamp - Date.now()) / 1000));
            expiry.textContent = formatCountdown(seconds, config);

            if (seconds === 0 && expiry.dataset.cmpTicketExpiryHandled !== '1') {
                expiry.dataset.cmpTicketExpiryHandled = '1';
                expireClientCart(root, config);
            }
        });
    };

    const updateState = (root, state, config) => {
        if (!isObject(state)) {
            return;
        }

        (Array.isArray(state.events) ? state.events : []).forEach(
            (eventState) => updateEvent(root, eventState, config)
        );
        updateCart(root, isObject(state.cart) ? state.cart : {}, config, state);
    };

    const handleSubmit = async (event, root, config) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !isObject(config.selectors)) {
            return;
        }

        let endpoint = '';

        if (form.matches(config.selectors.eventForm)) {
            endpoint = config.endpoints.reserve || '';

            if (!hasSelectedQuantity(form, config)) {
                event.preventDefault();
                updateEventSubmitState(form, true, config);

                return;
            }
        } else if (form.matches(config.selectors.removeForm)) {
            endpoint = config.endpoints.remove || '';
        } else if (form.matches(config.selectors.clearForm)) {
            endpoint = config.endpoints.clear || '';
        } else {
            return;
        }

        event.preventDefault();
        const body = new FormData(form);
        setBusy(form, true);
        let busy = true;

        try {
            const payload = await request(endpoint, body, config);
            const state = getJsonData(payload);

            setBusy(form, false);
            busy = false;
            updateState(root, state, config);
            renderMessage(payload.message || '', !payload.success);
        } catch (error) {
            const message = error instanceof Error && error.message !== ''
                ? error.message
                : config.strings.requestFailed || '';
            renderMessage(message, true);
        } finally {
            if (busy) {
                setBusy(form, false);
            }
        }
    };

    const initializeRoot = (root, config) => {
        if (!(root instanceof Element) || instances.has(root)) {
            return;
        }

        const initializedAttribute = config.initializedAttribute;

        if (!isValidDataAttribute(initializedAttribute)) {
            return;
        }

        instances.add(root);
        root.setAttribute(initializedAttribute, 'true');
        selectAll(root, config.selectors.eventForm).forEach((form) => {
            updateEventSubmitState(form, true, config);
        });
        root.addEventListener('submit', (event) => handleSubmit(event, root, config));
        root.addEventListener('input', (event) => updateQuantitySubmitState(event.target, config));
        root.addEventListener('change', (event) => updateQuantitySubmitState(event.target, config));
    };

    runtime.init = (context) => {
        const config = getConfig();

        if (!isValidSelector(config.rootSelector)) {
            return;
        }

        const roots = collectRoots(context, config.rootSelector);

        roots.forEach((root) => initializeRoot(root, config));

        if (!runtime.basketMessageListener) {
            window.addEventListener('message', (event) => {
                const currentConfig = getConfig();

                if (
                    event.origin !== window.location.origin
                    || !isObject(event.data)
                    || event.data.type !== currentConfig.basketMessageType
                    || typeof event.data.active !== 'boolean'
                ) {
                    return;
                }

                const cartState = isObject(event.data.state) && isObject(event.data.state.cart)
                    ? event.data.state.cart
                    : null;
                const expiresAt = cartState && typeof cartState.expiresAt === 'string'
                    ? cartState.expiresAt
                    : null;

                setBasketState(event.data.active, expiresAt, currentConfig);

                if (isObject(event.data.state) && isValidSelector(currentConfig.rootSelector)) {
                    collectRoots(document, currentConfig.rootSelector).forEach(
                        (root) => updateState(root, event.data.state, currentConfig)
                    );
                }
            });
            runtime.basketMessageListener = true;
        }

        if (!runtime.basketExpiryListenersRegistered) {
            window.addEventListener('focus', () => scheduleBasketExpiryCheck(getConfig()));
            window.addEventListener('pageshow', () => scheduleBasketExpiryCheck(getConfig()));
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) {
                    scheduleBasketExpiryCheck(getConfig());
                }
            });
            runtime.basketExpiryListenersRegistered = true;
        }

        scheduleBasketExpiryCheck(config);

        const cartSelector = isObject(config.selectors) ? config.selectors.cart : '';
        const hasCartRoot = isValidSelector(cartSelector) && roots.some(
            (root) => root.matches(cartSelector) || select(root, cartSelector)
        );

        if (!runtime.countdownTimer && hasCartRoot) {
            updateCountdowns();
            runtime.countdownTimer = window.setInterval(updateCountdowns, 1000);
        }
    };

    runtime.instances = instances;
    runtime.setBasketState = setBasketState;
    window.CopyMyPageTicketCart = runtime;
})(window, document, window.Joomla);
