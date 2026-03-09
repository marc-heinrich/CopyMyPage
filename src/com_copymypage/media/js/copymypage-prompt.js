/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window) {
    'use strict';

    const modalNs = window.CopyMyPageModal;
    modalNs.adapters = modalNs.adapters || {};
    modalNs.utils = modalNs.utils || {};

    const getText = modalNs.utils.getText || ((key, fallback) => fallback || key);
    const escapeHtml = modalNs.utils.escapeHtml || ((value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;'));

    const promptAdapter = (message, value = '', title = '', options = {}) => {
        if (message && typeof message === 'object' && !Array.isArray(message)) {
            options = Object.assign({}, message);
        } else {
            options = Object.assign({}, options, {
                message,
                value,
                title
            });
        }

        const dialogAdapter = modalNs.adapters.dialog;

        if (typeof dialogAdapter !== 'function') {
            return Promise.resolve(window.prompt(String(options.message || ''), String(options.value || '')));
        }

        const inputId = `cmp-uikit-dialog-prompt-${Date.now()}`;
        const bodyHtml = [
            `<label class="uk-form-label" for="${inputId}">${escapeHtml(String(options.message || ''))}</label>`,
            '<div class="uk-margin-small-top">',
            `<input id="${inputId}" class="uk-input" type="text" value="${escapeHtml(String(options.value || ''))}">`,
            '</div>'
        ].join('');

        return dialogAdapter({
            title: options.title || getText('JNOTICE', 'Input'),
            body: bodyHtml,
            isHtml: true,
            closable: options.closable === true,
            cancelValue: null,
            className: ['cmp-uikit-dialog--prompt', options.className || ''].join(' ').trim(),
            stack: options.stack !== false,
            buttons: [{
                label: options.cancelLabel || getText('JNO', 'Cancel'),
                className: 'uk-button uk-button-default',
                value: null
            }, {
                label: options.okLabel || getText('JOK', 'OK'),
                className: 'uk-button uk-button-primary',
                value: ({ body }) => {
                    const input = body.querySelector(`#${inputId}`);
                    return input ? input.value : '';
                },
                onClick: (result) => {
                    if (options.required === true && String(result || '').trim() === '') {
                        return false;
                    }

                    return true;
                }
            }]
        });
    };

    modalNs.adapters.prompt = promptAdapter;
})(window);
