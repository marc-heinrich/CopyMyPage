/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

window.CopyMyPage = window.CopyMyPage || {};

(function(Joomla, window, document) {

    "use strict";

    /**
     * CopyMyPage Class
     * Provides functionality for template interactions and behaviors.
     */
    class CopyMyPage {

        constructor(params) {
            // Validate and assign basic params.
            if (!params || typeof params !== 'object') {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_INVALID_PARAMS'));
                return;
            }

            // Validate and store the back-to-top button element.
            this.backToTopButton = this._select(params.backToTopID || '#back-top');
            if (!this.backToTopButton) {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_BACKTOTOP_NOT_FOUND'));
                return;
            }

            // Set the scroll position threshold (default to 100px if not provided).
            this.scrollTopPosition = params.scrollTopPosition || 100;
        }

        /**
         * Initializes the template functionality.
         * Can be expanded for more view-specific methods (e.g., AJAX, form handling, etc.)
         */
        init() {
            this._initBackToTop();
            // More methods can be initialized here (e.g., AJAX, form handling, etc.)
        }

        /**
         * Private method: Back to Top Button behavior.
         * Handles button visibility and click-to-scroll behavior.
         */
        _initBackToTop() {
            this._checkScrollPos();

            // Using requestAnimationFrame for better scroll performance.
            window.addEventListener('scroll', this._debouncedScroll.bind(this));

            // Smooth scroll to top on click.
            this._on('click', this.backToTopButton, (ev) => {
                ev.preventDefault();
                this._scrollToTop();
            });
        }

        /**
         * Private method: Checks scroll position and shows/hides the back-to-top button.
         */
        _checkScrollPos() {
            if (document.body.scrollTop > this.scrollTopPosition || document.documentElement.scrollTop > this.scrollTopPosition) {
                this.backToTopButton.classList.add('visible');
            } else {
                this.backToTopButton.classList.remove('visible');
            }
        }

        /**
         * Private helper method: Easy event listener function.
         * @param {String} type - The event type.
         * @param {String} el - The CSS selector for the element.
         * @param {Function} listener - The event listener function.
         */
        _on(type, el, listener) {
            const selectEl = this._select(el);
            if (selectEl) {
                selectEl.addEventListener(type, listener);
            } else {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_ELEMENT_NOT_FOUND').replace('%s', el));
            }
        }

        /**
         * Private helper method: Easy selector function.
         * @param {String|HTMLElement} el - The CSS selector or HTMLElement.
         * @param {Boolean} all - Whether to select one or all matching elements.
         * @returns {NodeList|Element} - The selected DOM element(s).
         */
        _select(el, all = false) {
            if (el instanceof HTMLElement) {
                return el; 
            }

            el = el.trim(); 
            let selected;
            try {
                selected = all ? [...document.querySelectorAll(el)] : document.querySelector(el);
                if (!selected) {
                    throw new Error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_SELECTOR').replace('%s', el));
                }
            } catch (e) {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_SELECTOR').replace('%s', el));
                return null;
            }
            return selected;
        }

        /**
         * Private method: Smooth scroll to the top of the page.
         */
        _scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        /**
         * Private method: Debounced scroll event handler for better performance.
         */
        _debouncedScroll() {
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = setTimeout(() => {
                window.requestAnimationFrame(() => this._checkScrollPos());
            }, 100);
        }

        /**
         * Static method for handling AJAX requests with Joomla API.
         * @param {Object} options - AJAX options for the request.
         */
        static request(options) {
            return Joomla.request(options);
        }        
    }

    // Expose the class globally as window.CopyMyPage
    window.CopyMyPage = CopyMyPage;

})(Joomla, window, document);
