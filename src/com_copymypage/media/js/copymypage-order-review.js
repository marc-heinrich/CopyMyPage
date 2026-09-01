/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

(function (window, document) {
    'use strict';

    const formSelector = '[data-cmp-order-review-form]';
    const continueSelector = '[data-cmp-order-review-continue]';
    const termsSelector = '[data-cmp-order-review-terms]';
    const formSynchronisers = new WeakMap();

    const initialiseForm = (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const existingSynchroniser = formSynchronisers.get(form);

        if (existingSynchroniser) {
            existingSynchroniser();

            return;
        }

        const continueButton = form.querySelector(continueSelector);
        const terms = form.querySelector(termsSelector);

        if (!(continueButton instanceof HTMLButtonElement)) {
            return;
        }

        const checkoutReady = continueButton.dataset.cmpOrderReviewReady === 'true';
        const syncContinueButton = () => {
            const termsRequired = terms instanceof HTMLInputElement && terms.required;
            const termsAccepted = !termsRequired || terms.checked;
            const disabled = !checkoutReady || !termsAccepted;

            continueButton.disabled = disabled;
            continueButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        };

        formSynchronisers.set(form, syncContinueButton);

        if (terms instanceof HTMLInputElement) {
            terms.addEventListener('change', syncContinueButton);
        }

        form.addEventListener('reset', () => window.setTimeout(syncContinueButton, 0));
        syncContinueButton();
    };

    const initialise = (scope = document) => {
        if (!scope || typeof scope.querySelectorAll !== 'function') {
            return;
        }

        if (scope instanceof Element && scope.matches(formSelector)) {
            initialiseForm(scope);
        }

        scope.querySelectorAll(formSelector).forEach(initialiseForm);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialise(document), { once: true });
    } else {
        initialise(document);
    }

    window.addEventListener('pageshow', () => initialise(document));
    document.addEventListener('joomla:updated', (event) => initialise(event.target));
}(window, document));
