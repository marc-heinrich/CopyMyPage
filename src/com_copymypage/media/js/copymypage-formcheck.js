/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

(function (window, document, Joomla, submitForm) {
    'use strict';

    const buttonDataSelector = 'data-submit-task';
    const defaultFormId = 'cmp-contact-form';
    const messageContainerId = 'system-message-container';

    const getText = (key, fallback) => {
        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    };

    const registerSystemMessageTranslations = () => {
        if (
            !window.CopyMyPageDialog
            || typeof window.CopyMyPageDialog.setSystemMessageConfig !== 'function'
        ) {
            return;
        }

        window.CopyMyPageDialog.setSystemMessageConfig({
            messageTranslations: {
                'Could not instantiate mail function.': 'MOD_COPYMYPAGE_CONTACT_ERROR_MAIL_INSTANTIATE'
            }
        });
    };

    registerSystemMessageTranslations();

    const resolveForm = (button) => {
        const customSelector = button.getAttribute('data-submit-form');

        if (customSelector) {
            return document.querySelector(customSelector) || document.getElementById(customSelector.replace(/^#/, ''));
        }

        return button.closest('form') || document.getElementById(defaultFormId);
    };

    const ensureMessageContainer = () => {
        let container = document.getElementById(messageContainerId);

        if (container) {
            return container;
        }

        container = document.createElement('div');
        container.id = messageContainerId;
        container.setAttribute('aria-live', 'polite');

        const component = document.getElementById('component');

        if (component && component.parentNode) {
            component.parentNode.insertBefore(container, component);
        } else {
            document.body.appendChild(container);
        }

        if (
            window.CopyMyPageDialog
            && typeof window.CopyMyPageDialog.initSystemMessages === 'function'
        ) {
            window.CopyMyPageDialog.initSystemMessages();
        }

        return container;
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
            return {
                valid: typeof form.checkValidity !== 'function' || form.checkValidity(),
                handled: false
            };
        }

        ensureMessageContainer();

        return {
            valid: document.formvalidator.isValid(form),
            handled: true
        };
    };

    const submitTask = async (task, button) => {
        const form = resolveForm(button);

        if (!form || !task) {
            return;
        }

        const validation = validateForm(form);

        if (!validation.valid) {
            if (!validation.handled) {
                await showInvalidFormMessage();
            }

            return;
        }

        if (shouldConfirm(button, form)) {
            const approved = await confirmSubmit(button);

            if (!approved) {
                return;
            }
        }

        if (typeof submitForm === 'function') {
            submitForm(task, form);
            return;
        }

        const taskInput = form.querySelector('input[name="task"]');

        if (taskInput) {
            taskInput.value = task;
        }

        form.submit();
    };

    document.addEventListener('DOMContentLoaded', () => {
        const buttons = Array.from(document.querySelectorAll(`[${buttonDataSelector}]`));

        if (buttons.length) {
            ensureMessageContainer();
        }

        buttons.forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();
                const task = button.getAttribute(buttonDataSelector);
                await submitTask(task, button);
            });
        });
    });
})(window, document, window.Joomla, window.Joomla.submitform);
