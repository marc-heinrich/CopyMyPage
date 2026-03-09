/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

window.CopyMyPageDialog = window.CopyMyPageDialog || {};
window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window, document, Joomla) {
    'use strict';

    const api = window.CopyMyPageDialog;
    const modalNs = window.CopyMyPageModal;
    modalNs.adapters = modalNs.adapters || {};
    modalNs.config = modalNs.config || {};

    const defaultConfig = {
        containerId: 'system-message-container',
        complexRenderer: 'dialog',
        defaultRenderer: 'alert',
        mixedRenderer: 'dialog',
        typeRenderer: {
            message: 'alert',
            success: 'alert',
            notice: 'alert',
            info: 'alert',
            warning: 'alert',
            error: 'alert',
            danger: 'alert',
            critical: 'alert',
            alert: 'alert',
            emergency: 'alert'
        },
        forceDialogTags: ['header', 'section', 'article', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'form', 'fieldset', 'pre', 'blockquote', 'figure'],
        alertAllowedTags: ['p', 'span', 'strong', 'b', 'em', 'i', 'u', 'small', 'a', 'code', 'mark', 'br'],
        alertMaxTextLength: 240,
        alertMaxLineBreaks: 1,
        alertMaxRootElements: 1,
        stackModals: true,
        dialogTitleKey: 'MESSAGE',
        dialogTitleFallback: 'Message'
    };

    const hasOwn = Object.hasOwn || ((object, key) => Object.prototype.hasOwnProperty.call(object, key));

    const normalizeTagList = (values, fallback = []) => {
        const source = Array.isArray(values) ? values : fallback;

        return source
            .map((item) => String(item || '').toLowerCase().trim())
            .filter(Boolean);
    };

    const toSafeNumber = (value, fallback) => {
        const number = Number.parseInt(value, 10);

        if (!Number.isFinite(number)) {
            return fallback;
        }

        return number;
    };

    const mergeSystemMessageConfig = (baseConfig, overrideConfig = {}) => {
        const merged = Object.assign({}, baseConfig, overrideConfig || {});

        merged.typeRenderer = Object.assign(
            {},
            baseConfig.typeRenderer || {},
            overrideConfig && overrideConfig.typeRenderer ? overrideConfig.typeRenderer : {}
        );

        merged.forceDialogTags = normalizeTagList(
            overrideConfig && hasOwn(overrideConfig, 'forceDialogTags')
                ? overrideConfig.forceDialogTags
                : undefined,
            baseConfig.forceDialogTags || []
        );

        merged.alertAllowedTags = normalizeTagList(
            overrideConfig && hasOwn(overrideConfig, 'alertAllowedTags')
                ? overrideConfig.alertAllowedTags
                : undefined,
            baseConfig.alertAllowedTags || []
        );

        merged.alertMaxTextLength = Math.max(
            0,
            toSafeNumber(
                overrideConfig && hasOwn(overrideConfig, 'alertMaxTextLength')
                    ? overrideConfig.alertMaxTextLength
                    : undefined,
                baseConfig.alertMaxTextLength
            )
        );

        merged.alertMaxLineBreaks = Math.max(
            0,
            toSafeNumber(
                overrideConfig && hasOwn(overrideConfig, 'alertMaxLineBreaks')
                    ? overrideConfig.alertMaxLineBreaks
                    : undefined,
                baseConfig.alertMaxLineBreaks
            )
        );

        merged.alertMaxRootElements = Math.max(
            1,
            toSafeNumber(
                overrideConfig && hasOwn(overrideConfig, 'alertMaxRootElements')
                    ? overrideConfig.alertMaxRootElements
                    : undefined,
                baseConfig.alertMaxRootElements
            )
        );

        return merged;
    };

    modalNs.config.systemMessages = mergeSystemMessageConfig(defaultConfig, modalNs.config.systemMessages || {});

    let messageQueue = [];
    let systemDialogOpen = false;

    const getText = (key, fallback) => {
        if (modalNs.utils && typeof modalNs.utils.getText === 'function') {
            return modalNs.utils.getText(key, fallback);
        }

        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    };

    const escapeHtml = (value) => {
        if (modalNs.utils && typeof modalNs.utils.escapeHtml === 'function') {
            return modalNs.utils.escapeHtml(value);
        }

        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const toPlainText = (value) => {
        if (modalNs.utils && typeof modalNs.utils.toPlainText === 'function') {
            return modalNs.utils.toPlainText(value);
        }

        const tmp = document.createElement('div');
        tmp.innerHTML = String(value || '');
        return (tmp.textContent || tmp.innerText || '').trim();
    };

    const normalizeType = (type) => {
        const key = String(type || '').toLowerCase();
        const map = {
            message: 'success',
            success: 'success',
            notice: 'info',
            info: 'info',
            warning: 'warning',
            error: 'danger',
            danger: 'danger',
            critical: 'danger',
            alert: 'danger',
            emergency: 'danger'
        };

        return map[key] || 'info';
    };

    const getTypeTitle = (type) => {
        switch (type) {
            case 'danger':
                return getText('ERROR', 'Error');
            case 'warning':
                return getText('WARNING', 'Warning');
            case 'success':
                return getText('MESSAGE', 'Message');
            default:
                return getText('NOTICE', 'Notice');
        }
    };

    const analyzeMessageMarkup = (html, config) => {
        const source = String(html || '');
        const plainText = toPlainText(source);
        const textLength = plainText.length;

        if (!source || !plainText) {
            return {
                preferDialog: false,
                reason: 'empty',
                textLength
            };
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = source;

        const tags = Array.from(wrapper.querySelectorAll('*'))
            .map((element) => element.tagName.toLowerCase());

        if (!tags.length) {
            if (textLength > config.alertMaxTextLength) {
                return {
                    preferDialog: true,
                    reason: 'text-length',
                    textLength
                };
            }

            return {
                preferDialog: false,
                reason: 'plain-short-text',
                textLength
            };
        }

        const tagSet = new Set(tags);
        const hasForcedTag = config.forceDialogTags.some((tag) => tagSet.has(tag));

        if (hasForcedTag) {
            return {
                preferDialog: true,
                reason: 'force-dialog-tag',
                textLength
            };
        }

        const hasNonAlertTag = tags.some((tag) => !config.alertAllowedTags.includes(tag));

        if (hasNonAlertTag) {
            return {
                preferDialog: true,
                reason: 'non-alert-tag',
                textLength
            };
        }

        const rootElementCount = wrapper.children.length;

        if (rootElementCount > config.alertMaxRootElements) {
            return {
                preferDialog: true,
                reason: 'root-element-count',
                textLength
            };
        }

        const lineBreakCount = wrapper.querySelectorAll('br').length;

        if (lineBreakCount > config.alertMaxLineBreaks) {
            return {
                preferDialog: true,
                reason: 'line-break-count',
                textLength
            };
        }

        if (textLength > config.alertMaxTextLength) {
            return {
                preferDialog: true,
                reason: 'text-length',
                textLength
            };
        }

        return {
            preferDialog: false,
            reason: 'alert-safe-markup',
            textLength
        };
    };

    const renderCompactBatchBody = (entries) => entries.map((entry) => {
        const messageHtml = entry.messages
            .map((message) => `<div class="cmp-uikit-dialog__message-text">${message}</div>`)
            .join('');

        return [
            `<article class="cmp-uikit-dialog__message cmp-uikit-dialog__message--${entry.type}">`,
            messageHtml,
            '</article>'
        ].join('');
    }).join('');

    const renderRichBatchBody = (entries) => entries.map((entry) => {
        const messageHtml = entry.messages
            .map((message) => `<div class="cmp-uikit-dialog__message-text">${message}</div>`)
            .join('');

        return [
            `<article class="cmp-uikit-dialog__message cmp-uikit-dialog__message--${entry.type}">`,
            `<h3 class="cmp-uikit-dialog__message-title">${escapeHtml(entry.title)}</h3>`,
            messageHtml,
            '</article>'
        ].join('');
    }).join('');

    const collectSystemMessages = (container, config) => {
        const alertElements = Array.from(container.querySelectorAll('joomla-alert'));

        if (!alertElements.length) {
            return [];
        }

        const entries = alertElements.map((alertElement) => {
            const rawType = String(alertElement.getAttribute('type') || '').toLowerCase();
            const type = normalizeType(rawType);
            const title = alertElement.querySelector('.alert-heading .visually-hidden')?.textContent?.trim() || getTypeTitle(type);

            let messages = Array.from(alertElement.querySelectorAll('.alert-message'))
                .map((messageElement) => messageElement.innerHTML.trim())
                .filter(Boolean);

            if (!messages.length) {
                const fallbackMessage = alertElement.querySelector('.alert-wrapper')?.innerHTML?.trim() || '';

                if (fallbackMessage) {
                    messages = [fallbackMessage];
                }
            }

            const analyses = messages.map((message) => analyzeMessageMarkup(message, config));
            const prefersDialog = analyses.some((analysis) => analysis.preferDialog);

            return {
                rawType,
                type,
                title,
                messages,
                analyses,
                prefersDialog
            };
        }).filter((entry) => entry.messages.length > 0);

        alertElements.forEach((alertElement) => alertElement.remove());

        return entries;
    };

    const resolveEntryRenderer = (entry, config) => {
        if (entry.prefersDialog) {
            return config.complexRenderer;
        }

        return config.typeRenderer[entry.rawType]
            || config.typeRenderer[entry.type]
            || config.defaultRenderer;
    };

    const resolveRenderer = (entries, config) => {
        const rendererSet = new Set(entries.map((entry) => resolveEntryRenderer(entry, config)));

        if (rendererSet.has(config.complexRenderer)) {
            return config.complexRenderer;
        }

        if (rendererSet.size === 1) {
            return rendererSet.values().next().value;
        }

        return config.mixedRenderer || config.complexRenderer;
    };

    const presentSystemMessages = (entries) => {
        const config = modalNs.config.systemMessages;
        const renderer = resolveRenderer(entries, config);

        if (renderer === 'dialog') {
            return api.dialog({
                title: getText(config.dialogTitleKey, config.dialogTitleFallback),
                body: renderRichBatchBody(entries),
                isHtml: true,
                closable: true,
                className: 'cmp-uikit-dialog--system cmp-uikit-dialog--system-rich',
                stack: config.stackModals !== false,
                buttons: [{
                    label: getText('JOK', 'OK'),
                    className: 'uk-button uk-button-primary',
                    value: true
                }]
            });
        }

        return api.alert(renderCompactBatchBody(entries), '', {
            isHtml: true,
            className: 'cmp-uikit-dialog--system cmp-uikit-dialog--system-compact',
            stack: config.stackModals !== false,
            showTitle: false
        });
    };

    const renderSystemMessageQueue = () => {
        if (systemDialogOpen || !messageQueue.length) {
            return;
        }

        const currentBatch = messageQueue.splice(0, messageQueue.length);
        systemDialogOpen = true;

        presentSystemMessages(currentBatch).finally(() => {
            systemDialogOpen = false;
            renderSystemMessageQueue();
        });
    };

    const processSystemMessages = () => {
        const config = modalNs.config.systemMessages;
        const container = document.getElementById(config.containerId);

        if (!container) {
            return;
        }

        container.classList.add('cmp-message-enhanced');

        const entries = collectSystemMessages(container, config);

        if (!entries.length) {
            return;
        }

        messageQueue = messageQueue.concat(entries);
        renderSystemMessageQueue();
    };

    const initSystemMessageObserver = () => {
        const config = modalNs.config.systemMessages;
        const container = document.getElementById(config.containerId);

        if (!container || container.dataset.cmpDialogInit === '1') {
            return;
        }

        container.dataset.cmpDialogInit = '1';
        container.classList.add('cmp-message-enhanced');

        const observer = new MutationObserver(() => {
            processSystemMessages();
        });

        observer.observe(container, {
            childList: true,
            subtree: true
        });

        modalNs.systemMessageObserver = observer;

        processSystemMessages();
    };

    const runAdapter = (adapterName, fallbackValue, args) => {
        const adapter = modalNs.adapters[adapterName];

        if (typeof adapter !== 'function') {
            return Promise.resolve(fallbackValue);
        }

        return adapter(...args);
    };

    const setSystemMessageConfig = (overrideConfig = {}) => {
        modalNs.config.systemMessages = mergeSystemMessageConfig(
            modalNs.config.systemMessages,
            overrideConfig
        );

        return modalNs.config.systemMessages;
    };

    const getSystemMessageConfig = () => mergeSystemMessageConfig({}, modalNs.config.systemMessages);

    api.open = (options = {}) => runAdapter('dialog', options.cancelValue ?? false, [options]);
    api.dialog = (options = {}, title = '', extra = {}) => {
        if (options && typeof options === 'object' && !Array.isArray(options)) {
            return runAdapter('dialog', options.cancelValue ?? false, [options]);
        }

        return runAdapter('dialog', false, [Object.assign({}, extra, {
            body: options,
            title
        })]);
    };

    api.alert = (message, title = '', options = {}) => runAdapter('alert', true, [message, title, options]);
    api.confirm = (message, title = '', options = {}) => runAdapter('confirm', false, [message, title, options]);
    api.prompt = (message, value = '', title = '', options = {}) => runAdapter('prompt', null, [message, value, title, options]);
    api.processSystemMessages = processSystemMessages;
    api.initSystemMessages = initSystemMessageObserver;
    api.setSystemMessageConfig = setSystemMessageConfig;
    api.getSystemMessageConfig = getSystemMessageConfig;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSystemMessageObserver);
    } else {
        initSystemMessageObserver();
    }
})(window, document, window.Joomla);
