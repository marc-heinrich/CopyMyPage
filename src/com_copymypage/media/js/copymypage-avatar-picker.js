/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

((window, document) => {
    'use strict';

    const selector = '[data-cmp-avatar-media]';
    const profileFieldSelector = '[data-cmp-avatar-profile-field]';

    const getButtons = () => Array.from(document.querySelectorAll(selector));

    const select = (button) => {
        if (!(button instanceof HTMLButtonElement) || window.parent === window) {
            return;
        }

        getButtons().forEach((candidate) => {
            const selected = candidate === button;

            candidate.classList.toggle('is-selected', selected);
            candidate.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });

        const parentDocument = window.parent.document;
        const eventName = parentDocument.querySelector(profileFieldSelector)
            ? 'copymypage:avatar-selected'
            : 'onMediaFileSelected';

        parentDocument.dispatchEvent(new CustomEvent(eventName, {
            bubbles: true,
            cancelable: false,
            detail: {
                extension: button.dataset.cmpAvatarExtension || '',
                fileType: button.dataset.cmpAvatarMime || '',
                height: Number.parseInt(button.dataset.cmpAvatarHeight || '0', 10) || 0,
                name: button.dataset.cmpAvatarName || '',
                path: button.dataset.cmpAvatarPath || '',
                thumb: false,
                type: 'file',
                width: Number.parseInt(button.dataset.cmpAvatarWidth || '0', 10) || 0
            }
        }));
    };

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest(selector)
            : null;

        if (button instanceof HTMLButtonElement) {
            select(button);
        }
    });
})(window, document);
