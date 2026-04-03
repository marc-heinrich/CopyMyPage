/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (Joomla, UIkit, window, document, CopyMyPage) {
    'use strict';

    class CopyMyPageRuntime {
        constructor(params = null) {
            this._disabled = false;
            this._initialized = false;
            this._features = [];

            if (!Joomla) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED', 'Joomla');
                this._disabled = true;
                return;
            }

            if (!UIkit) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED', 'UIkit');
                this._disabled = true;
                return;
            }

            const resolvedParams = (params && typeof params === 'object' && !Array.isArray(params))
                ? params
                : Joomla.getOptions('copymypage.params', null);

            if (!resolvedParams || typeof resolvedParams !== 'object' || Array.isArray(resolvedParams)) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_INVALID_PARAMS');
                this._disabled = true;
                return;
            }

            this._applyParams(resolvedParams);
        }

        init() {
            if (this._disabled) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_INIT_SKIPPED');
                return;
            }

            if (this._initialized) {
                return;
            }

            this._initializeFeatures();
            this._features.forEach((feature) => {
                if (typeof feature.init === 'function') {
                    feature.init();
                }
            });

            this._initialized = true;

            if (window.CopyMyPageDialog && typeof window.CopyMyPageDialog.initSystemMessages === 'function') {
                window.CopyMyPageDialog.initSystemMessages();
            }
        }

        destroy() {
            this._destroyFeatures();
            this._initialized = false;
        }

        logError(messageKey, element = '') {
            const msg = Joomla?.Text?._(messageKey) ?? messageKey;

            if (element) {
                console.error(msg.replace('%s', element));
                return;
            }

            console.error(msg);
        }

        _applyParams(params) {
            const forbidden = new Set(['__proto__', 'prototype', 'constructor']);
            const protoKeys = new Set(Object.getOwnPropertyNames(Object.getPrototypeOf(this)));

            for (const [key, value] of Object.entries(params)) {
                if (forbidden.has(key) || protoKeys.has(key) || key.startsWith('_')) {
                    continue;
                }

                this[key] = value;
            }
        }

        _initializeFeatures() {
            const runtimeFeatures = CopyMyPage.features || {};

            this._destroyFeatures();

            this._backToTopFeature = runtimeFeatures.BackToTop
                ? new runtimeFeatures.BackToTop(this)
                : null;
            this._onepageNavigationFeature = runtimeFeatures.OnepageNavigation
                ? new runtimeFeatures.OnepageNavigation(this)
                : null;
            this._desktopUserDropdownFeature = runtimeFeatures.DesktopUserDropdown
                ? new runtimeFeatures.DesktopUserDropdown(this)
                : null;
            this._viewportFeature = runtimeFeatures.Viewport
                ? new runtimeFeatures.Viewport(this, (viewportState) => this._handleViewportChange(viewportState))
                : null;
            this._mmenuNavigationFeature = runtimeFeatures.MmenuNavigation
                ? new runtimeFeatures.MmenuNavigation(this)
                : null;
            this._preloaderFeature = runtimeFeatures.Preloader
                ? new runtimeFeatures.Preloader(this)
                : null;
            this._scrollCoordinatorFeature = runtimeFeatures.ScrollCoordinator
                ? new runtimeFeatures.ScrollCoordinator(this, [
                    () => this._backToTopFeature?.sync?.(),
                    () => this._onepageNavigationFeature?.handleScroll?.(),
                ])
                : null;

            this._features = [
                this._preloaderFeature,
                this._backToTopFeature,
                this._onepageNavigationFeature,
                this._desktopUserDropdownFeature,
                this._viewportFeature,
                this._mmenuNavigationFeature,
                this._scrollCoordinatorFeature,
            ].filter(Boolean);
        }

        _destroyFeatures() {
            while (this._features.length > 0) {
                const feature = this._features.pop();

                try {
                    feature?.destroy?.();
                } catch (error) {
                    // Ignore teardown errors to ensure all feature cleanups are attempted.
                }
            }

            this._backToTopFeature = null;
            this._onepageNavigationFeature = null;
            this._desktopUserDropdownFeature = null;
            this._viewportFeature = null;
            this._mmenuNavigationFeature = null;
            this._preloaderFeature = null;
            this._scrollCoordinatorFeature = null;
        }

        _handleViewportChange(viewportState) {
            this._desktopUserDropdownFeature?.sync?.(viewportState);
            this._onepageNavigationFeature?.handleViewportChange?.();
        }

        _normalizePath(path) {
            const trimmed = String(path || '').replace(/\/+$/, '');
            return trimmed || '/';
        }

        _normalizeHash(href) {
            const value = String(href || '').trim();

            if (!value) {
                return '';
            }

            if (value.startsWith('#')) {
                return value;
            }

            try {
                return new URL(value, window.location.href).hash || '';
            } catch (error) {
                return '';
            }
        }

        _toBool(value) {
            return value === true || value === 1 || value === '1';
        }

        _toInteger(value, fallback) {
            return Number.parseInt(value, 10) || fallback;
        }

        _toNumber(value, fallback) {
            return Number(value ?? fallback);
        }

        _select(element, all = false) {
            if (element instanceof HTMLElement) {
                return element;
            }

            if (typeof element !== 'string') {
                return null;
            }

            const selector = element.trim();

            if (!selector) {
                return null;
            }

            try {
                const selected = all
                    ? [...document.querySelectorAll(selector)]
                    : document.querySelector(selector);

                if (all) {
                    if (selected.length === 0) {
                        this.logError('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND', selector);
                        return null;
                    }

                    return selected;
                }

                if (!selected) {
                    this.logError('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND', selector);
                    return null;
                }

                return selected;
            } catch (error) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_SELECTOR', selector);
                return null;
            }
        }

        static request(options) {
            return Joomla.request(options);
        }
    }

    CopyMyPage.Runtime = CopyMyPageRuntime;
    CopyMyPage.createRuntime = function createRuntime(params = null) {
        return new CopyMyPageRuntime(params);
    };
    CopyMyPage.request = function request(options) {
        return CopyMyPageRuntime.request(options);
    };
})(window.Joomla, window.UIkit, window, document, window.CopyMyPage);
