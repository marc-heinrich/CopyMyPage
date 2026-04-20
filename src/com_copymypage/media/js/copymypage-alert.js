/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window, document) {
    'use strict';

    const modalNs = window.CopyMyPageModal;
    modalNs.adapters = modalNs.adapters || {};
    modalNs.utils = modalNs.utils || {};

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

    const alertAdapter = (message, title, options = {}) => {
        const settings = normalizeArgs(message, title, options);
        const dialogAdapter = modalNs.adapters.dialog;

        if (typeof dialogAdapter !== 'function') {
            window.alert(toPlainText(settings.message));
            return Promise.resolve(true);
        }

        return dialogAdapter({
            title: settings.showTitle === true ? (settings.title || '') : '',
            body: settings.body !== undefined ? settings.body : settings.message,
            isHtml: settings.isHtml === true,
            closable: settings.closable !== false,
            cancelValue: true,
            className: ['cmp-uikit-dialog--alert', settings.className || ''].join(' ').trim(),
            stack: settings.stack !== false,
            buttons: []
        }).then(() => true);
    };

    modalNs.adapters.alert = alertAdapter;
})(window, document);
