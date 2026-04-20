/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

window.CopyMyPageModal = window.CopyMyPageModal || {};

(function (window, document, Joomla, UIkit) {
    'use strict';

    const modalNs = window.CopyMyPageModal;
    modalNs.adapters = modalNs.adapters || {};
    modalNs.utils = modalNs.utils || {};

    const getText = modalNs.utils.getText || ((key, fallback) => {
        if (Joomla && Joomla.Text && typeof Joomla.Text._ === 'function') {
            return Joomla.Text._(key, fallback) || fallback || key;
        }

        return fallback || key;
    });

    const escapeHtml = modalNs.utils.escapeHtml || ((value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;'));

    const toPlainText = modalNs.utils.toPlainText || ((value) => {
        const tmp = document.createElement('div');
        tmp.innerHTML = String(value || '');
        return (tmp.textContent || tmp.innerText || '').trim();
    });

    const toBodyHtml = modalNs.utils.toBodyHtml || ((content, isHtml) => {
        if (isHtml === true) {
            return String(content || '');
        }

        return `<p>${escapeHtml(String(content || ''))}</p>`;
    });

    const open = (options = {}) => {
        const settings = Object.assign({
            title: '',
            body: '',
            isHtml: false,
            closable: true,
            cancelValue: false,
            className: '',
            buttons: [],
            stack: true
        }, options);

        if (!UIkit || typeof UIkit.modal !== 'function') {
            return Promise.resolve(settings.cancelValue);
        }

        return new Promise((resolve) => {
            let result = settings.cancelValue;
            let modalInstance = null;
            let resolved = false;

            const done = () => {
                if (resolved) {
                    return;
                }

                resolved = true;
                modalElement.remove();
                resolve(result);
            };

            const modalElement = document.createElement('div');
            modalElement.className = `uk-flex-top cmp-uikit-dialog ${settings.className}`.trim();
            modalElement.setAttribute(
                'uk-modal',
                `esc-close: ${settings.closable}; bg-close: ${settings.closable}; stack: ${settings.stack !== false}`
            );

            const panel = document.createElement('div');
            panel.className = 'uk-modal-dialog uk-margin-auto-vertical cmp-uikit-dialog__panel';
            modalElement.appendChild(panel);

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'uk-modal-close-default cmp-uikit-dialog__close';
            closeButton.setAttribute('uk-close', '');
            if (!settings.closable) {
                closeButton.classList.add('uk-hidden');
            }
            panel.appendChild(closeButton);

            const header = document.createElement('header');
            header.className = 'uk-modal-header cmp-uikit-dialog__header';

            const title = document.createElement('h2');
            title.className = 'uk-modal-title cmp-uikit-dialog__title';
            title.textContent = settings.title ? String(settings.title) : '';
            header.appendChild(title);
            if (!settings.title) {
                header.classList.add('uk-hidden');
            }
            panel.appendChild(header);

            const body = document.createElement('div');
            body.className = 'uk-modal-body cmp-uikit-dialog__body';
            body.innerHTML = toBodyHtml(settings.body, settings.isHtml);
            panel.appendChild(body);

            const footer = document.createElement('footer');
            footer.className = 'uk-modal-footer uk-text-right cmp-uikit-dialog__footer';
            if (!settings.buttons.length) {
                footer.classList.add('uk-hidden');
            }
            panel.appendChild(footer);

            settings.buttons.forEach((buttonCfg) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = buttonCfg.className || 'uk-button uk-button-default';
                button.textContent = buttonCfg.label || '';
                button.addEventListener('click', () => {
                    result = (typeof buttonCfg.value === 'function')
                        ? buttonCfg.value({ modalElement, panel, body, footer })
                        : buttonCfg.value;

                    if (typeof buttonCfg.onClick === 'function') {
                        const shouldClose = buttonCfg.onClick(result, { modalElement, panel, body, footer });
                        if (shouldClose === false) {
                            return;
                        }
                    }

                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });

                footer.appendChild(button);
            });

            modalElement.addEventListener('hidden', done, { once: true });
            modalElement.addEventListener('hidden.uk.modal', done, { once: true });

            document.body.appendChild(modalElement);
            modalInstance = UIkit.modal(modalElement);
            modalInstance.show();
        });
    };

    const dialog = (options = {}) => {
        const settings = Object.assign({
            title: '',
            body: '',
            isHtml: false,
            closable: true,
            cancelValue: false,
            className: '',
            buttons: [],
            stack: true
        }, options);

        return open(settings);
    };

    modalNs.utils.getText = getText;
    modalNs.utils.escapeHtml = escapeHtml;
    modalNs.utils.toPlainText = toPlainText;
    modalNs.utils.toBodyHtml = toBodyHtml;
    modalNs.utils.open = open;

    modalNs.adapters.dialog = dialog;
})(window, document, window.Joomla, window.UIkit);
