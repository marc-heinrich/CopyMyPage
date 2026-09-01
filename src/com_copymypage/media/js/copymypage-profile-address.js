/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

(function (window, document) {
    'use strict';

    const formSelector = '.cmp-profile-address-form[data-regions-url]';
    const initialisedForms = new WeakSet();
    const pendingRequests = new WeakMap();

    const replaceWithMessage = (select, message) => {
        const option = document.createElement('option');

        option.value = '';
        option.textContent = message;
        select.replaceChildren(option);
        select.disabled = true;
    };

    const populateRegions = (select, regions, placeholder) => {
        const fragment = document.createDocumentFragment();
        const prompt = document.createElement('option');

        prompt.value = '';
        prompt.textContent = placeholder;
        fragment.appendChild(prompt);

        regions.forEach((region) => {
            if (
                !region
                || typeof region.value !== 'string'
                || typeof region.text !== 'string'
                || region.value === ''
            ) {
                return;
            }

            const option = document.createElement('option');

            option.value = region.value;
            option.textContent = region.text;
            fragment.appendChild(option);
        });

        select.replaceChildren(fragment);
        select.disabled = select.options.length === 1;
    };

    const loadRegions = async (form, country, region) => {
        const countryCode = country.value.trim().toUpperCase();
        const previousRequest = pendingRequests.get(form);

        if (previousRequest) {
            previousRequest.abort();
        }

        if (countryCode === '') {
            replaceWithMessage(region, form.dataset.regionPlaceholder || '');
            return;
        }

        const controller = new AbortController();
        const endpoint = new URL(form.dataset.regionsUrl, document.baseURI);

        pendingRequests.set(form, controller);
        endpoint.searchParams.set('country', countryCode);
        region.disabled = true;

        try {
            const response = await window.fetch(endpoint.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json'
                },
                signal: controller.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();

            if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                throw new Error(payload && payload.message ? payload.message : 'Invalid response');
            }

            if (country.value.trim().toUpperCase() !== countryCode) {
                return;
            }

            if (payload.data.length === 0) {
                replaceWithMessage(region, form.dataset.regionEmpty || '');
                return;
            }

            populateRegions(
                region,
                payload.data,
                form.dataset.regionPlaceholder || ''
            );
        } catch (error) {
            if (error && error.name === 'AbortError') {
                return;
            }

            replaceWithMessage(region, form.dataset.regionError || '');
        } finally {
            if (pendingRequests.get(form) === controller) {
                pendingRequests.delete(form);
            }
        }
    };

    const initialiseForm = (form) => {
        if (initialisedForms.has(form)) {
            return;
        }

        const country = form.querySelector('#jform_country_code');
        const region = form.querySelector('#jform_region_code');

        if (!country || !region) {
            return;
        }

        initialisedForms.add(form);
        country.addEventListener('change', () => loadRegions(form, country, region));

        if (country.value === '') {
            region.disabled = true;
        } else if (region.options.length <= 1) {
            loadRegions(form, country, region);
        }
    };

    const initialise = (root) => {
        if (root instanceof Element && root.matches(formSelector)) {
            initialiseForm(root);
        }

        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;

        scope.querySelectorAll(formSelector).forEach(initialiseForm);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initialise(document), { once: true });
    } else {
        initialise(document);
    }

    document.addEventListener('joomla:updated', (event) => initialise(event.target));
}(window, document));
