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
    const regionStates = new WeakMap();

    const replaceWithMessage = (select, message) => {
        const option = document.createElement('option');

        option.value = '';
        option.textContent = message;
        select.replaceChildren(option);
        select.disabled = true;
    };

    const populateRegions = (select, regions, placeholder, selectedValue) => {
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
        if (Array.from(select.options).some((option) => option.value === selectedValue)) {
            select.value = selectedValue;
        }
        select.disabled = select.options.length === 1;
    };

    const loadRegions = async (form, country, region) => {
        const countryCode = country.value.trim().toUpperCase();
        const previousRequest = pendingRequests.get(form);
        const state = regionStates.get(form);

        if (!previousRequest) {
            state.values.set(state.countryCode, region.value);
        }

        if (previousRequest) {
            previousRequest.abort();
        }

        state.status.textContent = '';

        if (countryCode === '') {
            pendingRequests.delete(form);
            region.removeAttribute('aria-busy');
            replaceWithMessage(region, form.dataset.regionPlaceholder || '');
            state.countryCode = '';
            return;
        }

        const controller = new AbortController();
        const endpoint = new URL(form.dataset.regionsUrl, document.baseURI);

        pendingRequests.set(form, controller);
        endpoint.searchParams.set('country', countryCode);
        region.setAttribute('aria-busy', 'true');
        state.status.textContent = form.dataset.regionLoading || '';

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
                state.countryCode = countryCode;
                state.status.textContent = form.dataset.regionEmpty || '';
                return;
            }

            populateRegions(
                region,
                payload.data,
                form.dataset.regionPlaceholder || '',
                state.values.get(countryCode) || ''
            );
            state.countryCode = countryCode;
            state.status.textContent = '';
        } catch (error) {
            if ((error && error.name === 'AbortError') || pendingRequests.get(form) !== controller) {
                return;
            }

            // Retain the previous selection on failure; the server validates its country.
            state.status.textContent = form.dataset.regionError || '';
        } finally {
            if (pendingRequests.get(form) === controller) {
                pendingRequests.delete(form);
                region.removeAttribute('aria-busy');
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
        const status = document.createElement('div');

        status.id = `${form.id || 'cmp-address'}-region-status`;
        status.className = 'form-text';
        status.setAttribute('role', 'status');
        region.after(status);
        region.setAttribute('aria-describedby', [region.getAttribute('aria-describedby'), status.id].filter(Boolean).join(' '));
        regionStates.set(form, {
            countryCode: country.value.trim().toUpperCase(),
            values: new Map(),
            status
        });
        form.addEventListener('submit', (event) => {
            if (pendingRequests.has(form) && !event.submitter?.formNoValidate) {
                event.preventDefault();
                status.textContent = form.dataset.regionLoading || '';
            }
        });
        country.addEventListener('change', () => loadRegions(form, country, region));

        if (country.value === '' && region.value === '') {
            region.disabled = true;
        } else if (country.value !== '' && region.options.length <= 1) {
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
