/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

(function (window, document) {
    'use strict';

    const formSelector = '[data-cmp-order-review-form]';
    const continueSelector = '[data-cmp-order-review-continue]';
    const termsSelector = '[data-cmp-order-review-terms]';
    const paymentSelector = '[data-cmp-order-review-payment-total]';
    const totalLabelSelector = '[data-cmp-order-review-total-label]';
    const totalValueSelector = '[data-cmp-order-review-total-value]';
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
        const paymentInputs = Array.from(form.querySelectorAll(paymentSelector));
        const totalLabel = form.querySelector(totalLabelSelector);
        const totalValue = form.querySelector(totalValueSelector);

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
        const syncPaymentTotal = () => {
            if (!(totalLabel instanceof HTMLElement) || !(totalValue instanceof HTMLElement)) {
                return;
            }

            const selectedPayment = paymentInputs.find((input) => (
                input instanceof HTMLInputElement && input.checked
            ));

            totalLabel.textContent = selectedPayment
                ? totalLabel.dataset.cmpOrderReviewSelectedLabel || ''
                : totalLabel.dataset.cmpOrderReviewBaseLabel || '';
            totalValue.textContent = selectedPayment
                ? selectedPayment.dataset.cmpOrderReviewPaymentTotal || ''
                : totalValue.dataset.cmpOrderReviewBaseTotal || '';
        };
        const syncForm = () => {
            syncContinueButton();
            syncPaymentTotal();
        };

        formSynchronisers.set(form, syncForm);

        if (terms instanceof HTMLInputElement) {
            terms.addEventListener('change', syncContinueButton);
        }

        paymentInputs.forEach((input) => input.addEventListener('change', syncPaymentTotal));

        form.addEventListener('reset', () => window.setTimeout(syncForm, 0));
        syncForm();
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
