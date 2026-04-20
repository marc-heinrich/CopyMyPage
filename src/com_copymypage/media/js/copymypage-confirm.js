/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window) {
    'use strict';

    const modalNs = window.CopyMyPageModal;
    modalNs.adapters = modalNs.adapters || {};
    modalNs.utils = modalNs.utils || {};

    const getText = modalNs.utils.getText || ((key, fallback) => fallback || key);
    const toPlainText = modalNs.utils.toPlainText || ((value) => String(value || ''));

    const normalizeArgs = (message, title, options = {}) => {
        if (message && typeof message === 'object' && !Array.isArray(message)) {
            return Object.assign({}, message);
        }

        return Object.assign({}, options, {
            message,
            title
        });
    };

    const confirmAdapter = (message, title, options = {}) => {
        const settings = normalizeArgs(message, title, options);
        const dialogAdapter = modalNs.adapters.dialog;

        if (typeof dialogAdapter !== 'function') {
            return Promise.resolve(window.confirm(toPlainText(settings.message)));
        }

        return dialogAdapter({
            title: settings.title || getText('JNOTICE', 'Confirm'),
            body: settings.body !== undefined ? settings.body : settings.message,
            isHtml: settings.isHtml === true,
            closable: settings.closable === true,
            cancelValue: false,
            className: ['cmp-uikit-dialog--confirm', settings.className || ''].join(' ').trim(),
            stack: settings.stack !== false,
            buttons: [{
                label: settings.cancelLabel || getText('JNO', 'No'),
                className: 'uk-button uk-button-default',
                value: false
            }, {
                label: settings.okLabel || getText('JYES', 'Yes'),
                className: 'uk-button uk-button-primary',
                value: true
            }]
        });
    };

    modalNs.adapters.confirm = confirmAdapter;
})(window);
