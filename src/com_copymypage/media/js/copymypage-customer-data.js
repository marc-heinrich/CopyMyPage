/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

(function (window, document, Joomla) {
    'use strict';

    const initialisedRoots = new WeakSet();

    const getConfig = () => {
        if (!Joomla || typeof Joomla.getOptions !== 'function') {
            return {};
        }

        const options = Joomla.getOptions('copymypage.params', {});

        return options && options.com && options.com.customerData
            ? options.com.customerData
            : {};
    };

    const query = (scope, selector) => {
        if (!scope || typeof scope.querySelector !== 'function' || typeof selector !== 'string') {
            return null;
        }

        try {
            return scope.querySelector(selector);
        } catch (error) {
            return null;
        }
    };

    const queryAll = (scope, selector) => {
        if (!scope || typeof scope.querySelectorAll !== 'function' || typeof selector !== 'string') {
            return [];
        }

        try {
            return Array.from(scope.querySelectorAll(selector));
        } catch (error) {
            return [];
        }
    };

    const initialiseRoot = (root, config) => {
        if (!(root instanceof Element) || initialisedRoots.has(root)) {
            return;
        }

        const selectors = config && typeof config.selectors === 'object'
            ? config.selectors
            : {};
        const toggle = query(root, selectors.accountToggle);
        const fields = query(root, selectors.accountFields);
        const email = query(root, selectors.email);
        const username = query(root, selectors.username);
        const customerForm = query(root, selectors.customerForm);
        const continueButton = query(root, selectors.continueButton);
        const loginMode = query(root, selectors.loginMode);
        const modeSwitcher = query(root, selectors.modeSwitcher);

        initialisedRoots.add(root);
        root.classList.add('cmp-customer-data--enhanced');

        const isCustomerFormValid = () => {
            if (!(customerForm instanceof HTMLFormElement)) {
                return false;
            }

            return Array.from(customerForm.elements).every((control) => {
                if (!control || typeof control.willValidate !== 'boolean' || !control.willValidate) {
                    return true;
                }

                return control.validity && control.validity.valid;
            });
        };

        const isLoginModeActive = () => (
            loginMode instanceof HTMLElement
            && loginMode.classList.contains('uk-active')
        );

        const syncContinueButton = () => {
            if (!(continueButton instanceof HTMLButtonElement)) {
                return;
            }

            const disabled = isLoginModeActive() || !isCustomerFormValid();

            continueButton.disabled = disabled;
            continueButton.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        };

        if (customerForm instanceof HTMLFormElement) {
            customerForm.addEventListener('input', syncContinueButton);
            customerForm.addEventListener('change', syncContinueButton);
        }

        if (loginMode instanceof HTMLElement && typeof MutationObserver === 'function') {
            const modeObserver = new MutationObserver(syncContinueButton);

            modeObserver.observe(loginMode, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        if (modeSwitcher instanceof HTMLElement) {
            modeSwitcher.addEventListener('itemshow', syncContinueButton);
            modeSwitcher.addEventListener('itemshown', syncContinueButton);
        }

        if (email instanceof HTMLInputElement && username instanceof HTMLInputElement) {
            let previousSuggestion = email.value.trim();

            if (username.value.trim() === '') {
                username.value = previousSuggestion;
            }

            email.addEventListener('input', () => {
                const currentUsername = username.value.trim();
                const suggestion = email.value.trim();

                if (currentUsername === '' || currentUsername === previousSuggestion) {
                    username.value = suggestion;
                }

                previousSuggestion = suggestion;
            });
        }

        if (!(toggle instanceof HTMLInputElement) || !(fields instanceof HTMLElement)) {
            syncContinueButton();

            return;
        }

        const syncAccountFields = () => {
            const expanded = toggle.checked;

            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            fields.hidden = !expanded;

            queryAll(fields, 'input, select, textarea, button').forEach((control) => {
                if ('disabled' in control) {
                    control.disabled = !expanded;
                } else if (expanded) {
                    control.removeAttribute('disabled');
                } else {
                    control.setAttribute('disabled', 'disabled');
                }
            });

            queryAll(fields, selectors.accountRequired).forEach((control) => {
                if (expanded) {
                    control.setAttribute('required', 'required');
                } else {
                    control.removeAttribute('required');
                }
            });

            syncContinueButton();
        };

        if (typeof MutationObserver === 'function') {
            const accountFieldsObserver = new MutationObserver(syncAccountFields);

            accountFieldsObserver.observe(fields, {
                childList: true,
                subtree: true
            });
        }

        toggle.addEventListener('change', syncAccountFields);
        syncAccountFields();
    };

    const initialise = (scope = document) => {
        const config = getConfig();

        if (!config || typeof config.rootSelector !== 'string') {
            return;
        }

        if (scope instanceof Element && scope.matches(config.rootSelector)) {
            initialiseRoot(scope, config);
        }

        queryAll(scope, config.rootSelector).forEach((root) => initialiseRoot(root, config));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialise(document), { once: true });
    } else {
        initialise(document);
    }

    document.addEventListener('joomla:updated', (event) => initialise(event.target));
}(window, document, window.Joomla));
