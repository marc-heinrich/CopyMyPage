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

    const BaseFeature = CopyMyPage.BaseFeature;
    const { constants, utils } = CopyMyPage;

    if (!BaseFeature || !constants || !utils) {
        return;
    }

    class PreloaderFeature extends BaseFeature {
        constructor(host) {
            super(host);
            this._removalTimeout = null;
        }

        init() {
            if (!document.body) {
                return;
            }

            const preloader = this.select(this.tmpl.preloaderSelector || '#cmp-preloader');

            if (!preloader) {
                document.body.classList.remove('cmp-preloader-active');
                this.logError('TPL_COPYMYPAGE_JS_ERROR_PRELOADER_NOT_FOUND');
                return;
            }

            const removePreloader = () => {
                if (preloader.isConnected) {
                    preloader.remove();
                }
            };

            const hidePreloader = () => {
                document.body.classList.remove('cmp-preloader-active');

                if (preloader.classList.contains('is-loaded')) {
                    return;
                }

                preloader.classList.add('is-loaded');
                preloader.addEventListener('transitionend', removePreloader, { once: true });

                window.clearTimeout(this._removalTimeout);
                this._removalTimeout = window.setTimeout(removePreloader, constants.TIMINGS.preloaderRemovalFallback);
            };

            const scheduleHidePreloader = () => {
                window.requestAnimationFrame(hidePreloader);
            };

            if (document.readyState === 'loading') {
                this.listen(document, 'DOMContentLoaded', scheduleHidePreloader, { once: true });
                this.listen(window, 'load', scheduleHidePreloader, { once: true });
                return;
            }

            scheduleHidePreloader();
            this.listen(window, 'load', scheduleHidePreloader, { once: true });
        }

        destroy() {
            window.clearTimeout(this._removalTimeout);
            this._removalTimeout = null;
            super.destroy();
        }
    }

    class BackToTopFeature extends BaseFeature {
        constructor(host) {
            super(host);
            this.button = null;
            this.position = 100;
            this.targetSelector = 'body';
        }

        init() {
            this.button = this.select(this.tmpl.backToTopSelector);

            if (!this.button) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND');
                return;
            }

            this.position = this.toNumber(this.tmpl.backToTopPosition, 100);

            const hrefTarget = this.button.getAttribute('href') || '';
            this.targetSelector = this.tmpl.backToTopTargetSelector || hrefTarget || 'body';

            this.sync();
            this.listen(this.button, 'click', (event) => {
                event.preventDefault();
                this.scrollToTarget(this.targetSelector);
            });
        }

        sync() {
            if (!this.button) {
                return;
            }

            this.button.classList.toggle('visible', utils.getCurrentScrollPosition() > this.position);
        }

        scrollToTarget(selector) {
            const target = this.select(selector);

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    class ViewportFeature extends BaseFeature {
        constructor(host, onChange) {
            super(host);
            this._onChange = onChange;
            this._queries = this._createQueries();
            this._handleChange = this._handleChange.bind(this);
        }

        init() {
            this._applyBodyClass();
            this._bindListeners();
            this._emitChange();
        }

        getState() {
            const small = this._queries.small.matches;
            const tablet = this._queries.tablet.matches;
            const mobile = this._queries.mobile.matches;
            const narrow = this._queries.narrow.matches;
            const uiDesktop = this._queries.uiDesktop.matches;
            const desktop = !small && !narrow && !mobile && !tablet;

            return {
                name: this._resolveViewportName({ small, narrow, mobile, tablet }),
                mobile,
                narrow,
                small,
                tablet,
                desktop,
                uiDesktop,
            };
        }

        _createQueries() {
            return Object.entries(constants.VIEWPORT_QUERY_DEFINITIONS).reduce((queries, [key, query]) => {
                queries[key] = window.matchMedia(query);
                return queries;
            }, {});
        }

        _bindListeners() {
            for (const query of Object.values(this._queries)) {
                if (typeof query.addEventListener === 'function') {
                    this.listen(query, 'change', this._handleChange);
                    continue;
                }

                if ('onchange' in query) {
                    query.onchange = this._handleChange;
                    this.cleanup.add(() => {
                        if (query.onchange === this._handleChange) {
                            query.onchange = null;
                        }
                    });
                }
            }
        }

        _handleChange() {
            this._applyBodyClass();
            this._emitChange();
        }

        _emitChange() {
            if (typeof this._onChange === 'function') {
                this._onChange(this.getState());
            }
        }

        _applyBodyClass() {
            if (!document.body) {
                return;
            }

            const viewport = this.getState();
            document.body.classList.remove(...constants.VIEWPORT_BODY_CLASSES);
            document.body.classList.add(`is-${viewport.name}`);
        }

        _resolveViewportName(viewport) {
            if (viewport.small) {
                return 'small';
            }

            if (viewport.narrow) {
                return 'narrow';
            }

            if (viewport.mobile) {
                return 'mobile';
            }

            if (viewport.tablet) {
                return 'tablet';
            }

            return 'desktop';
        }
    }

    class ScrollCoordinatorFeature extends BaseFeature {
        constructor(host, callbacks = []) {
            super(host);
            this._callbacks = callbacks.slice();
            this._scrollFrame = null;
            this._handleScroll = this._handleScroll.bind(this);
        }

        init() {
            this._flush();
            this.listen(window, 'scroll', this._handleScroll, { passive: true });
        }

        destroy() {
            window.cancelAnimationFrame(this._scrollFrame);
            this._scrollFrame = null;
            super.destroy();
        }

        _handleScroll() {
            if (this._scrollFrame !== null) {
                return;
            }

            this._scrollFrame = window.requestAnimationFrame(() => {
                this._scrollFrame = null;
                this._flush();
            });
        }

        _flush() {
            this._callbacks.forEach((callback) => {
                if (typeof callback !== 'function') {
                    return;
                }

                try {
                    callback();
                } catch (error) {
                    // Keep other callbacks alive even if one handler fails.
                }
            });
        }
    }

    CopyMyPage.features.Preloader = PreloaderFeature;
    CopyMyPage.features.BackToTop = BackToTopFeature;
    CopyMyPage.features.Viewport = ViewportFeature;
    CopyMyPage.features.ScrollCoordinator = ScrollCoordinatorFeature;
})(window, document, window.CopyMyPage);
