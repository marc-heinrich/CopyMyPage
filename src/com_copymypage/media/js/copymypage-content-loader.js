/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

window.CopyMyPageContentLoader = window.CopyMyPageContentLoader || {};

(function (window, document) {
    'use strict';

    const api = window.CopyMyPageContentLoader;
    const allowedProtocols = new Set(['http:', 'https:']);
    const activeMarkupSelector = [
        'base',
        'embed',
        'foreignObject',
        'iframe',
        'link',
        'meta',
        'noscript',
        'object',
        'portal',
        'script',
        'style'
    ].join(',');
    const urlAttributes = ['action', 'formaction', 'href', 'poster', 'src', 'xlink:href'];

    class ContentLoaderError extends Error {
        constructor(code, message, status = 0) {
            super(message);
            this.name = 'ContentLoaderError';
            this.code = code;
            this.status = status;
        }
    }

    const normalizeUrl = (value, baseUrl = window.location.href) => {
        try {
            if (value instanceof URL) {
                return new URL(value.href);
            }

            const normalizedValue = String(value ?? '').trim();

            if (!normalizedValue) {
                return null;
            }

            return new URL(normalizedValue, baseUrl);
        } catch (error) {
            return null;
        }
    };

    const isLoadableUrl = (value) => {
        const url = normalizeUrl(value);

        return Boolean(
            url
            && allowedProtocols.has(url.protocol)
            && url.origin === window.location.origin
        );
    };

    const getFallbackUrl = (value) => {
        const url = normalizeUrl(value);

        if (!url) {
            return null;
        }

        url.searchParams.delete('tmpl');
        url.searchParams.delete('cmp_context');

        return url;
    };

    const getComponentUrl = (value, options = {}) => {
        const url = normalizeUrl(value);

        if (!url) {
            throw new ContentLoaderError('invalid-url', 'The content URL is invalid.');
        }

        if (!allowedProtocols.has(url.protocol) || url.origin !== window.location.origin) {
            throw new ContentLoaderError('unsafe-url', 'Only same-origin HTTP content can be loaded.');
        }

        url.searchParams.set('tmpl', 'component');

        if (options.context === 'drawer') {
            url.searchParams.set('cmp_context', 'drawer');
        } else {
            url.searchParams.delete('cmp_context');
        }

        return url;
    };

    const resolveAttributeUrl = (element, attributeName, sourceUrl) => {
        const rawValue = element.getAttribute(attributeName);

        if (!rawValue || rawValue.startsWith('#')) {
            return;
        }

        const resolved = normalizeUrl(rawValue, sourceUrl.href);

        if (!resolved || !allowedProtocols.has(resolved.protocol)) {
            element.removeAttribute(attributeName);
            return;
        }

        if (
            ['action', 'formaction'].includes(attributeName)
            && resolved.origin !== window.location.origin
        ) {
            element.removeAttribute(attributeName);
            return;
        }

        element.setAttribute(attributeName, resolved.href);
    };

    const resolveSrcset = (element, sourceUrl) => {
        const rawValue = element.getAttribute('srcset');

        if (!rawValue) {
            return;
        }

        const candidates = rawValue
            .split(',')
            .map((candidate) => {
                const parts = candidate.trim().split(/\s+/);
                const resolved = normalizeUrl(parts.shift() || '', sourceUrl.href);

                if (!resolved || !allowedProtocols.has(resolved.protocol)) {
                    return '';
                }

                return [resolved.href, ...parts].join(' ');
            })
            .filter(Boolean);

        if (candidates.length === 0) {
            element.removeAttribute('srcset');
            return;
        }

        element.setAttribute('srcset', candidates.join(', '));
    };

    const sanitizeFragment = (root, sourceValue) => {
        const sourceUrl = normalizeUrl(sourceValue);

        if (!sourceUrl) {
            throw new ContentLoaderError('invalid-response-url', 'The response URL is invalid.');
        }

        root.querySelectorAll(activeMarkupSelector).forEach((element) => element.remove());

        root.querySelectorAll('*').forEach((element) => {
            Array.from(element.attributes).forEach((attribute) => {
                const name = attribute.name.toLowerCase();

                if (
                    name.startsWith('on')
                    || ['formtarget', 'nonce', 'ping', 'srcdoc'].includes(name)
                ) {
                    element.removeAttribute(attribute.name);
                }
            });

            urlAttributes.forEach((attributeName) => {
                if (element.hasAttribute(attributeName)) {
                    resolveAttributeUrl(element, attributeName, sourceUrl);
                }
            });

            if (element.hasAttribute('srcset')) {
                resolveSrcset(element, sourceUrl);
            }
        });

        root.querySelectorAll('a[target]').forEach((link) => {
            const target = link.getAttribute('target');

            if (!['_blank', '_self'].includes(target)) {
                link.removeAttribute('target');
                return;
            }

            if (target === '_blank') {
                link.setAttribute('rel', 'noopener noreferrer');
            }
        });

        root.querySelectorAll('form[target]').forEach((form) => form.removeAttribute('target'));
        root.querySelectorAll('.alert').forEach((alert) => {
            alert.classList.add('cmp-content-loader__notice');
        });

        return root;
    };

    const extractFragment = (html, sourceValue) => {
        const sourceUrl = normalizeUrl(sourceValue);

        if (!sourceUrl) {
            throw new ContentLoaderError('invalid-response-url', 'The response URL is invalid.');
        }

        const parsed = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const source = parsed.querySelector('[data-cmp-component-content]');

        if (!source) {
            throw new ContentLoaderError(
                'invalid-payload',
                'The component response does not expose the required content marker.'
            );
        }

        const content = document.createElement('div');
        content.className = 'cmp-content-fragment';
        content.innerHTML = source.innerHTML;

        return {
            content: sanitizeFragment(content, sourceUrl),
            title: String(parsed.title || '').trim(),
            url: sourceUrl
        };
    };

    const loadFragment = async (value, options = {}) => {
        const componentUrl = getComponentUrl(value);
        const response = await window.fetch(componentUrl.href, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            redirect: 'follow',
            signal: options.signal
        });

        if (!response.ok) {
            throw new ContentLoaderError(
                'http-error',
                `The content request failed with status ${response.status}.`,
                response.status
            );
        }

        const responseUrl = normalizeUrl(response.url || componentUrl.href);

        if (!responseUrl || responseUrl.origin !== window.location.origin) {
            throw new ContentLoaderError(
                'unsafe-redirect',
                'The content request was redirected outside the current origin.'
            );
        }

        const contentType = response.headers.get('content-type') || '';

        if (!contentType.toLowerCase().includes('text/html')) {
            throw new ContentLoaderError(
                'invalid-content-type',
                'The content request did not return HTML.'
            );
        }

        return extractFragment(await response.text(), responseUrl);
    };

    api.ContentLoaderError = ContentLoaderError;
    api.extractFragment = extractFragment;
    api.getComponentUrl = getComponentUrl;
    api.getFallbackUrl = getFallbackUrl;
    api.isLoadableUrl = isLoadableUrl;
    api.loadFragment = loadFragment;
    api.normalizeUrl = normalizeUrl;
    api.sanitizeFragment = sanitizeFragment;
})(window, document);
