/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (Joomla, UIkit, window, document) {
    "use strict";

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

            // Initialize features.
            this._backToTop();
            this._desktopUserDropdownHoldOpen();
        }

        /**
         * Log an error message to the console with a given key.
         * 
         * @param {string} messageKey - The key of the error message to be logged.
         * @param {string} [el] - Optional element reference for the error message.
         */
        logError(messageKey, el = '') {
            if (el) {
                console.error(Joomla.Text._(messageKey).replace('%s', el));
            } else {
                console.error(Joomla.Text._(messageKey));
            }
        }

        /**
         * Apply the given parameters as instance properties (key -> this[key]).
         * This method ensures that only valid parameters are applied, preventing prototype method overrides.
         * 
         * @param {Object} params - The parameters to apply to the instance.
         */
        _applyParams(params) {
            const forbidden = new Set(['__proto__', 'prototype', 'constructor']);
            const protoKeys = new Set(Object.getOwnPropertyNames(Object.getPrototypeOf(this)));

            for (const [key, value] of Object.entries(params)) {
                if (forbidden.has(key) || protoKeys.has(key) || key.startsWith('_')) continue;
                this[key] = value;
            }
        }

        /**
         * Private method: Handles the back-to-top button functionality.
         * This includes visibility based on scroll position and click-to-scroll behavior.
         */
        _backToTop() {
            this.backToTopButton = this._select(this.backToTopID);

            if (!this.backToTopButton) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND', this.backToTopID);
                return;
            }

            this.scrollTopPosition = Number(this.scrollTopPosition);

            this._checkScrollPos();

            window.addEventListener('scroll', this._debouncedScroll.bind(this));

            this._on('click', this.backToTopButton, (ev) => {
                ev.preventDefault();
                this._scrollToTop();
            });
        }

        /**
         * Private method: Checks the current scroll position to show/hide the back-to-top button.
         * The button will appear if the scroll position exceeds `scrollTopPosition`.
         */
        _checkScrollPos() {
            const scrollPosition = document.body.scrollTop || document.documentElement.scrollTop;
            this.backToTopButton.classList.toggle('visible', scrollPosition > this.scrollTopPosition);
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
            if (selectEl) selectEl.addEventListener(type, listener);
            else this.logError('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND', el);
        }

        /**
         * Private helper method: Selects an element based on a CSS selector.
         * If `all` is true, returns all matching elements.
         * 
         * @param {string|HTMLElement} el - The CSS selector or HTMLElement to select.
         * @param {boolean} all - Whether to select one or all matching elements.
         * @returns {NodeList|Element|null} - The selected DOM element(s), or null if no element is found.
         */
        _select(el, all = false) {
            if (el instanceof HTMLElement) return el;

            try {
                return all ? [...document.querySelectorAll(el.trim())] : document.querySelector(el.trim());
            } catch (e) {
                this.logError('TPL_COPYMYPAGE_JS_ERROR_SELECTOR', el);
                return null;
            }
        }

        /**
         * Private method: Smooth scroll to the top of the page.
         */
        _scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        /**
         * Private method: Debounced scroll event handler for better performance.
         * This ensures the scroll position is checked at a controlled rate, improving performance.
         */
        _debouncedScroll() {
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = setTimeout(() => {
                window.requestAnimationFrame(() => this._checkScrollPos());
            }, 100);
        }

        /**
         * Private method: Keeps the user dropdown open on desktop when hovering.
         */
        _desktopUserDropdownHoldOpen() {
            const opt = this.userDropdownHoldOpen;

            // Feature is optional.
            if (!opt || !UIkit?.util || !window.matchMedia(`(min-width: ${opt.desktopMin}px)`).matches) return;

            const { selectors, closeDelay, closeOnNavClick } = opt;

            // Select the elements directly.
            const root = document.querySelector(selectors.root);
            const user = root?.querySelector(selectors.user);
            const toggle = user?.querySelector(selectors.toggle);
            const dropdown = user?.querySelector(selectors.dropdown);
            const navbarDropdown = document.querySelector('.cmp-navbar .uk-navbar-dropdown');

            if (!root || !user || !toggle || !dropdown) return;

            const drop = UIkit.getComponent(dropdown, 'drop') || UIkit.drop(dropdown);
            if (!drop?.show || !drop?.hide) return;

            // Ensure that the main navbar dropdown is hidden when the user menu is shown.
            const hideNavbarDropdown = () => {
                if (navbarDropdown) {
                    const navbarDrop = UIkit.getComponent(navbarDropdown, 'drop');
                    navbarDrop?.hide(false);
                }
            };

            let t;
            const inside = () =>
                toggle.matches(':hover, :focus') || dropdown.matches(':hover, :focus') || dropdown.contains(document.activeElement);

            const closeLater = () => {
                window.clearTimeout(t);
                t = window.setTimeout(() => { 
                    if (!inside()) {
                        drop.hide(false); 
                    }
                }, closeDelay);
            };

            const keepOpen = () => window.clearTimeout(t);

            UIkit.util.on(dropdown, 'beforehide', (e) => { 
                if (inside()) e.preventDefault(); 
            });

            // Hover to show and close main navbar dropdown when hovering over user menu.
            toggle.addEventListener('mouseenter', () => { 
                keepOpen(); 
                drop.show(); 
                hideNavbarDropdown();  // Close the main navbar dropdown when hovering the user menu.
            });

            // Hover to close the user menu dropdown after a delay.
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
