/** Gate native Joomla registration on explicit privacy consent. */
(function (window, document, Joomla) {
    'use strict';

    const initialisedForms = new WeakSet();
    const initialise = () => {
        const form = document.getElementById('member-registration');

        if (!(form instanceof HTMLFormElement) || initialisedForms.has(form)) {
            return;
        }

        const controls = Array.from(form.querySelectorAll('[name="jform[privacyconsent][privacy]"]'));
        const button = form.querySelector('button[type="submit"]');

        if (controls.length === 0 || !(button instanceof HTMLButtonElement)) {
            return;
        }

        initialisedForms.add(form);

        const sync = () => {
            const agreed = controls.some((control) => (
                control instanceof HTMLInputElement && control.type === 'radio'
                && !control.disabled && control.checked && control.value === '1'
            ));

            controls.forEach((control) => control.setCustomValidity(''));

            if (!agreed) {
                const control = controls.find((item) => !item.disabled);

                if (control) {
                    control.setCustomValidity(Joomla.Text._('PLG_SYSTEM_PRIVACYCONSENT_FIELD_ERROR'));
                }
            }

            button.disabled = !agreed || Array.from(form.elements).some((control) => (
                control.willValidate && !control.validity.valid
            ));
            button.setAttribute('aria-disabled', button.disabled ? 'true' : 'false');

            return agreed;
        };

        form.addEventListener('input', sync);
        form.addEventListener('change', sync);
        form.addEventListener('reset', () => window.requestAnimationFrame(sync));
        form.addEventListener('submit', (event) => {
            if (!sync()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
        window.addEventListener('pageshow', sync);
        sync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }

    document.addEventListener('joomla:updated', initialise);
})(window, document, window.Joomla);
