/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

(function (window, document, Joomla, submitForm) {
    'use strict';

    const buttonDataSelector = 'data-submit-task';
    const defaultFormId = 'op-contactForm';
    const checkboxSelector = 'input.form-check-input';

    const getText = (key, fallback) => {
        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    };

    const toggleCheckboxValue = (checkbox) => {
        checkbox.value = checkbox.value === '0' ? '1' : '0';
    };

    const resolveForm = (button) => {
        const customSelector = button.getAttribute('data-submit-form');

        if (customSelector) {
            return document.querySelector(customSelector) || document.getElementById(customSelector.replace(/^#/, ''));
        }

        return button.closest('form') || document.getElementById(defaultFormId);
    };

    const shouldConfirm = (button, form) => {
        const explicit = button.getAttribute('data-confirm');

        if (explicit === 'true') {
            return true;
        }

        if (explicit === 'false') {
            return false;
        }

        return !!form && form.id === defaultFormId;
    };

    const showInvalidFormMessage = async () => {
        const title = getText('WARNING', 'Warning');
        const message = getText('JGLOBAL_VALIDATION_FORM_FAILED', 'Invalid form');

        if (window.CopyMyPageDialog && typeof window.CopyMyPageDialog.alert === 'function') {
            await window.CopyMyPageDialog.alert(message, title);
            return;
        }

        if (window.UIkit && typeof window.UIkit.notification === 'function') {
            window.UIkit.notification({
                message,
                status: 'warning'
            });
            return;
        }

        window.alert(message);
    };

    const confirmSubmit = async (button) => {
        const title = button.getAttribute('data-confirm-title') || getText('JNOTICE', 'Confirm');
        const message = button.getAttribute('data-confirm-message') || 'Do you want to submit the form?';

        if (window.CopyMyPageDialog && typeof window.CopyMyPageDialog.confirm === 'function') {
            return window.CopyMyPageDialog.confirm(message, title);
        }

        return window.confirm(message);
    };

    const validateForm = (form) => {
        if (!document.formvalidator || typeof document.formvalidator.isValid !== 'function') {
            return true;
        }

        return document.formvalidator.isValid(form);
    };

    const submitTask = async (task, button) => {
        const form = resolveForm(button);

        if (!form || !task) {
            return;
        }

        if (!validateForm(form)) {
            await showInvalidFormMessage();
            return;
        }

        if (shouldConfirm(button, form)) {
            const approved = await confirmSubmit(button);

            if (!approved) {
                return;
            }
        }

        submitForm(task, form);
    };

    document.addEventListener('DOMContentLoaded', () => {
        const checkboxes = Array.from(document.querySelectorAll(checkboxSelector));
        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('click', () => toggleCheckboxValue(checkbox));
        });

        const buttons = Array.from(document.querySelectorAll(`[${buttonDataSelector}]`));
        buttons.forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const task = button.getAttribute(buttonDataSelector);
                await submitTask(task, button);
            });
        });
    });
})(window, document, window.Joomla, window.Joomla.submitform);
