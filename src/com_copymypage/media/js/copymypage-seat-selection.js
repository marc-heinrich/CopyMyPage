/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

(function (window, document, Joomla) {
    'use strict';

    const runtime = window.CopyMyPageSeatSelection || {};
    const instances = runtime.instances instanceof Set ? runtime.instances : new Set();
    const zoomStates = runtime.zoomStates instanceof WeakMap
        ? runtime.zoomStates
        : new WeakMap();
    const initializedViewports = runtime.initializedViewports instanceof WeakSet
        ? runtime.initializedViewports
        : new WeakSet();
    const initializedTableFocusLinks = runtime.initializedTableFocusLinks instanceof WeakSet
        ? runtime.initializedTableFocusLinks
        : new WeakSet();
    const tableFocusUpdateFrames = runtime.tableFocusUpdateFrames instanceof WeakMap
        ? runtime.tableFocusUpdateFrames
        : new WeakMap();
    let zoomFitFrame = Number.isInteger(runtime.zoomFitFrame) ? runtime.zoomFitFrame : null;
    let tableFocusResizeObserver = runtime.tableFocusResizeObserver || null;

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

    const isValidFieldName = (fieldName) => typeof fieldName === 'string'
        && /^[A-Za-z][A-Za-z0-9_]*$/.test(fieldName.trim());

    const getDataAttributeFromSelector = (selector) => {
        if (typeof selector !== 'string') {
            return '';
        }

        const match = selector.trim().match(/^\[(data-[a-z0-9-]+)\]$/);

        return match && isValidDataAttribute(match[1]) ? match[1] : '';
    };

    const getSeatRemoveAttribute = (config) => {
        const attribute = isObject(config.attributes) ? config.attributes.seatRemove : '';

        if (isValidDataAttribute(attribute)) {
            return attribute;
        }

        return isObject(config.selectors)
            ? getDataAttributeFromSelector(config.selectors.seatRemove)
            : '';
    };

    const hasValidSelectors = (config) => {
        if (!isObject(config) || !isValidSelector(config.rootSelector)
            || !isObject(config.selectors)) {
            return false;
        }

        return [
            'continue',
            'event',
            'eventCount',
            'eventForm',
            'eventMessage',
            'eventStatus',
            'globalStatus',
            'revisionField',
            'seat',
            'seatRemove',
            'selectedSeats',
            'suggest',
            'tableFocus',
            'tableFocusLinks',
            'tableFocusNext',
            'tableFocusPrevious',
            'zoomCanvas',
            'zoomIn',
            'zoomOut',
            'zoomReset',
            'zoomViewport',
        ].every((key) => isValidSelector(config.selectors[key]));
    };

    const getConfig = () => {
        if (!Joomla || typeof Joomla.getOptions !== 'function') {
            return {};
        }

        const options = Joomla.getOptions('copymypage.params', {}) || {};

        return isObject(options.com) && isObject(options.com.ticketSeats)
            ? options.com.ticketSeats
            : {};
    };

    const select = (root, selector) => isValidSelector(selector)
        ? root.querySelector(selector)
        : null;

    const selectAll = (root, selector) => isValidSelector(selector)
        ? Array.from(root.querySelectorAll(selector))
        : [];

    const collectRoots = (context, selector) => {
        const roots = [];
        const scope = context && typeof context.querySelectorAll === 'function'
            ? context
            : document;

        if (scope instanceof Element) {
            const root = scope.matches(selector) ? scope : scope.closest(selector);

            if (root instanceof Element) {
                roots.push(root);
            }
        }

        scope.querySelectorAll(selector).forEach((root) => {
            if (!roots.includes(root)) {
                roots.push(root);
            }
        });

        return roots;
    };

    const normalizePositiveInteger = (value) => {
        const number = Number(value);

        return Number.isInteger(number) && number > 0 ? number : 0;
    };

    const normalizeRevision = (value) => {
        const revision = Number(value);

        return Number.isInteger(revision) && revision >= 0 ? revision : 0;
    };

    const getElementId = (element, attribute) => {
        if (!(element instanceof Element) || !isValidDataAttribute(attribute)) {
            return 0;
        }

        return normalizePositiveInteger(element.getAttribute(attribute));
    };

    const getEventId = (element, config) => getElementId(
        element,
        isObject(config.attributes) ? config.attributes.eventId : ''
    );

    const getSeatId = (element, config) => getElementId(
        element,
        isObject(config.attributes) ? config.attributes.seatId : ''
    );

    const getRequiredCount = (eventElement, config) => {
        const attribute = isObject(config.attributes) ? config.attributes.requiredCount : '';

        return getElementId(eventElement, attribute);
    };

    const replaceTemplateValues = (template, values) => {
        if (typeof template !== 'string') {
            return '';
        }

        let output = template;

        values.forEach((value, index) => {
            output = output.split(`%${index + 1}$s`).join(String(value));
            output = output.replace('%s', String(value));
        });

        return output;
    };

    const announce = (root, message) => {
        if (!(root instanceof Element) || typeof message !== 'string' || message.trim() === '') {
            return;
        }

        const config = getConfig();
        const status = isObject(config.selectors)
            ? select(root, config.selectors.globalStatus)
            : null;

        if (!(status instanceof Element)) {
            return;
        }

        status.textContent = '';
        window.requestAnimationFrame(() => {
            status.textContent = message;
        });
    };

    const setEventMessage = (eventElement, message, config) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const region = select(eventElement, config.selectors.eventMessage);

        if (!(region instanceof HTMLElement)) {
            return;
        }

        const normalized = typeof message === 'string' ? message.trim() : '';
        region.textContent = normalized;
        region.hidden = normalized === '';
    };

    const setBusy = (form, busy) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (busy) {
            form.setAttribute('aria-busy', 'true');
        } else {
            form.removeAttribute('aria-busy');
        }

        Array.from(form.querySelectorAll('fieldset, button, input:not([type="hidden"]), select'))
            .filter((control) => control instanceof HTMLFieldSetElement
                || !(control.closest('fieldset') instanceof HTMLFieldSetElement))
            .forEach((control) => {
                if (!(control instanceof HTMLButtonElement)
                    && !(control instanceof HTMLFieldSetElement)
                    && !(control instanceof HTMLInputElement)
                    && !(control instanceof HTMLSelectElement)) {
                    return;
                }

                if (busy) {
                    control.dataset.cmpSeatWasDisabled = control.disabled ? '1' : '0';
                    control.disabled = true;

                    return;
                }

                if (control.dataset.cmpSeatWasDisabled === '0') {
                    control.disabled = false;
                }

                delete control.dataset.cmpSeatWasDisabled;
            });
    };

    const request = async (url, body, config) => {
        if (typeof url !== 'string' || url.trim() === '') {
            throw new Error(isObject(config.strings) ? config.strings.requestFailed || '' : '');
        }

        const token = typeof config.csrfToken === 'string' ? config.csrfToken.trim() : '';

        if (token !== '' && !body.has(token)) {
            body.append(token, '1');
        }

        const response = await window.fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => null);

        if ((!response.ok && response.status !== 409) || !isObject(payload)) {
            throw new Error(isObject(config.strings) ? config.strings.requestFailed || '' : '');
        }

        return {
            payload,
            conflict: response.status === 409 || payload.success === false,
        };
    };

    const requestState = async (url, eventId, config) => {
        if (typeof url !== 'string' || url.trim() === '' || eventId < 1) {
            throw new Error(isObject(config.strings) ? config.strings.requestFailed || '' : '');
        }

        const requestUrl = new URL(url, window.location.href);
        requestUrl.searchParams.set('event_id', String(eventId));
        const response = await window.fetch(requestUrl.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => null);

        if ((!response.ok && response.status !== 409) || !isObject(payload)) {
            throw new Error(isObject(config.strings) ? config.strings.requestFailed || '' : '');
        }

        return {
            payload,
            conflict: response.status === 409 || payload.success === false,
        };
    };

    const buildMutationBody = (form, config) => {
        const body = new FormData(form);
        const fieldName = isObject(config.fields) && isValidFieldName(config.fields.seatIds)
            ? config.fields.seatIds.trim()
            : 'seat_ids';
        const inputName = `${fieldName}[]`;

        body.delete(fieldName);
        body.delete(inputName);
        selectAll(form, config.selectors.seat).forEach((seatElement) => {
            const seatId = getSeatId(seatElement, config);
            const input = seatElement.querySelector('input[type="checkbox"]');

            if (seatId > 0 && input instanceof HTMLInputElement
                && input.checked && !input.disabled) {
                body.append(inputName, String(seatId));
            }
        });

        return body;
    };

    const getPayloadData = (payload) => isObject(payload) && isObject(payload.data)
        ? payload.data
        : {};

    const updateRevisionFields = (root, revision, config) => {
        if (!(root instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const value = String(normalizeRevision(revision));

        selectAll(root, config.selectors.revisionField).forEach((input) => {
            if (input instanceof HTMLInputElement && input.value !== value) {
                input.value = value;
            }
        });
    };

    const replaceModifierClass = (element, prefix, modifier) => {
        if (!(element instanceof Element)) {
            return;
        }

        const nextClass = `${prefix}${modifier}`;
        let hasNextClass = false;

        Array.from(element.classList).forEach((className) => {
            if (className === nextClass) {
                hasNextClass = true;
            } else if (className.startsWith(prefix)) {
                element.classList.remove(className);
            }
        });

        if (!hasNextClass) {
            element.classList.add(nextClass);
        }
    };

    const getEventStatus = (eventState) => {
        if (!isObject(eventState) || !eventState.ready) {
            return 'unavailable';
        }

        return eventState.complete ? 'complete' : 'incomplete';
    };

    const getStatusLabel = (status, config) => {
        if (!isObject(config.strings)) {
            return '';
        }

        return typeof config.strings[status] === 'string' ? config.strings[status] : '';
    };

    const flattenSeats = (eventState) => {
        const layout = isObject(eventState) && isObject(eventState.layout)
            ? eventState.layout
            : {};
        const seats = [];

        (Array.isArray(layout.tables) ? layout.tables : []).forEach((table) => {
            if (!isObject(table)) {
                return;
            }

            (Array.isArray(table.seats) ? table.seats : []).forEach((seat) => {
                if (isObject(seat) && normalizePositiveInteger(seat.id) > 0) {
                    seats.push(seat);
                }
            });
        });

        return seats;
    };

    const indexSeatElements = (eventElement, config) => {
        const seatElements = new Map();

        if (!isObject(config.selectors)) {
            return seatElements;
        }

        selectAll(eventElement, config.selectors.seat).forEach((element) => {
            const seatId = getSeatId(element, config);

            if (seatId > 0) {
                seatElements.set(seatId, element);
            }
        });

        return seatElements;
    };

    const findSeatElement = (eventElement, seatId, config, seatElements = null) => {
        const elements = seatElements instanceof Map
            ? seatElements
            : indexSeatElements(eventElement, config);

        return elements.get(seatId) || null;
    };

    const updateSeatMark = (seatElement, status) => {
        const label = seatElement.querySelector('.cmp-seat-selection-seat__label');

        if (!(label instanceof HTMLLabelElement)) {
            return;
        }

        let mark = label.querySelector('.cmp-seat-selection-seat__mark');

        if (status === 'available') {
            if (mark) {
                mark.remove();
            }

            return;
        }

        if (!(mark instanceof HTMLElement)) {
            mark = document.createElement('span');
            mark.className = 'cmp-seat-selection-seat__mark';
            mark.setAttribute('aria-hidden', 'true');
            label.append(mark);
        }

        const markText = status === 'selected' ? '✓' : '×';

        if (mark.textContent !== markText) {
            mark.textContent = markText;
        }
    };

    const updateSeats = (eventElement, eventState, config, seatElements) => {
        flattenSeats(eventState).forEach((seat) => {
            const seatId = normalizePositiveInteger(seat.id);
            const seatElement = findSeatElement(eventElement, seatId, config, seatElements);

            if (!(seatElement instanceof Element)) {
                return;
            }

            const status = ['available', 'selected', 'unavailable'].includes(seat.status)
                ? seat.status
                : 'unavailable';
            const input = seatElement.querySelector('input[type="checkbox"]');

            replaceModifierClass(seatElement, 'cmp-seat-selection-seat--', status);
            updateSeatMark(seatElement, status);

            if (input instanceof HTMLInputElement) {
                const checked = status === 'selected';
                const disabled = !eventState.ready || status === 'unavailable';

                if (input.checked !== checked) {
                    input.checked = checked;
                }

                if (input.disabled !== disabled) {
                    input.disabled = disabled;
                }
            }
        });
    };

    const getSelectedSeats = (eventState) => {
        if (Array.isArray(eventState.selectedSeats)) {
            const selected = eventState.selectedSeats.filter(
                (seat) => isObject(seat) && normalizePositiveInteger(seat.id) > 0
            );

            if (selected.length > 0) {
                return selected;
            }
        }

        return flattenSeats(eventState).filter((seat) => seat.status === 'selected');
    };

    const getSelectionFeedback = (eventState, interaction, config) => {
        if (!isObject(eventState) || !isObject(interaction) || !isObject(config.strings)) {
            return '';
        }

        const seatId = normalizePositiveInteger(interaction.seatId);
        const seat = flattenSeats(eventState).find(
            (candidate) => normalizePositiveInteger(candidate.id) === seatId
        );

        if (!isObject(seat) || typeof seat.label !== 'string' || seat.label.trim() === '') {
            return '';
        }

        const template = interaction.selected
            ? config.strings.seatSelected
            : config.strings.seatDeselected;

        return replaceTemplateValues(template, [
            seat.label,
            Math.max(0, Number(eventState.selectedCount) || 0),
            Math.max(0, Number(eventState.requiredCount) || 0),
        ]);
    };

    const renderSelectedSeats = (eventElement, eventState, config, seatElements) => {
        if (!isObject(config.selectors) || !isObject(config.attributes)) {
            return;
        }

        const container = select(eventElement, config.selectors.selectedSeats);

        if (!(container instanceof HTMLElement)) {
            return;
        }

        const selectedSeats = getSelectedSeats(eventState);
        const removeAttribute = getSeatRemoveAttribute(config);
        const strings = isObject(config.strings) ? config.strings : {};

        const renderedSeats = selectedSeats.map((seat) => {
            const seatId = normalizePositiveInteger(seat.id);
            const seatElement = findSeatElement(eventElement, seatId, config, seatElements);
            const input = seatElement instanceof Element
                ? seatElement.querySelector('input[type="checkbox"]')
                : null;

            if (!(input instanceof HTMLInputElement)) {
                return null;
            }

            return {
                id: seatId,
                inputId: input.id,
                label: typeof seat.label === 'string' ? seat.label : '',
            };
        }).filter((seat) => seat !== null);
        const currentItems = Array.from(container.children);
        const isCurrent = currentItems.length === renderedSeats.length
            && renderedSeats.every((seat, index) => {
                const control = currentItems[index].querySelector(config.selectors.seatRemove);
                const text = control instanceof Element ? control.querySelector('span') : null;

                return control instanceof HTMLButtonElement
                    && getElementId(control, removeAttribute) === seat.id
                    && control.getAttribute('aria-controls') === seat.inputId
                    && text instanceof HTMLElement
                    && text.textContent === seat.label;
            });

        if (!isCurrent) {
            const fragment = document.createDocumentFragment();

            renderedSeats.forEach((seat) => {
                const item = document.createElement('li');
                const button = document.createElement('button');
                const text = document.createElement('span');
                const icon = document.createElement('span');

                item.className = 'cmp-seat-selection-selected__item';
                button.className = 'cmp-seat-selection-selected__remove';
                button.type = 'button';
                button.setAttribute('aria-controls', seat.inputId);
                const removeLabel = replaceTemplateValues(strings.removeSeat || '', [seat.label]);

                if (removeLabel !== '') {
                    button.setAttribute('aria-label', removeLabel);
                }

                if (isValidDataAttribute(removeAttribute)) {
                    button.setAttribute(removeAttribute, String(seat.id));
                }

                text.textContent = seat.label;
                icon.className = 'cmp-seat-selection-selected__remove-icon';
                icon.setAttribute('uk-icon', 'icon: close');
                icon.setAttribute('aria-hidden', 'true');
                button.append(text, icon);
                item.append(button);
                fragment.append(item);
            });

            container.replaceChildren(fragment);

            if (window.UIkit && typeof window.UIkit.update === 'function') {
                window.UIkit.update(container);
            }
        }

        const empty = container.closest('.cmp-seat-selection-selected')
            ?.querySelector('.cmp-seat-selection-selected__empty');

        if (empty instanceof HTMLElement) {
            const hidden = renderedSeats.length > 0;

            if (empty.hidden !== hidden) {
                empty.hidden = hidden;
            }
        }
    };

    const updateSubmitState = (eventElement, eventState) => {
        const button = eventElement.querySelector('button[value="ticketseats.assign"]');

        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const required = Math.max(0, Number(eventState.requiredCount) || 0);
        const selected = Math.max(0, Number(eventState.selectedCount) || 0);
        const disabled = !eventState.ready || required === 0 || selected !== required;

        if (button.disabled !== disabled) {
            button.disabled = disabled;
        }
    };

    const syncSubmitStateFromDom = (eventElement, config) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const button = eventElement.querySelector('button[value="ticketseats.assign"]');
        const form = select(eventElement, config.selectors.eventForm);

        if (!(button instanceof HTMLButtonElement) || !(form instanceof HTMLFormElement)) {
            return;
        }

        const required = getRequiredCount(eventElement, config);
        const selected = form.querySelectorAll('input[type="checkbox"]:checked').length;
        const hasAvailableSeat = Array.from(form.querySelectorAll('input[type="checkbox"]'))
            .some((input) => !input.disabled);
        const disabled = required === 0 || selected !== required || !hasAvailableSeat;

        if (button.disabled !== disabled) {
            button.disabled = disabled;
        }
    };

    const updateContinue = (root, allComplete, config) => {
        if (!isObject(config.selectors)) {
            return;
        }

        const control = select(root, config.selectors.continue);

        if (!(control instanceof HTMLAnchorElement)) {
            return;
        }

        const enabled = allComplete === true
            && typeof config.continueUrl === 'string'
            && config.continueUrl.trim() !== '';

        if (enabled) {
            control.href = config.continueUrl;
            control.removeAttribute('aria-disabled');
            control.removeAttribute('tabindex');
        } else {
            control.removeAttribute('href');
            control.setAttribute('aria-disabled', 'true');
            control.tabIndex = -1;
        }
    };

    const updateEvent = (root, eventState, config) => {
        if (!isObject(eventState) || !isObject(config.selectors)) {
            return null;
        }

        const eventId = normalizePositiveInteger(eventState.id);
        const eventElement = selectAll(root, config.selectors.event).find(
            (element) => getEventId(element, config) === eventId
        );

        if (!(eventElement instanceof Element)) {
            return null;
        }

        const status = getEventStatus(eventState);
        const statusLabel = getStatusLabel(status, config);
        const selectedCount = Math.max(0, Number(eventState.selectedCount) || 0);
        const requiredCount = Math.max(0, Number(eventState.requiredCount) || 0);
        const requiredAttribute = isObject(config.attributes)
            ? config.attributes.requiredCount
            : '';
        const seatElements = indexSeatElements(eventElement, config);

        replaceModifierClass(eventElement, 'cmp-seat-selection-event--', status);

        if (isValidDataAttribute(requiredAttribute)
            && eventElement.getAttribute(requiredAttribute) !== String(requiredCount)) {
            eventElement.setAttribute(requiredAttribute, String(requiredCount));
        }

        selectAll(eventElement, config.selectors.eventCount).forEach((element) => {
            if (element.textContent !== String(selectedCount)) {
                element.textContent = String(selectedCount);
            }
        });
        eventElement.querySelectorAll(
            '.cmp-seat-selection-event__required-count'
        ).forEach((element) => {
            if (element.textContent !== String(requiredCount)) {
                element.textContent = String(requiredCount);
            }
        });
        selectAll(eventElement, config.selectors.eventStatus).forEach((element) => {
            if (statusLabel !== '' && element.textContent !== statusLabel) {
                element.textContent = statusLabel;
            }
        });

        const statusDot = eventElement.querySelector('.cmp-ticket-selection-event__status-dot');
        const completeIcon = eventElement.querySelector(
            '.cmp-seat-selection-event__complete-icon'
        );
        replaceModifierClass(statusDot, 'cmp-seat-selection-event__status-dot--', status);

        if (completeIcon instanceof HTMLElement && completeIcon.hidden !== (status !== 'complete')) {
            completeIcon.hidden = status !== 'complete';
        }

        const suggest = select(eventElement, config.selectors.suggest);

        if (suggest instanceof HTMLButtonElement && suggest.disabled !== !eventState.ready) {
            suggest.disabled = !eventState.ready;
        }

        updateSeats(eventElement, eventState, config, seatElements);
        renderSelectedSeats(eventElement, eventState, config, seatElements);
        updateSubmitState(eventElement, eventState);
        setEventMessage(eventElement, eventState.message || '', config);

        return eventElement;
    };

    const updateFromResponse = (root, data, config) => {
        if (!isObject(data)) {
            return null;
        }

        const eventElement = isObject(data.event)
            ? updateEvent(root, data.event, config)
            : null;

        if (Object.prototype.hasOwnProperty.call(data, 'cartRevision')) {
            updateRevisionFields(root, data.cartRevision, config);
        }

        if (Object.prototype.hasOwnProperty.call(data, 'allComplete')) {
            updateContinue(root, data.allComplete, config);
        }

        return eventElement;
    };

    const captureFocus = (scope, config, submitter = null) => {
        const active = document.activeElement;
        let seatElement = active instanceof Element && isObject(config.selectors)
            ? active.closest(config.selectors.seat)
            : null;

        if (!(seatElement instanceof Element) && active instanceof HTMLLabelElement
            && active.htmlFor !== '') {
            const input = document.getElementById(active.htmlFor);

            if (input instanceof HTMLInputElement && scope.contains(input)) {
                seatElement = input.closest(config.selectors.seat);
            }
        }

        let seatId = getSeatId(seatElement, config);

        if (seatId < 1 && active instanceof Element && isObject(config.selectors)
            && isObject(config.attributes)) {
            const remove = active.closest(config.selectors.seatRemove);

            if (remove instanceof Element && scope.contains(remove)) {
                seatId = getElementId(remove, getSeatRemoveAttribute(config));
            }
        }

        return {
            seatId,
            submitter: submitter instanceof HTMLElement ? submitter : null,
            active: active instanceof HTMLElement && scope.contains(active) ? active : null,
            within: active instanceof HTMLElement && scope.contains(active),
        };
    };

    const restoreFocus = (eventElement, focusState, config) => {
        if (!(eventElement instanceof Element) || !isObject(focusState)) {
            return;
        }

        if (!focusState.within && !(focusState.submitter instanceof HTMLElement)) {
            return;
        }

        if (focusState.seatId > 0) {
            const seatElement = findSeatElement(eventElement, focusState.seatId, config);
            const input = seatElement instanceof Element
                ? seatElement.querySelector('input[type="checkbox"]')
                : null;

            if (input instanceof HTMLInputElement && !input.disabled) {
                input.focus({ preventScroll: true });

                return;
            }
        }

        if (focusState.submitter instanceof HTMLElement && focusState.submitter.isConnected
            && !(focusState.submitter instanceof HTMLButtonElement
                && focusState.submitter.disabled)) {
            focusState.submitter.focus({ preventScroll: true });

            return;
        }

        if (focusState.active instanceof HTMLElement && focusState.active.isConnected
            && !(focusState.active instanceof HTMLButtonElement && focusState.active.disabled)
            && !(focusState.active instanceof HTMLInputElement && focusState.active.disabled)
            && focusState.active.getAttribute('aria-disabled') !== 'true') {
            focusState.active.focus({ preventScroll: true });

            return;
        }

        const toggle = eventElement.querySelector('.cmp-seat-selection-event__toggle');

        if (toggle instanceof HTMLElement) {
            toggle.focus({ preventScroll: true });
        }
    };

    const restoreSeatInputsFromClasses = (eventElement, config) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        selectAll(eventElement, config.selectors.seat).forEach((seatElement) => {
            const input = seatElement.querySelector('input[type="checkbox"]');

            if (input instanceof HTMLInputElement) {
                input.checked = seatElement.classList.contains(
                    'cmp-seat-selection-seat--selected'
                );
            }
        });
    };

    const submitSelection = async (
        form,
        endpoint,
        task,
        root,
        config,
        submitter = null,
        interaction = null
    ) => {
        if (!(form instanceof HTMLFormElement) || form.getAttribute('aria-busy') === 'true') {
            return;
        }

        const eventElement = isObject(config.selectors)
            ? form.closest(config.selectors.event)
            : null;
        const focusState = captureFocus(form, config, submitter);
        const body = buildMutationBody(form, config);
        body.set('task', task);
        setBusy(form, true);
        let busy = true;

        try {
            const result = await request(endpoint, body, config);
            const data = getPayloadData(result.payload);

            setBusy(form, false);
            busy = false;
            const updatedEvent = updateFromResponse(root, data, config) || eventElement;
            const message = typeof data.message === 'string' && data.message !== ''
                ? data.message
                : result.payload.message || '';
            const feedback = getSelectionFeedback(data.event, interaction, config);

            if (result.conflict) {
                setEventMessage(updatedEvent, message, config);
            } else {
                announce(root, feedback || message);
            }

            restoreFocus(updatedEvent, focusState, config);
        } catch (error) {
            if (busy) {
                setBusy(form, false);
                busy = false;
            }

            restoreSeatInputsFromClasses(eventElement, config);
            syncSubmitStateFromDom(eventElement, config);
            const message = error instanceof Error && error.message !== ''
                ? error.message
                : (isObject(config.strings) ? config.strings.requestFailed || '' : '');
            setEventMessage(eventElement, message, config);
            announce(root, message);
            restoreFocus(eventElement, focusState, config);
        } finally {
            if (busy) {
                setBusy(form, false);
            }
        }
    };

    const getZoomConfig = (config) => {
        if (!isObject(config.zoom)) {
            return null;
        }

        const minimum = Number(config.zoom.min);
        const maximum = Number(config.zoom.max);
        const step = Number(config.zoom.step);

        if (!Number.isFinite(minimum) || !Number.isFinite(maximum)
            || !Number.isFinite(step) || minimum <= 0 || maximum < minimum || step <= 0) {
            return null;
        }

        return { minimum, maximum, step };
    };

    const clampZoom = (value, zoomConfig) => Math.min(
        zoomConfig.maximum,
        Math.max(zoomConfig.minimum, value)
    );

    const getCanvasSize = (shell) => {
        const styles = window.getComputedStyle(shell);
        const width = Number.parseFloat(styles.getPropertyValue('--cmp-seat-layout-width'));
        const height = Number.parseFloat(styles.getPropertyValue('--cmp-seat-layout-height'));

        return {
            width: Number.isFinite(width) && width > 0 ? width : 0,
            height: Number.isFinite(height) && height > 0 ? height : 0,
        };
    };

    const getViewportContentSize = (viewport) => {
        const styles = window.getComputedStyle(viewport);
        const paddingInline = Number.parseFloat(styles.paddingInlineStart)
            + Number.parseFloat(styles.paddingInlineEnd);
        const paddingBlock = Number.parseFloat(styles.paddingBlockStart)
            + Number.parseFloat(styles.paddingBlockEnd);

        return {
            width: Math.max(1, viewport.clientWidth - (Number.isFinite(paddingInline) ? paddingInline : 0)),
            height: Math.max(1, viewport.clientHeight - (Number.isFinite(paddingBlock) ? paddingBlock : 0)),
        };
    };

    const updateZoomButtons = (eventElement, zoom, zoomConfig, config) => {
        if (!isObject(config.selectors)) {
            return;
        }

        const out = select(eventElement, config.selectors.zoomOut);
        const input = select(eventElement, config.selectors.zoomIn);

        if (out instanceof HTMLButtonElement) {
            out.disabled = zoom <= zoomConfig.minimum;
        }

        if (input instanceof HTMLButtonElement) {
            input.disabled = zoom >= zoomConfig.maximum;
        }
    };

    const setZoom = (eventElement, requestedZoom, config, preserveCenter = true) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const viewport = select(eventElement, config.selectors.zoomViewport);
        const shell = select(eventElement, config.selectors.zoomCanvas);
        const zoomConfig = getZoomConfig(config);

        if (!(viewport instanceof HTMLElement) || !(shell instanceof HTMLElement) || !zoomConfig) {
            return;
        }

        const size = getCanvasSize(shell);

        if (size.width === 0 || size.height === 0) {
            return;
        }

        const state = zoomStates.get(shell) || {
            current: 1,
            initial: 1,
            minimum: zoomConfig.minimum,
            measured: false,
        };
        const minimum = Number.isFinite(state.minimum) && state.minimum > 0
            ? Math.min(zoomConfig.maximum, state.minimum)
            : zoomConfig.minimum;
        const effectiveZoomConfig = { ...zoomConfig, minimum };
        const oldZoom = state.current;
        const zoom = clampZoom(requestedZoom, effectiveZoomConfig);
        const centerX = (viewport.scrollLeft + (viewport.clientWidth / 2)) / oldZoom;
        const centerY = (viewport.scrollTop + (viewport.clientHeight / 2)) / oldZoom;

        state.current = zoom;
        zoomStates.set(shell, state);
        shell.style.setProperty('--cmp-seat-zoom', String(zoom));
        shell.style.inlineSize = `${size.width * zoom}px`;
        shell.style.blockSize = `${size.height * zoom}px`;
        updateZoomButtons(eventElement, zoom, effectiveZoomConfig, config);

        if (preserveCenter) {
            window.requestAnimationFrame(() => {
                viewport.scrollLeft = Math.max(0, (centerX * zoom) - (viewport.clientWidth / 2));
                viewport.scrollTop = Math.max(0, (centerY * zoom) - (viewport.clientHeight / 2));
            });
        }
    };

    const fitZoom = (eventElement, config) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const viewport = select(eventElement, config.selectors.zoomViewport);
        const shell = select(eventElement, config.selectors.zoomCanvas);
        const zoomConfig = getZoomConfig(config);

        if (!(viewport instanceof HTMLElement) || !(shell instanceof HTMLElement) || !zoomConfig
            || viewport.clientWidth === 0 || viewport.clientHeight === 0) {
            return;
        }

        const canvasSize = getCanvasSize(shell);

        if (canvasSize.width === 0 || canvasSize.height === 0) {
            return;
        }

        const viewportSize = getViewportContentSize(viewport);
        const initial = Math.min(
            1,
            viewportSize.width / canvasSize.width,
            viewportSize.height / canvasSize.height
        );

        if (!Number.isFinite(initial) || initial <= 0) {
            return;
        }

        const state = zoomStates.get(shell) || {
            current: 1,
            initial: 1,
            minimum: zoomConfig.minimum,
            measured: false,
        };

        state.initial = initial;
        state.minimum = Math.min(zoomConfig.minimum, initial);
        state.measured = true;
        zoomStates.set(shell, state);
        setZoom(eventElement, initial, config, false);
        viewport.scrollLeft = 0;
        viewport.scrollTop = 0;
    };

    const initializeZoom = (eventElement, config) => {
        if (!isObject(config.selectors)) {
            return;
        }

        const viewport = select(eventElement, config.selectors.zoomViewport);
        const shell = select(eventElement, config.selectors.zoomCanvas);
        const zoomConfig = getZoomConfig(config);

        if (!(viewport instanceof HTMLElement) || !(shell instanceof HTMLElement) || !zoomConfig) {
            return;
        }

        const size = getCanvasSize(shell);

        if (size.width === 0 || size.height === 0) {
            return;
        }

        let state = zoomStates.get(shell);
        const canMeasure = viewport.clientWidth > 0 && viewport.clientHeight > 0;

        if (!state) {
            const fallback = clampZoom(1, zoomConfig);
            state = {
                current: fallback,
                initial: fallback,
                minimum: zoomConfig.minimum,
                measured: false,
            };
            zoomStates.set(shell, state);
        }

        if (canMeasure && !state.measured) {
            fitZoom(eventElement, config);
        }

        if (initializedViewports.has(viewport)) {
            return;
        }

        initializedViewports.add(viewport);
        const pointers = new Map();
        let pinchDistance = 0;
        let pinchZoom = state.current;

        const resetPinch = () => {
            if (pointers.size < 2) {
                pinchDistance = 0;
            }
        };

        viewport.addEventListener('wheel', (event) => {
            if (!event.ctrlKey) {
                return;
            }

            event.preventDefault();
            const state = zoomStates.get(shell);

            if (state) {
                setZoom(
                    eventElement,
                    state.current + (event.deltaY < 0 ? zoomConfig.step : -zoomConfig.step),
                    config
                );
            }
        }, { passive: false });

        viewport.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'touch') {
                return;
            }

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size === 2) {
                const points = Array.from(pointers.values());
                pinchDistance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
                pinchZoom = zoomStates.get(shell)?.current || state.current;
            }
        });

        viewport.addEventListener('pointermove', (event) => {
            if (!pointers.has(event.pointerId)) {
                return;
            }

            pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

            if (pointers.size !== 2 || pinchDistance <= 0) {
                return;
            }

            const points = Array.from(pointers.values());
            const distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);

            event.preventDefault();
            setZoom(eventElement, pinchZoom * (distance / pinchDistance), config);
        }, { passive: false });

        ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
            viewport.addEventListener(eventName, (event) => {
                pointers.delete(event.pointerId);
                resetPinch();
            });
        });
    };

    const scheduleZoomFit = () => {
        if (zoomFitFrame !== null) {
            return;
        }

        zoomFitFrame = window.requestAnimationFrame(() => {
            zoomFitFrame = null;
            runtime.zoomFitFrame = null;
            const config = getConfig();

            if (!hasValidSelectors(config)) {
                return;
            }

            instances.forEach((root) => {
                if (!(root instanceof Element) || !root.isConnected) {
                    instances.delete(root);

                    return;
                }

                selectAll(root, config.selectors.event).forEach((eventElement) => {
                    fitZoom(eventElement, config);
                });
            });
        });
        runtime.zoomFitFrame = zoomFitFrame;
    };

    const handleZoomControl = (target, eventElement, config) => {
        if (!(target instanceof Element) || !isObject(config.selectors)) {
            return false;
        }

        const shell = select(eventElement, config.selectors.zoomCanvas);
        const zoomConfig = getZoomConfig(config);

        if (!(shell instanceof HTMLElement) || !zoomConfig) {
            return false;
        }

        const state = zoomStates.get(shell);

        if (!state) {
            return false;
        }

        if (target.closest(config.selectors.zoomOut)) {
            setZoom(eventElement, state.current - zoomConfig.step, config);

            return true;
        }

        if (target.closest(config.selectors.zoomIn)) {
            setZoom(eventElement, state.current + zoomConfig.step, config);

            return true;
        }

        if (target.closest(config.selectors.zoomReset)) {
            setZoom(eventElement, state.initial, config);

            return true;
        }

        return false;
    };

    const updateTableFocusNavigation = (links, eventElement, config) => {
        if (!(links instanceof HTMLElement) || !(eventElement instanceof Element)
            || !links.isConnected || !eventElement.isConnected
            || !eventElement.contains(links) || !isObject(config.selectors)) {
            return false;
        }

        const previous = select(eventElement, config.selectors.tableFocusPrevious);
        const next = select(eventElement, config.selectors.tableFocusNext);

        if (!(previous instanceof HTMLButtonElement) || !(next instanceof HTMLButtonElement)) {
            return false;
        }

        const previousWasHidden = previous.hidden;
        const nextWasHidden = next.hidden;

        const hasOverflow = links.clientWidth > 0
            && links.scrollWidth > links.clientWidth + 1;

        if (!hasOverflow) {
            previous.hidden = true;
            next.hidden = true;

            return previous.hidden !== previousWasHidden || next.hidden !== nextWasHidden;
        }

        const viewportRect = links.getBoundingClientRect();
        const linkElements = selectAll(links, config.selectors.tableFocus);
        const tolerance = 1;
        let canScrollLeft = false;
        let canScrollRight = false;

        linkElements.forEach((link) => {
            const linkRect = link.getBoundingClientRect();

            canScrollLeft = canScrollLeft || linkRect.left < viewportRect.left - tolerance;
            canScrollRight = canScrollRight || linkRect.right > viewportRect.right + tolerance;
        });

        previous.hidden = !canScrollLeft;
        next.hidden = !canScrollRight;

        return previous.hidden !== previousWasHidden || next.hidden !== nextWasHidden;
    };

    const scheduleTableFocusNavigationUpdate = (links, allowFollowUp = true) => {
        if (!(links instanceof HTMLElement) || tableFocusUpdateFrames.has(links)) {
            return;
        }

        const frame = window.requestAnimationFrame(() => {
            tableFocusUpdateFrames.delete(links);
            const currentConfig = getConfig();

            if (!links.isConnected || !hasValidSelectors(currentConfig)) {
                return;
            }

            const currentEventElement = links.closest(currentConfig.selectors.event);

            if (!(currentEventElement instanceof Element) || !currentEventElement.isConnected) {
                return;
            }

            const visibilityChanged = updateTableFocusNavigation(
                links,
                currentEventElement,
                currentConfig
            );

            if (visibilityChanged && allowFollowUp) {
                scheduleTableFocusNavigationUpdate(links, false);
            }
        });

        tableFocusUpdateFrames.set(links, frame);
    };

    const getTableFocusResizeObserver = () => {
        if (tableFocusResizeObserver && typeof tableFocusResizeObserver.observe === 'function') {
            return tableFocusResizeObserver;
        }

        if (typeof window.ResizeObserver !== 'function') {
            return null;
        }

        tableFocusResizeObserver = new window.ResizeObserver((entries) => {
            const config = getConfig();

            if (!hasValidSelectors(config)) {
                return;
            }

            entries.forEach((entry) => {
                const target = entry.target;
                const links = target instanceof Element
                    ? (target.matches(config.selectors.tableFocusLinks)
                        ? target
                        : target.closest(config.selectors.tableFocusLinks))
                    : null;
                const eventElement = links instanceof Element
                    ? links.closest(config.selectors.event)
                    : null;

                if (links instanceof HTMLElement && eventElement instanceof Element) {
                    scheduleTableFocusNavigationUpdate(links);
                }
            });
        });
        runtime.tableFocusResizeObserver = tableFocusResizeObserver;

        return tableFocusResizeObserver;
    };

    const initializeTableFocusNavigation = (eventElement, config) => {
        if (!(eventElement instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        const links = select(eventElement, config.selectors.tableFocusLinks);

        if (!(links instanceof HTMLElement)) {
            return;
        }

        if (!initializedTableFocusLinks.has(links)) {
            initializedTableFocusLinks.add(links);
            links.addEventListener('scroll', () => {
                const currentConfig = getConfig();

                if (hasValidSelectors(currentConfig)) {
                    scheduleTableFocusNavigationUpdate(links);
                }
            }, { passive: true });
        }

        const resizeObserver = getTableFocusResizeObserver();

        if (resizeObserver) {
            resizeObserver.observe(links);
            selectAll(links, config.selectors.tableFocus).forEach((link) => {
                resizeObserver.observe(link);
            });
        }

        scheduleTableFocusNavigationUpdate(links);
    };

    const refreshTableFocusNavigations = (root, config) => {
        if (!(root instanceof Element) || !isObject(config.selectors)) {
            return;
        }

        selectAll(root, config.selectors.event).forEach((eventElement) => {
            initializeTableFocusNavigation(eventElement, config);
        });
    };

    const handleTableFocusControl = (target, eventElement, config) => {
        if (!(target instanceof Element) || !(eventElement instanceof Element)
            || !isObject(config.selectors)) {
            return false;
        }

        const previous = target.closest(config.selectors.tableFocusPrevious);
        const next = target.closest(config.selectors.tableFocusNext);
        const control = previous || next;
        const links = select(eventElement, config.selectors.tableFocusLinks);

        if (!(control instanceof HTMLButtonElement) || !(links instanceof HTMLElement)) {
            return false;
        }

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const direction = previous ? -1 : 1;

        links.scrollBy({
            left: direction * Math.max(1, links.clientWidth * 0.75),
            behavior: reducedMotion ? 'auto' : 'smooth',
        });

        return true;
    };

    const focusTable = (link, eventElement, config) => {
        if (!(link instanceof HTMLAnchorElement) || !isObject(config.selectors)) {
            return;
        }

        const viewport = select(eventElement, config.selectors.zoomViewport);
        const shell = select(eventElement, config.selectors.zoomCanvas);

        if (!(viewport instanceof HTMLElement) || !(shell instanceof HTMLElement)) {
            return;
        }

        let tableId = '';

        try {
            tableId = decodeURIComponent(link.hash.slice(1));
        } catch (error) {
            return;
        }

        if (!/^cmp-seat-selection-table-[A-Za-z0-9_-]+$/.test(tableId)) {
            return;
        }

        const table = document.getElementById(tableId);
        const state = zoomStates.get(shell);

        if (!(table instanceof HTMLElement) || !eventElement.contains(table) || !state) {
            return;
        }

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const centerX = (table.offsetLeft + (table.offsetWidth / 2)) * state.current;
        const centerY = (table.offsetTop + (table.offsetHeight / 2)) * state.current;

        viewport.scrollTo({
            left: Math.max(0, centerX - (viewport.clientWidth / 2)),
            top: Math.max(0, centerY - (viewport.clientHeight / 2)),
            behavior: reducedMotion ? 'auto' : 'smooth',
        });
        table.focus({ preventScroll: true });
    };

    const refreshEvent = async (root, eventElement, config) => {
        if (!(eventElement instanceof Element) || !hasValidSelectors(config)
            || !isObject(config.endpoints)) {
            return;
        }

        const form = select(eventElement, config.selectors.eventForm);

        if (!(form instanceof HTMLFormElement) || form.getAttribute('aria-busy') === 'true') {
            return;
        }

        const focusState = captureFocus(eventElement, config);
        const result = await requestState(
            config.endpoints.state || '',
            getEventId(eventElement, config),
            config
        );
        const data = getPayloadData(result.payload);
        const updatedEvent = updateFromResponse(root, data, config) || eventElement;
        const message = typeof data.message === 'string' ? data.message : '';

        if (result.conflict && message !== '') {
            setEventMessage(updatedEvent, message, config);
        }

        restoreFocus(updatedEvent, focusState, config);
    };

    const pollVisibleEvents = async () => {
        if (document.hidden) {
            return;
        }

        const config = getConfig();

        if (!hasValidSelectors(config)) {
            return;
        }

        const jobs = [];

        instances.forEach((root) => {
            if (!(root instanceof Element) || !root.isConnected) {
                instances.delete(root);

                return;
            }

            selectAll(root, config.selectors.event).forEach((eventElement) => {
                if (eventElement.classList.contains('uk-open')) {
                    jobs.push(refreshEvent(root, eventElement, config));
                }
            });
        });

        await Promise.allSettled(jobs);
    };

    const schedulePolling = () => {
        if (runtime.pollTimer) {
            window.clearTimeout(runtime.pollTimer);
            runtime.pollTimer = null;
        }

        const config = getConfig();
        const interval = Number(config.pollInterval);

        if (document.hidden || !Number.isFinite(interval) || interval <= 0 || instances.size === 0) {
            return;
        }

        runtime.pollTimer = window.setTimeout(async () => {
            runtime.pollTimer = null;
            await pollVisibleEvents();
            schedulePolling();
        }, interval);
    };

    const handleChange = (event, root, config) => {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.type !== 'checkbox'
            || !hasValidSelectors(config) || !input.closest(config.selectors.seat)) {
            return;
        }

        const form = input.closest(config.selectors.eventForm);
        const eventElement = input.closest(config.selectors.event);

        if (!(form instanceof HTMLFormElement) || !(eventElement instanceof Element)) {
            return;
        }

        const required = getRequiredCount(eventElement, config);
        const selected = form.querySelectorAll('input[type="checkbox"]:checked').length;

        if (input.checked && selected > required) {
            input.checked = false;
            const message = replaceTemplateValues(
                isObject(config.strings) ? config.strings.selectionLimit || '' : '',
                [required]
            );
            announce(root, message);
            input.focus({ preventScroll: true });

            return;
        }

        submitSelection(
            form,
            isObject(config.endpoints) ? config.endpoints.assign || '' : '',
            'ticketseats.assign',
            root,
            config,
            null,
            {
                seatId: getSeatId(input.closest(config.selectors.seat), config),
                selected: input.checked,
            }
        );
    };

    const handleSubmit = (event, root, config) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !hasValidSelectors(config)
            || !form.matches(config.selectors.eventForm)) {
            return;
        }

        const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
        const task = submitter && submitter.value === 'ticketseats.suggest'
            ? 'ticketseats.suggest'
            : 'ticketseats.assign';
        const endpoint = isObject(config.endpoints)
            ? (task === 'ticketseats.suggest' ? config.endpoints.suggest : config.endpoints.assign)
            : '';

        event.preventDefault();
        submitSelection(form, endpoint || '', task, root, config, submitter);
    };

    const handleSeatRemove = (target, eventElement, config) => {
        if (!(target instanceof Element) || !isObject(config.selectors)
            || !isObject(config.attributes)) {
            return false;
        }

        const control = target.closest(config.selectors.seatRemove);

        if (!(control instanceof HTMLButtonElement)) {
            return false;
        }

        const seatId = getElementId(control, getSeatRemoveAttribute(config));
        const seatElement = findSeatElement(eventElement, seatId, config);
        const input = seatElement instanceof Element
            ? seatElement.querySelector('input[type="checkbox"]')
            : null;

        if (input instanceof HTMLInputElement && input.checked && !input.disabled) {
            input.checked = false;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return true;
    };

    const handleClick = (event, root, config) => {
        if (!(event.target instanceof Element) || !hasValidSelectors(config)) {
            return;
        }

        const eventElement = event.target.closest(config.selectors.event);

        if (!(eventElement instanceof Element)) {
            return;
        }

        if (handleSeatRemove(event.target, eventElement, config)) {
            event.preventDefault();

            return;
        }

        if (handleTableFocusControl(event.target, eventElement, config)) {
            event.preventDefault();

            return;
        }

        if (handleZoomControl(event.target, eventElement, config)) {
            event.preventDefault();

            return;
        }

        const tableFocus = event.target.closest(config.selectors.tableFocus);

        if (tableFocus instanceof HTMLAnchorElement) {
            event.preventDefault();
            focusTable(tableFocus, eventElement, config);
        }
    };

    const initializeRoot = (root, config) => {
        if (!(root instanceof Element) || !hasValidSelectors(config)) {
            return;
        }

        selectAll(root, config.selectors.event).forEach((eventElement) => {
            initializeZoom(eventElement, config);
            initializeTableFocusNavigation(eventElement, config);
            syncSubmitStateFromDom(eventElement, config);
        });

        if (instances.has(root)) {
            refreshTableFocusNavigations(root, config);

            return;
        }

        instances.add(root);
        root.addEventListener('change', (event) => handleChange(event, root, getConfig()));
        root.addEventListener('submit', (event) => handleSubmit(event, root, getConfig()));
        root.addEventListener('click', (event) => handleClick(event, root, getConfig()));
        root.addEventListener('shown', (event) => {
            const currentConfig = getConfig();
            if (!hasValidSelectors(currentConfig)) {
                return;
            }

            const eventElement = event.target instanceof Element
                ? event.target.closest(currentConfig.selectors.event)
                : null;

            if (eventElement) {
                initializeZoom(eventElement, currentConfig);
                fitZoom(eventElement, currentConfig);
                initializeTableFocusNavigation(eventElement, currentConfig);
                refreshEvent(root, eventElement, currentConfig).catch(() => {});
            }
        });
        root.classList.add('cmp-seat-selection--enhanced');
    };

    runtime.init = (context) => {
        const config = getConfig();

        if (!hasValidSelectors(config)) {
            return;
        }

        collectRoots(context, config.rootSelector).forEach((root) => initializeRoot(root, config));
        schedulePolling();
    };

    if (!runtime.lifecycleListenersRegistered) {
        document.addEventListener('joomla:updated', (event) => runtime.init(event.target));
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (runtime.pollTimer) {
                    window.clearTimeout(runtime.pollTimer);
                    runtime.pollTimer = null;
                }

                return;
            }

            pollVisibleEvents().finally(schedulePolling);
        });
        window.addEventListener('pageshow', () => {
            runtime.init(document);
            scheduleZoomFit();
            pollVisibleEvents().finally(schedulePolling);
        });
        window.addEventListener('resize', () => {
            const config = getConfig();

            if (!hasValidSelectors(config)) {
                return;
            }

            instances.forEach((root) => refreshTableFocusNavigations(root, config));
            scheduleZoomFit();
        });
        runtime.lifecycleListenersRegistered = true;
    }

    runtime.instances = instances;
    runtime.zoomStates = zoomStates;
    runtime.initializedViewports = initializedViewports;
    runtime.initializedTableFocusLinks = initializedTableFocusLinks;
    runtime.tableFocusUpdateFrames = tableFocusUpdateFrames;
    runtime.tableFocusResizeObserver = tableFocusResizeObserver;
    runtime.zoomFitFrame = zoomFitFrame;
    window.CopyMyPageSeatSelection = runtime;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => runtime.init(document), { once: true });
    } else {
        runtime.init(document);
    }
})(window, document, window.Joomla);
