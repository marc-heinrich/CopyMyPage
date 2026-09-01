/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

window.CopyMyPageContentDrawer = window.CopyMyPageContentDrawer || {};

(function (window, document, Joomla, UIkit, loader) {
    'use strict';

    const api = window.CopyMyPageContentDrawer;
    const triggerSelector = [
        'a[data-cmp-content-drawer]',
        'a[data-cmp-content-modal]',
        '.cmp-contact__consent a[data-bs-toggle="modal"][href^="#"]'
    ].join(',');
    const drawerId = 'cmp-content-drawer';
    const titleId = 'cmp-content-drawer-title';
    const documentActions = new Set([
        'close',
        'navigate-parent',
        'reload-parent'
    ]);
    let elements = null;
    let offcanvas = null;
    let initialized = false;
    let visible = false;
    let requestSequence = 0;
    let activeOperation = null;
    let returnFocus = null;
    let currentSettings = null;

    const getText = (key, fallback) => {
        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    };

    const getStrings = () => ({
        close: getText('COM_COPYMYPAGE_CONTENT_DRAWER_CLOSE', 'Close panel'),
        error: getText(
            'COM_COPYMYPAGE_CONTENT_DRAWER_ERROR',
            'The content could not be loaded.'
        ),
        genericTitle: getText(
            'COM_COPYMYPAGE_CONTENT_DRAWER_GENERIC_TITLE',
            'More information'
        ),
        loading: getText(
            'COM_COPYMYPAGE_CONTENT_DRAWER_LOADING',
            'Loading content...'
        ),
        openNormally: getText(
            'COM_COPYMYPAGE_CONTENT_DRAWER_OPEN_NORMALLY',
            'Open content on its own page'
        )
    });

    const dispatch = (name, detail = {}) => {
        if (!elements) {
            return;
        }

        elements.root.dispatchEvent(new CustomEvent(name, {
            bubbles: true,
            detail
        }));
    };

    const cancelActiveOperation = () => {
        if (!activeOperation) {
            return;
        }

        const operation = activeOperation;
        activeOperation = null;

        operation.controller.abort();

        if (typeof operation.cleanup === 'function') {
            operation.cleanup();
        }
    };

    const clearBody = () => {
        if (!elements) {
            return;
        }

        elements.body.removeAttribute('aria-busy');
        elements.body.replaceChildren();
    };

    const restoreTriggerFocus = () => {
        const target = returnFocus;
        returnFocus = null;

        if (target instanceof HTMLElement && target.isConnected) {
            target.focus({ preventScroll: true });
        }
    };

    const hideDrawer = () => {
        if (offcanvas && visible && offcanvas.isToggled()) {
            offcanvas.hide();
        }
    };

    const handleShown = () => {
        if (elements) {
            // UIkit freezes the opening viewport width as an inline max-width.
            // Let the responsive drawer CSS own the width after the transition.
            elements.panel.style.removeProperty('max-width');
        }

        window.requestAnimationFrame(() => {
            if (!elements) {
                return;
            }

            const focusTarget = elements.handle.getClientRects().length
                ? elements.handle
                : elements.closeButton;

            if (focusTarget.isConnected) {
                focusTarget.focus({ preventScroll: true });
            }
        });

        dispatch('copymypage:content-drawer:shown', {
            settings: currentSettings
        });
    };

    const handleHidden = () => {
        visible = false;
        cancelActiveOperation();
        clearBody();
        elements.root.classList.remove('cmp-content-drawer--document');
        elements.root.removeAttribute('data-cmp-drawer-transport');

        dispatch('copymypage:content-drawer:hidden', {
            settings: currentSettings
        });

        currentSettings = null;

        // UIkit still guards background focus while its hidden handlers run.
        // Restore focus on the next frame, after that guard has been removed.
        window.requestAnimationFrame(restoreTriggerFocus);
    };

    const createDrawer = () => {
        if (elements) {
            return elements;
        }

        if (!UIkit || typeof UIkit.offcanvas !== 'function') {
            return null;
        }

        const strings = getStrings();
        const root = document.createElement('div');
        root.id = drawerId;
        root.className = 'cmp-content-drawer';
        root.dataset.cmpContentDrawerRoot = '';
        root.setAttribute(
            'uk-offcanvas',
            'mode: slide; flip: true; overlay: true; esc-close: true; bg-close: true; container: false'
        );

        const panel = document.createElement('div');
        panel.className = 'uk-offcanvas-bar cmp-content-drawer__panel';
        panel.setAttribute('aria-labelledby', titleId);

        const handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'cmp-content-drawer__handle';
        handle.setAttribute('aria-label', strings.close);
        panel.appendChild(handle);

        const header = document.createElement('header');
        header.className = 'cmp-content-drawer__header';

        const title = document.createElement('h2');
        title.id = titleId;
        title.className = 'cmp-content-drawer__title';
        title.textContent = strings.genericTitle;
        header.appendChild(title);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'uk-offcanvas-close cmp-content-drawer__close';
        closeButton.setAttribute('uk-close', '');
        closeButton.setAttribute('aria-label', strings.close);
        header.appendChild(closeButton);
        panel.appendChild(header);

        const body = document.createElement('div');
        body.className = 'cmp-content-drawer__body';
        body.setAttribute('aria-live', 'polite');
        panel.appendChild(body);
        root.appendChild(panel);
        document.body.appendChild(root);

        elements = {
            body,
            closeButton,
            handle,
            header,
            panel,
            root,
            title
        };

        root.addEventListener('shown', handleShown);
        root.addEventListener('hidden', handleHidden);
        offcanvas = UIkit.offcanvas(root);
        handle.addEventListener('click', hideDrawer);
        handle.addEventListener('swipeDown', hideDrawer);

        return elements;
    };

    const setTitle = (value) => {
        const strings = getStrings();
        const title = String(value || '').trim() || strings.genericTitle;

        elements.title.textContent = title;
    };

    const normalizeDocumentAction = (value) => {
        const action = String(value || '').trim().toLowerCase();

        return documentActions.has(action) ? action : '';
    };

    const getComparableDocumentUrl = (value) => {
        if (
            !loader
            || typeof loader.getFallbackUrl !== 'function'
            || typeof loader.isLoadableUrl !== 'function'
        ) {
            return null;
        }

        const url = loader.getFallbackUrl(value);

        if (!url || !loader.isLoadableUrl(url)) {
            return null;
        }

        url.hash = '';
        url.pathname = url.pathname.replace(/\/+$/, '') || '/';
        url.searchParams.sort();

        return url;
    };

    const documentUrlsMatch = (first, second) => {
        const firstUrl = getComparableDocumentUrl(first);
        const secondUrl = getComparableDocumentUrl(second);

        return Boolean(firstUrl && secondUrl && firstUrl.href === secondUrl.href);
    };

    const performDocumentAction = (action, targetUrl = null) => {
        const normalizedAction = normalizeDocumentAction(action);

        if (normalizedAction === 'close') {
            api.close();
            return true;
        }

        if (normalizedAction === 'reload-parent') {
            window.location.reload();
            return true;
        }

        if (normalizedAction === 'navigate-parent') {
            const safeTargetUrl = getComparableDocumentUrl(targetUrl);

            if (!safeTargetUrl) {
                return false;
            }

            window.location.assign(safeTargetUrl.href);
            return true;
        }

        return false;
    };

    const adoptDrawerDocument = (frameDocument) => {
        const body = frameDocument && frameDocument.body;
        const source = frameDocument
            ? frameDocument.querySelector('[data-cmp-drawer-document-content]')
            : null;

        if (!body || !source) {
            return false;
        }

        const main = frameDocument.createElement('main');
        const content = frameDocument.createElement('div');
        const messages = frameDocument.getElementById('system-message-container');

        main.id = 'cmp-component-main';
        main.className = 'cmp-component-page__main';
        main.setAttribute('role', 'main');
        main.setAttribute('data-cmp-component-content', '');
        content.className = 'cmp-component-page__content';

        if (
            messages
            && !messages.contains(source)
            && !source.contains(messages)
        ) {
            main.appendChild(messages);
        }

        content.appendChild(source);
        main.appendChild(content);
        body.replaceChildren(main);
        body.className = 'cmp-component-page cmp-component-page--drawer';
        body.setAttribute('data-cmp-component-document', '');
        body.setAttribute('data-cmp-component-context', 'drawer');
        body.setAttribute('data-cmp-adapted-component-document', '');

        const frameUIkit = frameDocument.defaultView
            ? frameDocument.defaultView.UIkit
            : null;

        if (frameUIkit && typeof frameUIkit.update === 'function') {
            frameUIkit.update(body);
        }

        return true;
    };

    const createLoadingState = () => {
        const strings = getStrings();
        const loading = document.createElement('div');
        loading.className = 'cmp-content-drawer__loading';
        loading.setAttribute('role', 'status');

        const spinner = document.createElement('span');
        spinner.setAttribute('uk-spinner', 'ratio: 0.85');
        spinner.setAttribute('aria-hidden', 'true');
        loading.appendChild(spinner);

        const text = document.createElement('span');
        text.textContent = strings.loading;
        loading.appendChild(text);

        return loading;
    };

    const showLoadingState = () => {
        elements.body.setAttribute('aria-busy', 'true');
        elements.body.replaceChildren(createLoadingState());

        if (UIkit && typeof UIkit.update === 'function') {
            UIkit.update(elements.body);
        }
    };

    const showErrorState = (fallbackUrl) => {
        const strings = getStrings();
        const error = document.createElement('div');
        error.className = 'cmp-content-drawer__error';
        error.setAttribute('role', 'alert');

        const message = document.createElement('p');
        message.textContent = strings.error;
        error.appendChild(message);

        if (fallbackUrl) {
            const fallback = document.createElement('a');
            fallback.className = 'cmp-content-drawer__fallback uk-button uk-button-default';
            fallback.href = fallbackUrl.href;
            fallback.textContent = strings.openNormally;
            error.appendChild(fallback);
        }

        elements.body.removeAttribute('aria-busy');
        elements.body.replaceChildren(error);
    };

    const renderFragment = async (settings, operation) => {
        const result = await loader.loadFragment(settings.url, {
            signal: operation.controller.signal
        });

        if (
            operation.controller.signal.aborted
            || !activeOperation
            || activeOperation.sequence !== operation.sequence
        ) {
            return false;
        }

        if (!settings.hasExplicitTitle && result.title) {
            setTitle(result.title);
        }

        elements.body.removeAttribute('aria-busy');
        elements.body.replaceChildren(result.content);

        if (UIkit && typeof UIkit.update === 'function') {
            UIkit.update(elements.body);
        }

        dispatch('copymypage:content-drawer:loaded', {
            transport: 'fragment',
            url: result.url.href
        });

        return true;
    };

    const renderDocument = (settings, operation) => new Promise((resolve) => {
        const componentUrl = loader.getComponentUrl(settings.url, {
            context: 'drawer'
        });
        const frame = document.createElement('iframe');
        const loading = createLoadingState();
        let initialLoad = true;
        let settled = false;
        let timeoutId = null;

        const settle = (value) => {
            if (settled) {
                return;
            }

            settled = true;
            resolve(value);
        };

        const stopFrame = () => {
            window.clearTimeout(timeoutId);
            frame.removeEventListener('load', handleLoad);
            frame.removeEventListener('error', handleError);

            if (frame.isConnected) {
                frame.removeAttribute('src');
            }
        };

        const handleError = () => {
            if (operation.controller.signal.aborted) {
                settle(false);
                return;
            }

            stopFrame();
            showErrorState(settings.fallbackUrl);
            settle(false);
        };

        const showDocumentLoadingState = () => {
            if (
                operation.controller.signal.aborted
                || !activeOperation
                || activeOperation.sequence !== operation.sequence
            ) {
                return;
            }

            frame.hidden = true;
            elements.body.setAttribute('aria-busy', 'true');

            if (!loading.isConnected) {
                elements.body.prepend(loading);
            }

            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(handleError, 20000);
        };

        const handleLoad = () => {
            if (
                operation.controller.signal.aborted
                || !activeOperation
                || activeOperation.sequence !== operation.sequence
            ) {
                settle(false);
                return;
            }

            let frameUrl = null;
            let frameDocument = null;

            try {
                frameUrl = loader.normalizeUrl(frame.contentWindow.location.href);
                frameDocument = frame.contentDocument;
            } catch (error) {
                handleError();
                return;
            }

            // A newly attached iframe may report its initial about:blank
            // document before the requested component document is available.
            if (initialLoad && frameUrl && frameUrl.href === 'about:blank') {
                return;
            }

            if (!frameUrl || frameUrl.origin !== window.location.origin || !frameDocument) {
                handleError();
                return;
            }

            const returnMatched = Boolean(
                !initialLoad
                && settings.returnUrl
                && documentUrlsMatch(frameUrl, settings.returnUrl)
            );

            if (returnMatched) {
                window.clearTimeout(timeoutId);

                const detail = {
                    componentDocument: false,
                    iframe: frame,
                    initial: false,
                    returnMatched: true,
                    transport: 'document',
                    url: frameUrl.href
                };

                dispatch('copymypage:content-drawer:documentload', detail);
                settle(true);
                performDocumentAction(
                    settings.returnAction,
                    settings.returnUrl || frameUrl
                );
                return;
            }

            let isComponentDocument = Boolean(
                frameDocument.body
                && frameDocument.body.hasAttribute('data-cmp-component-document')
                && frameDocument.querySelector('[data-cmp-component-content]')
            );
            let adaptedDocument = false;

            if (!initialLoad && !isComponentDocument) {
                adaptedDocument = adoptDrawerDocument(frameDocument);
                isComponentDocument = adaptedDocument;
            }

            if (!isComponentDocument) {
                handleError();
                return;
            }

            window.clearTimeout(timeoutId);
            loading.remove();
            frame.hidden = false;
            elements.body.removeAttribute('aria-busy');

            if (!settings.hasExplicitTitle && frameDocument.title) {
                setTitle(frameDocument.title);
                frame.title = frameDocument.title;
            }

            const detail = {
                adaptedDocument,
                componentDocument: isComponentDocument,
                iframe: frame,
                initial: initialLoad,
                returnMatched: false,
                transport: 'document',
                url: frameUrl.href
            };

            dispatch('copymypage:content-drawer:documentload', detail);

            if (typeof settings.onDocumentLoad === 'function') {
                const action = settings.onDocumentLoad(detail);

                performDocumentAction(action, frameUrl);
            }

            try {
                frame.contentWindow.addEventListener(
                    'beforeunload',
                    showDocumentLoadingState,
                    { once: true }
                );
            } catch (error) {
                // A same-origin document was already verified above. If its
                // browsing context disappears now, the next load/error event
                // still handles the operation.
            }

            initialLoad = false;
            settle(true);
        };

        frame.className = 'cmp-content-drawer__frame';
        frame.hidden = true;
        frame.loading = 'eager';
        frame.referrerPolicy = 'same-origin';
        frame.title = String(settings.title || getStrings().genericTitle);
        frame.addEventListener('load', handleLoad);
        frame.addEventListener('error', handleError);

        operation.cleanup = stopFrame;
        operation.controller.signal.addEventListener('abort', () => {
            stopFrame();
            settle(false);
        }, { once: true });

        timeoutId = window.setTimeout(handleError, 20000);
        frame.src = componentUrl.href;
        elements.body.replaceChildren(loading, frame);
    });

    const open = async (options = {}) => {
        const fallbackUrl = loader && typeof loader.getFallbackUrl === 'function'
            ? loader.getFallbackUrl(options.url)
            : null;

        if (
            !loader
            || typeof loader.isLoadableUrl !== 'function'
            || !loader.isLoadableUrl(fallbackUrl)
            || !UIkit
            || typeof UIkit.offcanvas !== 'function'
        ) {
            return false;
        }

        if (!createDrawer()) {
            return false;
        }

        const title = String(options.title || '').trim();
        const transport = ['document', 'iframe'].includes(options.transport)
            ? 'document'
            : 'fragment';
        const returnUrl = transport === 'document'
            ? loader.getFallbackUrl(options.returnUrl)
            : null;
        const safeReturnUrl = returnUrl && loader.isLoadableUrl(returnUrl)
            ? returnUrl
            : null;
        const returnAction = safeReturnUrl
            ? normalizeDocumentAction(options.returnAction) || 'close'
            : '';
        const settings = {
            fallbackUrl,
            hasExplicitTitle: title !== '',
            onDocumentLoad: options.onDocumentLoad,
            returnAction,
            returnUrl: safeReturnUrl,
            title,
            transport,
            trigger: options.trigger instanceof HTMLElement ? options.trigger : null,
            url: fallbackUrl
        };

        if (!visible) {
            returnFocus = settings.trigger || (
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null
            );
        }

        cancelActiveOperation();
        requestSequence += 1;

        const operation = {
            cleanup: null,
            controller: new AbortController(),
            sequence: requestSequence
        };

        activeOperation = operation;
        currentSettings = settings;
        elements.root.dataset.cmpDrawerTransport = transport;
        elements.root.classList.toggle('cmp-content-drawer--document', transport === 'document');
        setTitle(title);
        showLoadingState();

        if (!visible) {
            visible = true;
            offcanvas.show();
        }

        dispatch('copymypage:content-drawer:open', {
            transport,
            url: fallbackUrl.href
        });

        try {
            if (transport === 'document') {
                return await renderDocument(settings, operation);
            }

            return await renderFragment(settings, operation);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return false;
            }

            if (
                activeOperation
                && activeOperation.sequence === operation.sequence
                && !operation.controller.signal.aborted
            ) {
                showErrorState(fallbackUrl);
            }

            return false;
        }
    };

    const close = () => {
        hideDrawer();
    };

    const resolveLegacyUrl = (trigger) => {
        const href = trigger.getAttribute('href') || '';

        if (!href.startsWith('#')) {
            return loader.getFallbackUrl(href);
        }

        const legacyModal = document.getElementById(href.slice(1));
        const fallbackUrl = legacyModal && legacyModal.dataset.url
            ? loader.getFallbackUrl(legacyModal.dataset.url)
            : null;

        if (legacyModal && fallbackUrl) {
            legacyModal.hidden = true;
            legacyModal.setAttribute('aria-hidden', 'true');
        }

        return fallbackUrl;
    };

    const prepareTrigger = (trigger) => {
        if (!(trigger instanceof HTMLAnchorElement)) {
            return null;
        }

        const fallbackUrl = resolveLegacyUrl(trigger);

        if (!fallbackUrl || !loader.isLoadableUrl(fallbackUrl)) {
            return null;
        }

        if (
            !trigger.hasAttribute('data-cmp-content-drawer')
            && !trigger.hasAttribute('data-cmp-content-modal')
        ) {
            trigger.dataset.cmpContentDrawer = 'legacy';
        }

        trigger.removeAttribute('data-bs-toggle');
        trigger.removeAttribute('data-bs-target');
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', drawerId);
        trigger.href = fallbackUrl.href;

        return fallbackUrl;
    };

    const decorateTriggers = (root = document) => {
        const triggers = [];

        if (root instanceof Element && root.matches(triggerSelector)) {
            triggers.push(root);
        }

        if (root && typeof root.querySelectorAll === 'function') {
            triggers.push(...root.querySelectorAll(triggerSelector));
        }

        triggers.forEach(prepareTrigger);
    };

    const handleClick = (event) => {
        const trigger = event.target instanceof Element
            ? event.target.closest(triggerSelector)
            : null;

        if (
            !trigger
            || event.defaultPrevented
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey
            || trigger.hasAttribute('download')
            || (trigger.target && trigger.target !== '_self')
        ) {
            return;
        }

        const fallbackUrl = prepareTrigger(trigger);

        if (!fallbackUrl) {
            return;
        }

        event.preventDefault();

        open({
            title: trigger.dataset.cmpDrawerTitle
                || trigger.dataset.cmpContentModalTitle
                || trigger.getAttribute('aria-label')
                || trigger.textContent.trim(),
            returnAction: trigger.dataset.cmpDrawerReturnAction,
            returnUrl: trigger.dataset.cmpDrawerReturnUrl,
            transport: trigger.dataset.cmpDrawerTransport || 'fragment',
            trigger,
            url: fallbackUrl
        });
    };

    const init = (root = document) => {
        if (!loader || !UIkit || typeof UIkit.offcanvas !== 'function') {
            return false;
        }

        createDrawer();
        decorateTriggers(root);

        if (!initialized) {
            document.addEventListener('click', handleClick);
            document.addEventListener('joomla:updated', (event) => {
                decorateTriggers(event.target || document);
            });
            initialized = true;
        }

        return true;
    };

    api.close = close;
    api.init = init;
    api.open = open;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init(), { once: true });
    } else {
        init();
    }
})(
    window,
    document,
    window.Joomla,
    window.UIkit,
    window.CopyMyPageContentLoader
);
