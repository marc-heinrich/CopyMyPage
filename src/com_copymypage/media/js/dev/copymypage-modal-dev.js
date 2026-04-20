/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

(function (window, document) {
    'use strict';

    if (window.__cmpModalDevHarnessInit === true) {
        return;
    }

    window.__cmpModalDevHarnessInit = true;

    const dialogApi = window.CopyMyPageDialog;

    if (!dialogApi) {
        console.warn('CopyMyPageDialog is not available. Dev harness not initialized.');
        return;
    }

    const helperId = 'cmp-modal-dev-helper';
    const styleId = `${helperId}-style`;

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const injectSystemMessages = (messages) => {
        const container = document.getElementById('system-message-container');

        if (!container) {
            dialogApi.alert('Container #system-message-container wurde nicht gefunden.');
            return;
        }

        messages.forEach((message) => {
            const alertElement = document.createElement('joomla-alert');
            const type = String(message.type || 'info');
            const title = message.title ? String(message.title) : '';

            alertElement.setAttribute('type', type);
            alertElement.setAttribute('dismiss', 'true');

            const headingHtml = title
                ? `<div class="alert-heading"><span class="visually-hidden">${escapeHtml(title)}</span></div>`
                : '';

            alertElement.innerHTML = [
                '<div class="alert-wrapper">',
                headingHtml,
                `<div class="alert-message">${String(message.html || '')}</div>`,
                '</div>'
            ].join('');

            container.appendChild(alertElement);
        });
    };

    const actions = {
        alert: () => dialogApi.alert('Kurze Info: Das ist ein einfacher Alert ohne Header.'),
        confirm: () => dialogApi.confirm('Soll der Test fortgesetzt werden?', 'Confirm Test')
            .then((approved) => dialogApi.alert(approved ? 'Ergebnis: bestaetigt.' : 'Ergebnis: abgebrochen.')),
        prompt: () => dialogApi.prompt('Bitte einen Testwert eingeben:', '', 'Prompt Test')
            .then((value) => {
                if (value === null) {
                    return dialogApi.alert('Prompt wurde abgebrochen.');
                }

                return dialogApi.alert(`Prompt-Wert: ${escapeHtml(value)}`, '', { isHtml: true });
            }),
        dialog: () => dialogApi.dialog({
            title: 'Dialog Test (Rich)',
            isHtml: true,
            body: [
                '<p>Dies ist ein frei aufgebauter Dialog.</p>',
                '<ul>',
                '<li>Mehrzeiliger Inhalt</li>',
                '<li>Mit Liste und eigener Struktur</li>',
                '</ul>'
            ].join(''),
            closable: true,
            buttons: [{
                label: 'Schliessen',
                className: 'uk-button uk-button-primary',
                value: true
            }]
        }),
        systemSimple: () => injectSystemMessages([
            {
                type: 'message',
                html: '<p>Datensatz wurde erfolgreich gespeichert.</p>'
            },
            {
                type: 'warning',
                html: '<p>Bitte ueberpruefe noch die optionalen Felder.</p>'
            }
        ]),
        systemComplex: () => injectSystemMessages([
            {
                type: 'notice',
                title: 'Hinweis',
                html: [
                    '<header><h4>Komplexe Struktur</h4></header>',
                    '<p>Diese Meldung enthaelt Header und Liste und sollte im Dialog landen.</p>',
                    '<ul><li>Schritt 1</li><li>Schritt 2</li></ul>'
                ].join('')
            }
        ])
    };

    const buildHelperUi = () => {
        if (document.getElementById(styleId)) {
            return;
        }

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            #${helperId} {
                position: fixed;
                right: 1rem;
                bottom: 1rem;
                z-index: 1200;
                width: min(90vw, 19rem);
                border: 1px solid rgba(0, 0, 0, 0.15);
                border-radius: 10px;
                background: rgba(255, 255, 255, 0.96);
                box-shadow: 0 12px 30px rgba(0, 0, 0, 0.22);
                backdrop-filter: blur(4px);
            }
            #${helperId} .cmp-dev-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0.55rem 0.7rem;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
                font: 600 0.82rem/1.2 "Segoe UI", Arial, sans-serif;
            }
            #${helperId} .cmp-dev-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
                padding: 0.7rem;
            }
            #${helperId} button {
                font: 500 0.78rem/1.1 "Segoe UI", Arial, sans-serif;
                padding: 0.45rem 0.5rem;
                border-radius: 6px;
                border: 1px solid rgba(0, 0, 0, 0.16);
                background: #fff;
                cursor: pointer;
            }
            #${helperId} button:hover {
                background: #f6f7fb;
            }
            #${helperId}[data-collapsed="1"] .cmp-dev-grid {
                display: none;
            }
        `;

        document.head.appendChild(style);
    };

    const mountHelperUi = () => {
        if (document.getElementById(helperId)) {
            return;
        }

        buildHelperUi();

        const panel = document.createElement('aside');
        panel.id = helperId;
        panel.dataset.collapsed = '0';

        panel.innerHTML = [
            '<div class="cmp-dev-head">',
            '<span>CMP Modal Dev</span>',
            '<button type="button" data-action="toggle">-</button>',
            '</div>',
            '<div class="cmp-dev-grid">',
            '<button type="button" data-action="alert">Alert</button>',
            '<button type="button" data-action="confirm">Confirm</button>',
            '<button type="button" data-action="prompt">Prompt</button>',
            '<button type="button" data-action="dialog">Dialog</button>',
            '<button type="button" data-action="systemSimple">System kurz</button>',
            '<button type="button" data-action="systemComplex">System komplex</button>',
            '</div>'
        ].join('');

        panel.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-action]');

            if (!button || !panel.contains(button)) {
                return;
            }

            const action = button.getAttribute('data-action');

            if (action === 'toggle') {
                panel.dataset.collapsed = panel.dataset.collapsed === '1' ? '0' : '1';
                button.textContent = panel.dataset.collapsed === '1' ? '+' : '-';
                return;
            }

            if (typeof actions[action] === 'function') {
                actions[action]();
            }
        });

        document.body.appendChild(panel);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountHelperUi);
    } else {
        mountHelperUi();
    }
})(window, document);

