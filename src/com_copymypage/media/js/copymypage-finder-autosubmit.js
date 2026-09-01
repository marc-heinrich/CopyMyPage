/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

(function (window, document) {
    'use strict';

    const inputSelector = '.cmp-finder__form .js-finder-search-query';
    const advancedSelector = '.js-finder-advanced';
    const initialisedInputs = new WeakSet();

    const submitSelectedSuggestion = (event) => {
        const input = event.currentTarget;

        if (!(input instanceof window.Element)) {
            return;
        }

        const form = input.closest('form');

        // Joomla already handles forms without advanced search fields.
        if (!form || !form.querySelector(advancedSelector)) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

        if (submitButton instanceof window.HTMLElement) {
            submitButton.click();
        }
    };

    const initialiseInput = (input) => {
        if (initialisedInputs.has(input)) {
            return;
        }

        const form = input.closest('form');

        if (!form || !form.querySelector(advancedSelector)) {
            return;
        }

        initialisedInputs.add(input);
        input.addEventListener('awesomplete-selectcomplete', submitSelectedSuggestion);
    };

    const initialise = (root) => {
        if (root instanceof window.Element && root.matches(inputSelector)) {
            initialiseInput(root);
        }

        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;

        scope.querySelectorAll(inputSelector).forEach(initialiseInput);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialise(document), { once: true });
    } else {
        initialise(document);
    }

    document.addEventListener('joomla:updated', (event) => initialise(event.target));
}(window, document));
