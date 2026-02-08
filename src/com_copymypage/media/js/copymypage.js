/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (Joomla, UIkit, window, document) {
    'use strict';

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

            // Internal timers/state.
            this._scrollTimeout = null;
            this._mmenuRestoreTimeout = null;
            this._initialized = false;

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
            this._backToTop();
            this._desktopUserDropdownHoldOpen();
            this._addMmenuLinksHandler();
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

            this.backToTopPosition = Number(tmpl.backToTopPosition ?? 100);

            // Prefer DB param, fallback to the anchor href (single source of truth stays the markup/db).
            const hrefTarget = this.backToTopButton.getAttribute('href') || '';
            this.backToTopTargetSelector = tmpl.backToTopTargetSelector || hrefTarget || 'body';

            this._checkScrollPos();
            window.addEventListener('scroll', this._onScroll, { passive: true });

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
                window.requestAnimationFrame(() => this._checkScrollPos());
            }, 100);
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

            const enabled = opt.userDropdownHoldOpenEnabled === true
                || opt.userDropdownHoldOpenEnabled === 1
                || opt.userDropdownHoldOpenEnabled === '1';

            if (!enabled) {
                return;
            }

            const desktopMin = parseInt(opt.userDropdownDesktopMin, 10) || 960;

            if (!window.matchMedia(`(min-width: ${desktopMin}px)`).matches) {
                return;
            }

            const closeDelay = parseInt(opt.userDropdownCloseDelay, 10) || 180;

            const closeOnNavClick = opt.userDropdownCloseOnNavClick === true
                || opt.userDropdownCloseOnNavClick === 1
                || opt.userDropdownCloseOnNavClick === '1';

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
                return;
            }

            const drop = UIkit.getComponent(dropdown, 'drop') || UIkit.drop(dropdown);

            if (!drop?.show || !drop?.hide) {
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

            UIkit.util.on(dropdown, 'beforehide', (e) => {
                if (inside()) {
                    e.preventDefault();
                }
            });

            toggle.addEventListener('mouseenter', () => {
                keepOpen();
                drop.show();
                hideNavbarDropdown();
            });

            toggle.addEventListener('mouseleave', closeLater);

            dropdown.addEventListener('mouseenter', keepOpen);
            dropdown.addEventListener('mouseleave', closeLater);

            if (closeOnNavClick) {
                dropdown.addEventListener('click', (ev) => {
                    const a = ev.target?.closest?.('a');

                    if (a && a.getAttribute('href') && a.getAttribute('href') !== '#') {
                        drop.hide(false);
                    }
                });
            }
        }

        /**
         * Activates the scroll logic for each menu item in the mmenu.
         * Removes the scroll-blocking class and allows UIkit to do its job.
         */
        _addMmenuLinksHandler() {
            const navOffcanvasId = this.mod?.navbar?.navOffcanvasId;

            if (!navOffcanvasId) {
                return;
            }

            const escapedOffcanvasId = window.CSS?.escape ? window.CSS.escape(navOffcanvasId) : navOffcanvasId;

            // All links in the mmenu that point to a section.
            const mmenuLinks = this._select(`#${escapedOffcanvasId} a[href^="#"]`, true);

            if (!mmenuLinks || mmenuLinks.length === 0) {
                return;
            }

            let isMenuClick = false;  // Flag to track if the user clicked a mmenu link.

            mmenuLinks.forEach(link => {
                link.addEventListener('click', (ev) => {
                    const targetSelector = link.getAttribute('href');

                    if (!targetSelector || targetSelector === '#') {
                        return;
                    }

                    const target = this._select(targetSelector);

                    if (!target) {
                        return;
                    }

                    ev.preventDefault();

                    window.clearTimeout(this._mmenuRestoreTimeout);
                    isMenuClick = true;  // Set the flag to true since the user clicked a menu item.

                    // Temporarily remove the scroll-blocking class.
                    document.body.classList.remove('mm-ocd-opened');

                    // Scroll to the target section.
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });

                    // Observe the target to check when it becomes visible.
                    let observer = null;
                    let observerCleanupTimeout = null;

                    const cleanupObserver = () => {
                        if (observerCleanupTimeout) {
                            window.clearTimeout(observerCleanupTimeout);
                            observerCleanupTimeout = null;
                        }

                        observer?.disconnect?.();
                        observer = null;
                    };

                    observer = new IntersectionObserver((entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting && isMenuClick) {
                                // Add the scroll-blocking class back after a short delay.
                                window.clearTimeout(this._mmenuRestoreTimeout);
                                this._mmenuRestoreTimeout = window.setTimeout(() => {
                                    document.body.classList.add('mm-ocd-opened');
                                }, 750); // Delay to allow UIkit to do its job.
                                isMenuClick = false;  // Reset the flag.
                                cleanupObserver();
                            }
                        });
                    }, { threshold: 0.1 });

                    // Observe the target element.
                    observer.observe(target);

                    // Safety cleanup in case the target never intersects.
                    observerCleanupTimeout = window.setTimeout(() => {
                        cleanupObserver();
                    }, 3000);
                });
            });
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

            selectEl.addEventListener(type, listener);
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
