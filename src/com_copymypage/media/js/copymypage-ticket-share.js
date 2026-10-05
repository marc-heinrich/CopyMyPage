/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */
(function (window, document, Joomla) {
    'use strict';

    if (window.CopyMyPageTicketShareInitialized) {
        return;
    }

    window.CopyMyPageTicketShareInitialized = true;
    const states = new Map();
    const prefix = 'COM_COPYMYPAGE_DASHBOARD_TICKETS_SHARE_';
    let sharing = false;

    const text = (key) => Joomla.Text._(prefix + key);
    const supported = () => {
        if (!window.isSecureContext || typeof navigator.share !== 'function'
            || typeof navigator.canShare !== 'function' || typeof window.File !== 'function') {
            return false;
        }
        try {
            return navigator.canShare({
                files: [new File(['%PDF-1.4\n'], 'ticket.pdf', { type: 'application/pdf' })]
            });
        } catch {
            return false;
        }
    };

    const feedback = (state, key = '', error = false) => {
        state.status.textContent = key ? text(key) : '';
        state.status.classList.toggle('is-error', error);
    };

    const unavailable = (state) => {
        state.file = null;
        state.available = false;
        state.button.disabled = true;
        state.button.hidden = true;
        state.button.removeAttribute('title');
        feedback(state);
    };

    const safeName = (name, fallback) => {
        const cleaned = String(name || '').split(/[\\/]/).pop()
            .replace(/[\u0000-\u001f\u007f<>:"|?*\u202a-\u202e\u2066-\u2069]/g, '')
            .replace(/^\.+/, '').trim().slice(0, 180);
        return /\.pdf$/i.test(cleaned) ? cleaned : fallback;
    };

    const filename = (response, fallback) => {
        const header = response.headers.get('Content-Disposition') || '';
        const encoded = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(header);
        if (encoded) {
            try {
                return safeName(decodeURIComponent(encoded[1].trim()), fallback);
            } catch {
                // Try the ordinary filename when filename* is malformed.
            }
        }
        const ordinary = /filename\s*=\s*(?:"([^"]*)"|([^;]*))/i.exec(header);
        return safeName(ordinary ? ordinary[1] || ordinary[2] : '', fallback);
    };

    const fail = (key) => {
        const error = new Error(key);
        error.feedbackKey = key;
        return error;
    };

    const load = async (state) => {
        const link = state.root.querySelector('[data-cmp-ticket-download]');
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.username || url.password) {
            throw fail('INVALID_PDF');
        }

        state.abort = new AbortController();
        let response;
        try {
            response = await fetch(url.href, {
                credentials: 'same-origin',
                cache: 'no-store',
                signal: state.abort.signal
            });
        } catch (error) {
            if (error.name === 'AbortError') {
                throw error;
            }
            throw fail('NETWORK_ERROR');
        }

        const finalUrl = new URL(response.url, url.href);
        if (finalUrl.origin !== window.location.origin) {
            throw fail('INVALID_PDF');
        }
        if (response.status === 401 || response.status === 403
            || (response.redirected && (/\/login\b/i.test(finalUrl.pathname)
                || (finalUrl.searchParams.get('option') === 'com_users'
                    && finalUrl.searchParams.get('view') === 'login')))) {
            throw fail('AUTH_REQUIRED');
        }
        if (!response.ok) {
            throw fail('NETWORK_ERROR');
        }
        const mime = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
        if (mime !== 'application/pdf') {
            throw fail(response.redirected && mime === 'text/html' ? 'AUTH_REQUIRED' : 'INVALID_PDF');
        }

        let blob;
        try {
            blob = await response.blob();
        } catch {
            throw fail('NETWORK_ERROR');
        }
        if (blob.size === 0 || !/^%PDF-\d\.\d/.test(await blob.slice(0, 8).text())) {
            throw fail('INVALID_PDF');
        }

        const fallback = safeName(link.dataset.pdfFilename, `ticket-${state.root.dataset.ticketId}.pdf`);
        return new File([blob], filename(response, fallback), { type: 'application/pdf' });
    };

    const share = async (state) => {
        // This call starts synchronously on a ready-state click: no fetch/await
        // before navigator.share, preserving fresh Safari/iOS user activation.
        sharing = true;
        state.busy = true;
        state.button.disabled = true;
        state.button.setAttribute('aria-busy', 'true');
        try {
            await navigator.share({ files: [state.file] });
            state.file = null;
            feedback(state);
            state.button.removeAttribute('title');
        } catch (error) {
            if (error.name === 'AbortError') {
                state.file = null;
                feedback(state, 'CANCELLED');
                state.button.removeAttribute('title');
            } else if (error.name === 'NotAllowedError'
                && navigator.userActivation && !navigator.userActivation.isActive) {
                feedback(state, 'READY');
                state.button.setAttribute('title', text('READY'));
            } else {
                feedback(state, 'ERROR', true);
            }
        } finally {
            sharing = false;
            state.busy = false;
            state.button.disabled = false;
            state.button.removeAttribute('aria-busy');
        }
    };

    const activate = async (state) => {
        if (state.busy || sharing || state.button.disabled) {
            return;
        }
        if (state.file) {
            share(state);
            return;
        }

        state.busy = true;
        state.button.disabled = true;
        state.button.setAttribute('aria-busy', 'true');
        feedback(state, 'LOADING');
        try {
            state.file = await load(state);
            if (!state.root.isConnected) {
                state.file = null;
                return;
            }
            if (!navigator.canShare({ files: [state.file] })) {
                unavailable(state);
                return;
            }
            if (!sharing && navigator.userActivation && navigator.userActivation.isActive) {
                await share(state);
            } else {
                feedback(state, 'READY');
                state.button.setAttribute('title', text('READY'));
            }
        } catch (error) {
            state.file = null;
            if (error.name !== 'AbortError') {
                feedback(state, error.feedbackKey || 'ERROR', true);
            }
        } finally {
            state.abort = null;
            state.busy = false;
            state.button.disabled = !state.available;
            state.button.removeAttribute('aria-busy');
        }
    };

    const initialize = () => {
        for (const [root, state] of states) {
            if (!root.isConnected) {
                state.abort?.abort();
                state.file = null;
                states.delete(root);
            }
        }
        document.querySelectorAll('[data-cmp-ticket]').forEach((root) => {
            if (states.has(root)) {
                return;
            }
            const button = root.querySelector('[data-cmp-ticket-share]');
            const status = root.querySelector('[data-cmp-ticket-status]');
            if (!button || !status || !root.querySelector('[data-cmp-ticket-download]')) {
                return;
            }
            const state = { root, button, status, file: null, abort: null, busy: false, available: true };
            states.set(root, state);
            if (supported()) {
                button.hidden = false;
            } else {
                unavailable(state);
            }
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element ? event.target.closest('[data-cmp-ticket-share]') : null;
        if (!button) {
            return;
        }
        const state = states.get(button.closest('[data-cmp-ticket]'));
        if (state) {
            activate(state);
        }
    });
    window.addEventListener('pagehide', () => {
        for (const state of states.values()) {
            state.abort?.abort();
            state.file = null;
        }
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
    document.addEventListener('joomla:updated', initialize);
})(window, document, window.Joomla);
