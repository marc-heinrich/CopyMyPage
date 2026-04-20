/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (window, document, CopyMyPage) {
    'use strict';

    const BaseFeature = CopyMyPage.BaseFeature;
    const { constants } = CopyMyPage;

    if (!BaseFeature || !constants) {
        return;
    }

    class MmenuNavigationFeature extends BaseFeature {
        constructor(host) {
            super(host);
            this._offcanvas = null;
            this._observer = null;
            this._observedTarget = null;
            this._observerCleanupTimeout = null;
            this._restoreTimeout = null;
            this._isMenuClick = false;
            this._handleOffcanvasClick = this._handleOffcanvasClick.bind(this);
        }

        init() {
            const options = this.mod?.navbarSlots?.mobilemenu || this.mod?.navbar || {};
            const navOffcanvasId = options.navOffcanvasId;

            if (!navOffcanvasId) {
                return;
            }

            const escapedOffcanvasId = window.CSS?.escape ? window.CSS.escape(navOffcanvasId) : navOffcanvasId;
            this._offcanvas = this.select(`#${escapedOffcanvasId}`);

            if (!this._offcanvas) {
                return;
            }

            this._observer = new IntersectionObserver(
                (entries) => this._handleIntersections(entries),
                { threshold: constants.THRESHOLDS.mmenuIntersection }
            );

            this.listen(this._offcanvas, 'click', this._handleOffcanvasClick);
        }

        destroy() {
            window.clearTimeout(this._observerCleanupTimeout);
            window.clearTimeout(this._restoreTimeout);

            if (this._observer) {
                this._observer.disconnect();
            }

            this._observer = null;
            this._observedTarget = null;
            this._observerCleanupTimeout = null;
            this._restoreTimeout = null;
            this._isMenuClick = false;
            super.destroy();
        }

        _handleIntersections(entries) {
            entries.forEach((entry) => {
                if (!entry.isIntersecting || !this._isMenuClick || entry.target !== this._observedTarget) {
                    return;
                }

                window.clearTimeout(this._restoreTimeout);
                this._restoreTimeout = window.setTimeout(() => {
                    if (document.body) {
                        document.body.classList.add('mm-ocd-opened');
                    }
                }, constants.TIMINGS.mmenuRestoreDelay);

                this._isMenuClick = false;
                this._cleanupObservedTarget();
            });
        }

        _cleanupObservedTarget() {
            window.clearTimeout(this._observerCleanupTimeout);
            this._observerCleanupTimeout = null;

            if (this._observer && this._observedTarget) {
                this._observer.unobserve(this._observedTarget);
            }

            this._observedTarget = null;
        }

        _observeTarget(target) {
            this._cleanupObservedTarget();

            this._observedTarget = target;
            this._observer?.observe(target);
            this._observerCleanupTimeout = window.setTimeout(() => {
                this._isMenuClick = false;
                this._cleanupObservedTarget();
            }, constants.TIMINGS.mmenuObserverCleanup);
        }

        _handleOffcanvasClick(event) {
            const link = event.target?.closest?.('a[href*="#"]');

            if (!link || !this._offcanvas || !this._offcanvas.contains(link)) {
                return;
            }

            const href = (link.getAttribute('href') || '').trim();

            if (!href || href === '#') {
                return;
            }

            let parsedUrl;

            try {
                parsedUrl = new URL(href, window.location.href);
            } catch (error) {
                return;
            }

            const targetSelector = parsedUrl.hash;

            if (!targetSelector || targetSelector === '#') {
                return;
            }

            const currentPath = this.normalizePath(window.location.pathname);
            const targetPath = this.normalizePath(parsedUrl.pathname);
            const isSameDocument = parsedUrl.origin === window.location.origin
                && targetPath === currentPath
                && parsedUrl.search === window.location.search;

            if (!isSameDocument && !href.startsWith('#')) {
                return;
            }

            const target = this.select(targetSelector);

            if (!target) {
                return;
            }

            event.preventDefault();

            window.clearTimeout(this._restoreTimeout);
            this._isMenuClick = true;

            if (document.body) {
                document.body.classList.remove('mm-ocd-opened');
            }

            this._updateSectionLocation(parsedUrl, targetSelector);
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            this._dispatchSectionChange(targetSelector);
            this._observeTarget(target);
        }

        _updateSectionLocation(parsedUrl, targetSelector) {
            const nextUrl = `${parsedUrl.pathname}${parsedUrl.search}${targetSelector}`;

            if (window.location.hash === targetSelector) {
                return;
            }

            if (window.history && typeof window.history.pushState === 'function') {
                window.history.pushState(window.history.state, '', nextUrl);
                return;
            }

            window.location.hash = targetSelector;
        }

        _dispatchSectionChange(targetSelector) {
            document.dispatchEvent(new window.CustomEvent('copymypage:onepage-sectionchange', {
                detail: {
                    hash: targetSelector,
                    source: 'mobilemenu',
                },
            }));
        }
    }

    CopyMyPage.features.MmenuNavigation = MmenuNavigationFeature;
})(window, document, window.CopyMyPage);
