/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (window, document, CopyMyPage) {
    'use strict';

    CopyMyPage.constants = CopyMyPage.constants || {};
    CopyMyPage.utils = CopyMyPage.utils || {};
    CopyMyPage.features = CopyMyPage.features || {};

    const { constants, utils } = CopyMyPage;

    constants.VIEWPORT_QUERY_DEFINITIONS = constants.VIEWPORT_QUERY_DEFINITIONS || Object.freeze({
        small: '(max-width: 639.98px)',
        narrow: '(min-width: 640px) and (max-width: 767.98px)',
        mobile: '(min-width: 768px) and (max-width: 959.98px)',
        tablet: '(min-width: 960px) and (max-width: 1199.98px)',
        uiDesktop: '(min-width: 960px)',
    });

    constants.VIEWPORT_BODY_CLASSES = constants.VIEWPORT_BODY_CLASSES || Object.freeze([
        'is-small',
        'is-tablet',
        'is-mobile',
        'is-desktop',
        'is-narrow',
    ]);

    constants.SELECTORS = constants.SELECTORS || Object.freeze({
        onepageNavbar: '#navbar[uk-scrollspy-nav] .cmp-navbar-nav',
    });

    constants.TIMINGS = constants.TIMINGS || Object.freeze({
        preloaderRemovalFallback: 320,
        onepageHashRecoveryDelays: Object.freeze([0, 16, 64, 160, 320, 640, 1200]),
        dropdownCloseFallback: 180,
        mmenuRestoreDelay: 750,
        mmenuObserverCleanup: 3000,
    });

    constants.THRESHOLDS = constants.THRESHOLDS || Object.freeze({
        mmenuIntersection: 0.1,
        pinLeeway: 8,
        pinFallbackBand: 48,
    });

    constants.PINNED_LINK_DATA = constants.PINNED_LINK_DATA || Object.freeze({
        top: Object.freeze({
            activeKey: 'cmpTopActive',
            ariaKey: 'cmpTopAriaCurrent',
        }),
        hash: Object.freeze({
            activeKey: 'cmpHashActive',
            ariaKey: 'cmpHashAriaCurrent',
        }),
    });

    utils.getCurrentScrollPosition = utils.getCurrentScrollPosition || function getCurrentScrollPosition() {
        return window.pageYOffset || document.documentElement.scrollTop || 0;
    };

    utils.getStickyOffset = utils.getStickyOffset || function getStickyOffset() {
        const rootStyles = getComputedStyle(document.documentElement);

        return Number.parseFloat(rootStyles.getPropertyValue('--cmp-header-offset')) || 0;
    };

    utils.getExpectedAnchorBand = utils.getExpectedAnchorBand || function getExpectedAnchorBand(stickyOffset) {
        return Math.max(
            (stickyOffset * 2) + constants.THRESHOLDS.pinLeeway,
            stickyOffset + constants.THRESHOLDS.pinFallbackBand
        );
    };

    utils.isHTMLElement = utils.isHTMLElement || function isHTMLElement(value) {
        return value instanceof HTMLElement;
    };

    class CleanupBag {
        constructor() {
            this._callbacks = [];
        }

        add(cleanup) {
            if (typeof cleanup === 'function') {
                this._callbacks.push(cleanup);
            }
        }

        isEmpty() {
            return this._callbacks.length === 0;
        }

        flush() {
            while (this._callbacks.length > 0) {
                const cleanup = this._callbacks.pop();

                try {
                    cleanup();
                } catch (error) {
                    // Ignore teardown errors to ensure all cleanup callbacks are attempted.
                }
            }
        }
    }

    class CopyMyPageFeature {
        constructor(host) {
            this.host = host;
            this.cleanup = new CleanupBag();
        }

        destroy() {
            this.cleanup.flush();
        }

        listen(target, type, listener, options) {
            if (!target || typeof target.addEventListener !== 'function' || typeof listener !== 'function') {
                return;
            }

            target.addEventListener(type, listener, options);
            this.cleanup.add(() => target.removeEventListener(type, listener, options));
        }

        select(element, all = false) {
            return this.host._select(element, all);
        }

        logError(messageKey, element = '') {
            this.host.logError(messageKey, element);
        }

        normalizePath(path) {
            return this.host._normalizePath(path);
        }

        normalizeHash(href) {
            return this.host._normalizeHash(href);
        }

        toBool(value) {
            return this.host._toBool(value);
        }

        toInteger(value, fallback) {
            return this.host._toInteger(value, fallback);
        }

        toNumber(value, fallback) {
            return this.host._toNumber(value, fallback);
        }

        get tmpl() {
            return this.host.tmpl || {};
        }

        get mod() {
            return this.host.mod || {};
        }
    }

    CopyMyPage.CleanupBag = CleanupBag;
    CopyMyPage.BaseFeature = CopyMyPageFeature;
})(window, document, window.CopyMyPage);
