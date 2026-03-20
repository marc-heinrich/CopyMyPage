/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (Joomla, UIkit, window, document) {
    'use strict';

    const VIEWPORT_QUERY_DEFINITIONS = Object.freeze({
        // Exclusive class tiers: small -> narrow -> mobile -> tablet -> desktop.
        small: '(max-width: 639.98px)',
        narrow: '(min-width: 640px) and (max-width: 767.98px)',
        mobile: '(min-width: 768px) and (max-width: 959.98px)',
        tablet: '(min-width: 960px) and (max-width: 1199.98px)',
        // UIkit desktop behavior starts at @m (960px).
        uiDesktop: '(min-width: 960px)',
    });

    const VIEWPORT_BODY_CLASSES = Object.freeze([
        'is-small',
        'is-tablet',
        'is-mobile',
        'is-desktop',
        'is-narrow',
    ]);

    const ONEPAGE_NAVBAR_SELECTOR = '#navbar[uk-scrollspy-nav] .cmp-navbar-nav';

    /**
     * CopyMyPage Class
     * Provides functionality for template interactions and behaviors.
     */
    class CopyMyPage {
        /**
         * Constructor for the CopyMyPage class.
         * Validates and applies the given parameters.
         *
         * @param {Object} params - The parameters for initializing CopyMyPage.
         */
        constructor(params) {
            this._disabled = false;

            // Cached bound handlers (avoid creating a new function on every event).
            this._onScroll = this._debouncedScroll.bind(this);
            this._onViewportChange = this._handleViewportChange.bind(this);
            this._onOnepageNavStateChange = this._scheduleOnepageNavbarTopStateSync.bind(this);

            // Internal timers/state.
            this._scrollTimeout = null;
            this._mmenuRestoreTimeout = null;
            this._preloaderRemovalTimeout = null;
            this._onepageNavSyncFrame = null;
            this._mmenuLinksHandlerBound = false;
            this._initialized = false;
            this._viewportListenerBound = false;
            this._dropdownHoldOpenCleanup = null;
            this._cleanupCallbacks = [];
            this._viewportQueries = this._createViewportQueries();

            // Runtime dependency checks.
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

            if (!params || typeof params !== 'object') {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_INVALID_PARAMS');
                this._disabled = true;
                return;
            }

            // Apply parameters to the instance.
            this._applyParams(params);
        }

        /**
         * Initializes the template functionality.
         * Called after the constructor to set up features such as back-to-top and dropdown behavior.
         *
         * Will skip initialization if the instance is disabled (invalid parameters).
         */
        init() {
            if (this._disabled) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_INIT_SKIPPED');
                return;
            }

            if (this._initialized) {
                return;
            }

            this._initialized = true;

            // Fire up the features.
            this._preloader();
            this._backToTop();
            this._bindOnepageNavbarStateObserver();
            this._handleViewportChange();
            this._scheduleOnepageNavbarTopStateSync();
            this._bindViewportListeners();
            this._addMmenuLinksHandler();
            this._listen(window, 'load', this._onOnepageNavStateChange);
            this._listen(window, 'hashchange', this._onOnepageNavStateChange);

            if (window.CopyMyPageDialog && typeof window.CopyMyPageDialog.initSystemMessages === 'function') {
                window.CopyMyPageDialog.initSystemMessages();
            }
        }

        /**
         * Destroys active runtime bindings and timers.
         * Safe to call multiple times.
         */
        destroy() {
            window.clearTimeout(this._scrollTimeout);
            window.clearTimeout(this._mmenuRestoreTimeout);
            window.clearTimeout(this._preloaderRemovalTimeout);
            window.cancelAnimationFrame(this._onepageNavSyncFrame);
            this._scrollTimeout = null;
            this._mmenuRestoreTimeout = null;
            this._preloaderRemovalTimeout = null;
            this._onepageNavSyncFrame = null;

            this._teardownDesktopUserDropdownHoldOpen();

            while (this._cleanupCallbacks.length > 0) {
                const cleanup = this._cleanupCallbacks.pop();

                try {
                    cleanup();
                } catch (e) {
                    // Ignore teardown errors to ensure all cleanup callbacks are attempted.
                }
            }

            this._viewportListenerBound = false;
            this._mmenuLinksHandlerBound = false;
            this._initialized = false;
        }

        /**
         * Registers a cleanup callback that is executed during destroy().
         *
         * @param {Function} cleanup - Cleanup function.
         */
        _addCleanup(cleanup) {
            if (typeof cleanup === 'function') {
                this._cleanupCallbacks.push(cleanup);
            }
        }

        /**
         * Creates media query listeners from centralized definitions.
         *
         * @returns {{small: MediaQueryList, narrow: MediaQueryList, mobile: MediaQueryList, tablet: MediaQueryList, uiDesktop: MediaQueryList}}
         */
        _createViewportQueries() {
            return Object.entries(VIEWPORT_QUERY_DEFINITIONS).reduce((queries, [key, query]) => {
                queries[key] = window.matchMedia(query);
                return queries;
            }, {});
        }

        /**
         * Log an error message to the console with a given key.
         *
         * @param {string} messageKey - The key of the error message to be logged.
         * @param {string} [el] - Optional element reference for the error message.
         */
        logError(messageKey, el = '') {
            const msg = Joomla?.Text?._(messageKey) ?? messageKey;

            if (el) {
                console.error(msg.replace('%s', el));
                return;
            }

            console.error(msg);
        }

        /**
         * Apply the given parameters as instance properties (key -> this[key]).
         * Ensures that only valid parameters are applied, preventing prototype method overrides.
         *
         * @param {Object} params - The parameters to apply to the instance.
         */
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

        /**
         * Private method: Fades out and removes the initial page preloader.
         */
        _preloader() {
            const tmpl = this.tmpl || {};

            if (!document.body) {
                return;
            }

            const preloader = this._select(tmpl.preloaderSelector || '#cmp-preloader');
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

                window.clearTimeout(this._preloaderRemovalTimeout);
                this._preloaderRemovalTimeout = window.setTimeout(removePreloader, 320);
            };

            const scheduleHidePreloader = () => {
                window.requestAnimationFrame(hidePreloader);
            };

            if (document.readyState === 'loading') {
                this._listen(document, 'DOMContentLoaded', scheduleHidePreloader, { once: true });
                this._listen(window, 'load', scheduleHidePreloader, { once: true });
                return;
            }

            scheduleHidePreloader();
            this._listen(window, 'load', scheduleHidePreloader, { once: true });
        }

        /**
         * Private method: Handles the back-to-top button functionality.
         * Includes visibility based on scroll position and click-to-scroll behavior.
         */
        _backToTop() {
            const tmpl = this.tmpl || {};

            this.backToTopButton = this._select(tmpl.backToTopSelector);
            if (!this.backToTopButton) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND');
                return;
            }

            this.backToTopPosition = this._toNumber(tmpl.backToTopPosition, 100);

            // Prefer DB param, fallback to the anchor href (single source of truth stays the markup/db).
            const hrefTarget = this.backToTopButton.getAttribute('href') || '';
            this.backToTopTargetSelector = tmpl.backToTopTargetSelector || hrefTarget || 'body';

            this._checkScrollPos();
            this._listen(window, 'scroll', this._onScroll, { passive: true });

            this._on('click', this.backToTopButton, (ev) => {
                ev.preventDefault();
                this._scrollToTarget(this.backToTopTargetSelector);
            });
        }

        /**
         * Private method: Smooth scroll to a target element (fallback to top).
         *
         * @param {string} selector - CSS selector of the target (e.g. "#main-content").
         */
        _scrollToTarget(selector) {
            const target = this._select(selector);

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            // Fallback: scroll to absolute top.
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Private method: Checks the current scroll position to show/hide the back-to-top button.
         * The button will appear if the scroll position exceeds `backToTopPosition`.
         */
        _checkScrollPos() {
            // More consistent across browsers than body.scrollTop.
            const scrollPosition = window.pageYOffset || document.documentElement.scrollTop || 0;
            this.backToTopButton.classList.toggle('visible', scrollPosition > this.backToTopPosition);
        }

        /**
         * Private method: Debounced scroll event handler for better performance.
         * Uses a short timeout and then a requestAnimationFrame to avoid layout thrashing.
         */
        _debouncedScroll() {
            window.clearTimeout(this._scrollTimeout);

            this._scrollTimeout = window.setTimeout(() => {
                window.requestAnimationFrame(() => {
                    this._checkScrollPos();
                    this._scheduleOnepageNavbarTopStateSync();
                });
            }, 100);
        }

        /**
         * Returns the current viewport state based on UIkit breakpoints.
         *
         * @returns {{name: string, mobile: boolean, narrow: boolean, small: boolean, tablet: boolean, desktop: boolean, uiDesktop: boolean}}
         */
        _getViewportState() {
            const small = this._viewportQueries.small.matches;
            const tablet = this._viewportQueries.tablet.matches;
            const mobile = this._viewportQueries.mobile.matches;
            const narrow = this._viewportQueries.narrow.matches;
            const uiDesktop = this._viewportQueries.uiDesktop.matches;
            const desktop = !small && !narrow && !mobile && !tablet;

            const name = this._resolveViewportName({ small, narrow, mobile, tablet });

            return { name, mobile, narrow, small, tablet, desktop, uiDesktop };
        }

        /**
         * Sync viewport-dependent body classes.
         */
        _applyViewportBodyClass() {
            if (!document.body) {
                return;
            }

            const viewport = this._getViewportState();
            document.body.classList.remove(...VIEWPORT_BODY_CLASSES);
            document.body.classList.add(`is-${viewport.name}`);
        }

        /**
         * Handle viewport changes for all responsive runtime behaviors.
         */
        _handleViewportChange() {
            this._applyViewportBodyClass();
            this._desktopUserDropdownHoldOpen();
            this._scheduleOnepageNavbarTopStateSync();
        }

        /**
         * Coalesces onepage navbar state syncs so rapid Scrollspy mutations settle in one frame.
         */
        _scheduleOnepageNavbarTopStateSync() {
            if (this._onepageNavSyncFrame !== null) {
                return;
            }

            this._onepageNavSyncFrame = window.requestAnimationFrame(() => {
                this._onepageNavSyncFrame = null;
                this._syncOnepageNavbarTopState();
            });
        }

        /**
         * Resolve the onepage navbar container used by the Scrollspy state sync.
         *
         * @returns {HTMLElement|null}
         */
        _getOnepageNavbar() {
            const navbar = document.querySelector(ONEPAGE_NAVBAR_SELECTOR);
            return navbar instanceof HTMLElement ? navbar : null;
        }

        /**
         * Watches ScrollspyNav class mutations so the top-state correction can run immediately.
         */
        _bindOnepageNavbarStateObserver() {
            if (!document.body || !document.body.classList.contains('is-onepage')) {
                return;
            }

            const navbar = this._getOnepageNavbar();

            if (!navbar) {
                return;
            }

            const observer = new MutationObserver((mutations) => {
                const hasRelevantChange = mutations.some((mutation) => mutation.type === 'attributes');

                if (!hasRelevantChange) {
                    return;
                }

                this._scheduleOnepageNavbarTopStateSync();
            });

            observer.observe(navbar, {
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'aria-current'],
            });

            this._addCleanup(() => observer.disconnect());
        }

        /**
         * Keeps the first visible onepage nav item active while the viewport is at the true page top.
         *
         * UIkit ScrollspyNav intentionally activates sections early (header offset + viewport threshold),
         * which can cause the second item to win on reload when the first section is short.
         */
        _syncOnepageNavbarTopState() {
            if (!document.body || !document.body.classList.contains('is-onepage')) {
                return;
            }

            const navbar = this._getOnepageNavbar();

            if (!navbar) {
                return;
            }

            const scrollLinks = Array.from(navbar.querySelectorAll(':scope > li > a[data-cmp-scroll="1"]'));

            if (scrollLinks.length === 0) {
                return;
            }

            const firstLink = scrollLinks[0];
            const firstItem = firstLink.closest('li');

            if (!(firstItem instanceof HTMLElement)) {
                return;
            }

            const rootStyles = getComputedStyle(document.documentElement);
            const stickyOffset = Number.parseFloat(rootStyles.getPropertyValue('--cmp-header-offset')) || 0;
            const scrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
            const secondLink = scrollLinks[1] || null;
            const secondTargetSelector = secondLink?.getAttribute('href') || '';
            const secondTarget = secondTargetSelector.startsWith('#')
                ? document.querySelector(secondTargetSelector)
                : null;
            const secondTargetTop = secondTarget instanceof HTMLElement
                ? secondTarget.getBoundingClientRect().top
                : null;
            const hasOtherActiveItem = scrollLinks.slice(1).some((link) => {
                const item = link.closest('li');
                return item instanceof HTMLElement && item.classList.contains('uk-active');
            });
            const shouldPinFirstLink = scrollY <= stickyOffset + 1
                && (secondTargetTop === null || secondTargetTop > stickyOffset + 1);

            if (shouldPinFirstLink) {
                scrollLinks.slice(1).forEach((link) => {
                    const item = link.closest('li');

                    if (item instanceof HTMLElement) {
                        item.classList.remove('uk-active');
                    }

                    if (link.getAttribute('aria-current') === 'page') {
                        link.removeAttribute('aria-current');
                    }
                });

                firstItem.classList.add('uk-active');

                if (firstLink.dataset.cmpTopActive !== '1') {
                    firstLink.dataset.cmpTopActive = '1';
                }

                if (firstLink.getAttribute('aria-current') !== 'page') {
                    firstLink.setAttribute('aria-current', 'page');
                    firstLink.dataset.cmpTopAriaCurrent = '1';
                }

                return;
            }

            if (firstLink.dataset.cmpTopActive === '1' && hasOtherActiveItem) {
                firstItem.classList.remove('uk-active');
                delete firstLink.dataset.cmpTopActive;
            }

            if (firstLink.dataset.cmpTopAriaCurrent === '1' && hasOtherActiveItem) {
                firstLink.removeAttribute('aria-current');
                delete firstLink.dataset.cmpTopAriaCurrent;
            }
        }

        /**
         * Normalize URL path names so "/de" and "/de/" are treated equally.
         *
         * @param {string} path - Pathname to normalize.
         * @returns {string}
         */
        _normalizePath(path) {
            const trimmed = String(path || '').replace(/\/+$/, '');
            return trimmed || '/';
        }

        /**
         * Bind viewport listeners once so viewport-dependent behavior can react to live resize.
         */
        _bindViewportListeners() {
            if (this._viewportListenerBound) {
                return;
            }

            this._viewportListenerBound = true;
            const queries = Object.values(this._viewportQueries);

            for (const query of queries) {
                if (typeof query.addEventListener === 'function') {
                    this._listen(query, 'change', this._onViewportChange);
                    continue;
                }

                // Legacy fallback without deprecated addListener API.
                if ('onchange' in query) {
                    query.onchange = this._onViewportChange;
                    this._addCleanup(() => {
                        if (query.onchange === this._onViewportChange) {
                            query.onchange = null;
                        }
                    });
                }
            }
        }

        /**
         * Remove active desktop dropdown listeners if present.
         */
        _teardownDesktopUserDropdownHoldOpen() {
            if (typeof this._dropdownHoldOpenCleanup === 'function') {
                this._dropdownHoldOpenCleanup();
                this._dropdownHoldOpenCleanup = null;
            }
        }

        /**
         * Private method: Keeps the user dropdown open on desktop when hovering.
         * Ensures that both dropdowns (main navbar and user menu) do not open simultaneously.
         */
        _desktopUserDropdownHoldOpen() {
            const opt = this.mod.navbar || {};

            if (!opt || !UIkit?.util) {
                return;
            }

            const enabled = this._toBool(opt.userDropdownHoldOpenEnabled);

            if (!enabled) {
                this._teardownDesktopUserDropdownHoldOpen();
                return;
            }

            const viewport = this._getViewportState();

            if (!viewport.uiDesktop) {
                this._teardownDesktopUserDropdownHoldOpen();
                return;
            }

            // Already active for desktop; avoid duplicate listeners on repeated resize events.
            if (this._dropdownHoldOpenCleanup) {
                return;
            }

            const closeDelay = this._toInteger(opt.userDropdownCloseDelay, 180);

            const closeOnNavClick = this._toBool(opt.userDropdownCloseOnNavClick);

            // Selector hooks (DB-backed).
            const selectors = {
                root: opt.userDropdownSelectorRoot,
                user: opt.userDropdownSelectorUser,
                toggle: opt.userDropdownSelectorToggle,
                dropdown: opt.userDropdownSelectorDropdown,
                navbarDropdown: opt.userDropdownSelectorNavbarDropdown,
            };

            const root = this._select(selectors.root);
            const user = this._select(selectors.user);
            const toggle = this._select(selectors.toggle);
            const dropdown = this._select(selectors.dropdown);

            // navbarDropdown is optional (only needed to hide other open dropdowns).
            const navbarDropdown = this._select(selectors.navbarDropdown);

            if (!root || !user || !toggle || !dropdown) {
                this._teardownDesktopUserDropdownHoldOpen();
                return;
            }

            const drop = UIkit.getComponent(dropdown, 'drop') || UIkit.drop(dropdown);

            if (!drop?.show || !drop?.hide) {
                this._teardownDesktopUserDropdownHoldOpen();
                return;
            }

            const hideNavbarDropdown = () => {
                if (!navbarDropdown) {
                    return;
                }

                const navbarDrop = UIkit.getComponent(navbarDropdown, 'drop') || UIkit.drop(navbarDropdown);
                navbarDrop?.hide?.(false);
            };

            let t;

            const inside = () =>
                toggle.matches(':hover, :focus')
                || dropdown.matches(':hover, :focus')
                || dropdown.contains(document.activeElement);

            const keepOpen = () => window.clearTimeout(t);

            const closeLater = () => {
                window.clearTimeout(t);

                t = window.setTimeout(() => {
                    if (!inside()) {
                        drop.hide(false);
                    }
                }, closeDelay);
            };

            const onBeforeHide = (e) => {
                if (inside()) {
                    e.preventDefault();
                }
            };

            const onToggleMouseEnter = () => {
                keepOpen();
                drop.show();
                hideNavbarDropdown();
            };

            const onToggleMouseLeave = closeLater;
            const onDropdownMouseEnter = keepOpen;
            const onDropdownMouseLeave = closeLater;

            UIkit.util.on(dropdown, 'beforehide', onBeforeHide);
            toggle.addEventListener('mouseenter', onToggleMouseEnter);
            toggle.addEventListener('mouseleave', onToggleMouseLeave);
            dropdown.addEventListener('mouseenter', onDropdownMouseEnter);
            dropdown.addEventListener('mouseleave', onDropdownMouseLeave);

            const onDropdownClick = (ev) => {
                const a = ev.target?.closest?.('a');

                if (a && a.getAttribute('href') && a.getAttribute('href') !== '#') {
                    drop.hide(false);
                }
            };

            if (closeOnNavClick) {
                dropdown.addEventListener('click', onDropdownClick);
            }

            this._dropdownHoldOpenCleanup = () => {
                window.clearTimeout(t);
                UIkit.util.off?.(dropdown, 'beforehide', onBeforeHide);
                toggle.removeEventListener('mouseenter', onToggleMouseEnter);
                toggle.removeEventListener('mouseleave', onToggleMouseLeave);
                dropdown.removeEventListener('mouseenter', onDropdownMouseEnter);
                dropdown.removeEventListener('mouseleave', onDropdownMouseLeave);

                if (closeOnNavClick) {
                    dropdown.removeEventListener('click', onDropdownClick);
                }
            };
        }

        /**
         * Activates the scroll logic for each menu item in the mmenu.
         * Removes the scroll-blocking class and allows UIkit to do its job.
         */
        _addMmenuLinksHandler() {
            const navOffcanvasId = this.mod?.navbar?.navOffcanvasId;

            if (!navOffcanvasId || this._mmenuLinksHandlerBound) {
                return;
            }

            // Escape the ID for use in querySelector (in case it contains special characters).
            const escapedOffcanvasId = window.CSS?.escape ? window.CSS.escape(navOffcanvasId) : navOffcanvasId;
            const offcanvas = this._select(`#${escapedOffcanvasId}`);

            if (!offcanvas) {
                return;
            }

            this._mmenuLinksHandlerBound = true;

            let isMenuClick = false;
            let observedTarget = null;
            let observerCleanupTimeout = null;

            const cleanupObservedTarget = () => {
                if (observerCleanupTimeout) {
                    window.clearTimeout(observerCleanupTimeout);
                    observerCleanupTimeout = null;
                }

                if (observedTarget) {
                    mmenuObserver.unobserve(observedTarget);
                    observedTarget = null;
                }
            };

            const mmenuObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting || !isMenuClick || entry.target !== observedTarget) {
                        return;
                    }

                    // Add the scroll-blocking class back after a short delay.
                    window.clearTimeout(this._mmenuRestoreTimeout);
                    this._mmenuRestoreTimeout = window.setTimeout(() => {
                        document.body.classList.add('mm-ocd-opened');
                    }, 750); // Delay to allow UIkit to do its job.

                    isMenuClick = false;
                    cleanupObservedTarget();
                });
            }, { threshold: 0.1 });

            const observeTarget = (target) => {
                cleanupObservedTarget();
                observedTarget = target;
                mmenuObserver.observe(target);

                // Safety cleanup in case the target never intersects.
                observerCleanupTimeout = window.setTimeout(() => {
                    isMenuClick = false;
                    cleanupObservedTarget();
                }, 3000);
            };

            const onOffcanvasClick = (ev) => {
                const link = ev.target?.closest?.('a[href*="#"]');

                if (!link || !offcanvas.contains(link)) {
                    return;
                }

                const href = (link.getAttribute('href') || '').trim();

                if (!href || href === '#') {
                    return;
                }

                let parsedUrl;

                try {
                    parsedUrl = new URL(href, window.location.href);
                } catch (e) {
                    return;
                }

                const targetSelector = parsedUrl.hash;

                if (!targetSelector || targetSelector === '#') {
                    return;
                }

                const currentPath = this._normalizePath(window.location.pathname);
                const targetPath = this._normalizePath(parsedUrl.pathname);
                const isSameDocument = parsedUrl.origin === window.location.origin
                    && targetPath === currentPath
                    && parsedUrl.search === window.location.search;

                // For cross-page links (e.g. /de/#hero from another view), allow normal navigation.
                if (!isSameDocument && !href.startsWith('#')) {
                    return;
                }

                const target = this._select(targetSelector);

                if (!target) {
                    return;
                }

                ev.preventDefault();

                window.clearTimeout(this._mmenuRestoreTimeout);
                isMenuClick = true;

                // Temporarily remove the scroll-blocking class.
                document.body.classList.remove('mm-ocd-opened');

                // Scroll to the target section.
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });

                observeTarget(target);
            };

            this._listen(offcanvas, 'click', onOffcanvasClick);
            this._addCleanup(() => {
                cleanupObservedTarget();
                mmenuObserver.disconnect();
                this._mmenuLinksHandlerBound = false;
            });
        }

        /**
         * Resolves the current viewport name by priority.
         *
         * @param {{small: boolean, narrow: boolean, mobile: boolean, tablet: boolean}} viewport
         * @returns {string}
         */
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

        /**
         * Normalizes common truthy config values (true/1/"1").
         *
         * @param {unknown} value - Value to normalize.
         * @returns {boolean}
         */
        _toBool(value) {
            return value === true || value === 1 || value === '1';
        }

        /**
         * Parses integer-like config values and falls back on invalid/0.
         * Mirrors legacy `parseInt(value, 10) || fallback` behavior.
         *
         * @param {unknown} value - Value to parse.
         * @param {number} fallback - Fallback value.
         * @returns {number}
         */
        _toInteger(value, fallback) {
            return Number.parseInt(value, 10) || fallback;
        }

        /**
         * Parses number-like config values and falls back on null/undefined.
         * Mirrors legacy `Number(value ?? fallback)` behavior.
         *
         * @param {unknown} value - Value to parse.
         * @param {number} fallback - Fallback value.
         * @returns {number}
         */
        _toNumber(value, fallback) {
            return Number(value ?? fallback);
        }

        /**
         * Adds an event listener and auto-registers its removal for destroy().
         *
         * @param {EventTarget} target - Event target.
         * @param {string} type - Event type.
         * @param {Function} listener - Event callback.
         * @param {boolean|AddEventListenerOptions} [options] - Listener options.
         */
        _listen(target, type, listener, options) {
            if (!target || typeof target.addEventListener !== 'function' || typeof listener !== 'function') {
                return;
            }

            target.addEventListener(type, listener, options);
            this._addCleanup(() => target.removeEventListener(type, listener, options));
        }

        /**
         * Private helper method: Adds an event listener to a specified element.
         *
         * @param {string} type - The event type (e.g., 'click', 'scroll').
         * @param {string|HTMLElement} el - The CSS selector or HTMLElement to attach the listener to.
         * @param {Function} listener - The event listener function to execute when the event occurs.
         */
        _on(type, el, listener) {
            const selectEl = this._select(el);

            if (!selectEl) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND', typeof el === 'string' ? el : '[HTMLElement]');
                return;
            }

            this._listen(selectEl, type, listener);
        }

        /**
         * Selects a DOM element (or list of elements) by selector.
         * Logs an error when the selector is invalid OR when no element is found.
         *
         * @param {string|HTMLElement} el - The CSS selector or HTMLElement.
         * @param {boolean} all - Whether to select one or all matching elements.
         * @returns {Element|Element[]|null} - The selected DOM element(s) or null if not found.
         */
        _select(el, all = false) {
            if (el instanceof HTMLElement) {
                return el;
            }

            if (typeof el !== 'string') {
                return null;
            }

            const selector = el.trim();

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
            } catch (e) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_SELECTOR', selector);
                return null;
            }
        }

        /**
         * Static method for handling AJAX requests with Joomla API.
         *
         * @param {Object} options - The AJAX request options.
         * @returns {Promise} - The result of the AJAX request.
         */
        static request(options) {
            return Joomla.request(options);
        }
    }

    window.CopyMyPage = CopyMyPage;

})(window.Joomla, window.UIkit, window, document);
