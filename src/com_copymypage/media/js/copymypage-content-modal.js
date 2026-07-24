/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

window.CopyMyPageContentModal = window.CopyMyPageContentModal || {};
window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window, document, Joomla, UIkit) {
    'use strict';

    const api = window.CopyMyPageContentModal;
    const modalNs = window.CopyMyPageModal;
    const initializedTriggers = new WeakSet();
    let titleId = 0;

    const getText = (key, fallback) => {
        if (modalNs.utils && typeof modalNs.utils.getText === 'function') {
            return modalNs.utils.getText(key, fallback);
        }

        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    };

    const escapeHtml = (value) => {
        if (modalNs.utils && typeof modalNs.utils.escapeHtml === 'function') {
            return modalNs.utils.escapeHtml(value);
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const normalizeUrl = (value) => {
        try {
            return new URL(String(value || ''), window.location.href);
        } catch (error) {
            return null;
        }
    };

    const getComponentUrl = (url) => {
        const componentUrl = new URL(url.href);

        componentUrl.searchParams.set('tmpl', 'component');

        return componentUrl;
    };

    const removeUnsafeMarkup = (root, sourceUrl) => {
        root.querySelectorAll('script, style, link, meta, base, noscript, iframe, object, embed')
            .forEach((element) => element.remove());

        root.querySelectorAll('*').forEach((element) => {
            Array.from(element.attributes).forEach((attribute) => {
                const name = attribute.name.toLowerCase();

                if (name.startsWith('on') || name === 'srcdoc') {
                    element.removeAttribute(attribute.name);
                }
            });
        });

        root.querySelectorAll('[href], [src]').forEach((element) => {
            ['href', 'src'].forEach((attributeName) => {
                const rawValue = element.getAttribute(attributeName);

                if (!rawValue || rawValue.startsWith('#')) {
                    return;
                }

                try {
                    const resolved = new URL(rawValue, sourceUrl);

                    if (!['http:', 'https:'].includes(resolved.protocol)) {
                        element.removeAttribute(attributeName);
                        return;
                    }

                    element.setAttribute(attributeName, resolved.href);
                } catch (error) {
                    element.removeAttribute(attributeName);
                }
            });
        });

        root.querySelectorAll('a[target="_blank"]').forEach((link) => {
            link.setAttribute('rel', 'noopener noreferrer');
        });

        root.querySelectorAll('.alert').forEach((alert) => {
            alert.classList.add('cmp-uikit-content-modal__notice');
        });

        return root;
    };

    const extractContent = (html, sourceUrl) => {
        const parsed = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const source = parsed.querySelector('[data-cmp-component-content]')
            || parsed.querySelector('.com-content-article__body')
            || parsed.querySelector('[itemprop="articleBody"]')
            || parsed.querySelector('.item-page')
            || parsed.querySelector('article')
            || parsed.querySelector('main');

        if (!source) {
            throw new Error('Content fragment not found.');
        }

        const content = document.createElement('article');
        content.className = 'cmp-uikit-content-modal__article uk-article';
        content.innerHTML = source.innerHTML;

        return removeUnsafeMarkup(content, sourceUrl);
    };

    const loadContent = async (url, signal) => {
        if (url.origin !== window.location.origin) {
            throw new Error('Only same-origin modal content can be loaded.');
        }

        const componentUrl = getComponentUrl(url);
        const response = await window.fetch(componentUrl.href, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal
        });

        if (!response.ok) {
            throw new Error(`Content request failed with status ${response.status}.`);
        }

        const contentType = response.headers.get('content-type') || '';

        if (!contentType.toLowerCase().includes('text/html')) {
            throw new Error('Content request did not return HTML.');
        }

        return extractContent(await response.text(), response.url || componentUrl.href);
    };

    const open = (options = {}) => {
        const settings = Object.assign({
            title: '',
            url: '',
            closable: true,
            stack: true
        }, options);
        const url = normalizeUrl(settings.url);
        const dialogAdapter = modalNs.adapters && modalNs.adapters.dialog;

        if (!url) {
            return Promise.resolve(false);
        }

        if (url.origin !== window.location.origin) {
            window.open(url.href, '_blank', 'noopener,noreferrer');
            return Promise.resolve(false);
        }

        if (!UIkit || typeof UIkit.modal !== 'function' || typeof dialogAdapter !== 'function') {
            window.open(url.href, '_blank', 'noopener,noreferrer');
            return Promise.resolve(false);
        }

        const loadingText = getText(
            'COM_COPYMYPAGE_CONTENT_MODAL_LOADING',
            'Loading privacy information...'
        );
        const errorText = getText(
            'COM_COPYMYPAGE_CONTENT_MODAL_ERROR',
            'The privacy information could not be loaded.'
        );
        const abortController = new AbortController();

        return dialogAdapter({
            title: settings.title,
            body: [
                '<div class="cmp-uikit-content-modal__loading" role="status">',
                '<span uk-spinner="ratio: 0.8" aria-hidden="true"></span>',
                `<span>${escapeHtml(loadingText)}</span>`,
                '</div>'
            ].join(''),
            isHtml: true,
            closable: settings.closable !== false,
            cancelValue: true,
            className: 'cmp-uikit-dialog--content cmp-uikit-content-modal uk-modal-container',
            panelClassName: 'cmp-uikit-content-modal__panel',
            bodyClassName: 'cmp-uikit-content-modal__body',
            overflowAuto: true,
            stack: settings.stack !== false,
            buttons: [{
                label: getText('JCLOSE', 'Close'),
                className: 'uk-button uk-button-primary',
                value: true
            }],
            onReady: async ({ modalElement, title, body }) => {
                titleId += 1;
                title.id = `cmp-content-modal-title-${titleId}`;
                modalElement.setAttribute('aria-labelledby', title.id);
                modalElement.addEventListener('hidden', () => abortController.abort(), { once: true });
                modalElement.addEventListener('hidden.uk.modal', () => abortController.abort(), { once: true });
                body.setAttribute('aria-busy', 'true');

                try {
                    const content = await loadContent(url, abortController.signal);

                    body.replaceChildren(content);
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    body.innerHTML = [
                        '<div class="cmp-uikit-content-modal__error uk-alert-danger" uk-alert>',
                        `<p>${escapeHtml(errorText)}</p>`,
                        '</div>'
                    ].join('');
                } finally {
                    body.removeAttribute('aria-busy');

                    if (UIkit && typeof UIkit.update === 'function') {
                        UIkit.update(body);
                    }
                }
            }
        });
    };

    const initializeConsentTrigger = (trigger) => {
        if (initializedTriggers.has(trigger)) {
            return;
        }

        const targetSelector = trigger.getAttribute('href');
        const legacyModal = targetSelector && targetSelector.startsWith('#')
            ? document.getElementById(targetSelector.slice(1))
            : null;
        const url = normalizeUrl(
            legacyModal && legacyModal.dataset.url
                ? legacyModal.dataset.url
                : targetSelector
        );

        if (!url) {
            return;
        }

        const fallbackUrl = new URL(url.href);

        fallbackUrl.searchParams.delete('tmpl');

        initializedTriggers.add(trigger);
        trigger.removeAttribute('data-bs-toggle');
        trigger.removeAttribute('data-bs-target');
        trigger.dataset.cmpContentModal = trigger.dataset.cmpContentModal || 'privacy';
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('href', fallbackUrl.href);

        if (legacyModal) {
            legacyModal.hidden = true;
            legacyModal.setAttribute('aria-hidden', 'true');
        }

        trigger.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const dialogAdapter = modalNs.adapters && modalNs.adapters.dialog;

            if (
                url.origin !== window.location.origin
                || !UIkit
                || typeof UIkit.modal !== 'function'
                || typeof dialogAdapter !== 'function'
            ) {
                return;
            }

            event.preventDefault();
            open({
                title: trigger.dataset.cmpContentModalTitle || trigger.textContent.trim(),
                url: fallbackUrl.href
            }).finally(() => {
                if (trigger.isConnected) {
                    trigger.focus({ preventScroll: true });
                }
            });
        });
    };

    const initConsentLinks = (root = document) => {
        const selector = [
            'a[data-cmp-content-modal]',
            '.cmp-contact__consent a[data-bs-toggle="modal"][href^="#"]'
        ].join(',');
        const triggers = [];

        if (root instanceof Element && root.matches(selector)) {
            triggers.push(root);
        }

        if (root && typeof root.querySelectorAll === 'function') {
            triggers.push(...root.querySelectorAll(selector));
        }

        triggers.forEach(initializeConsentTrigger);
    };

    api.open = open;
    api.initConsentLinks = initConsentLinks;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initConsentLinks());
    } else {
        initConsentLinks();
    }

    document.addEventListener('joomla:updated', (event) => {
        initConsentLinks(event.target || document);
    });
})(window, document, window.Joomla, window.UIkit);
