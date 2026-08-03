/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

((window, document, Joomla) => {
    'use strict';

    const panelSelector = '[data-cmp-avatar-profile-field]';
    const selectionEvent = 'copymypage:avatar-selected';
    let processingSelection = false;

    const getMediaField = () => document.querySelector(
        panelSelector + ' joomla-field-media'
    );

    const showSelectionError = () => {
        if (!Joomla || typeof Joomla.renderMessages !== 'function') {
            return;
        }

        const message = Joomla.Text && typeof Joomla.Text._ === 'function'
            ? Joomla.Text._('JLIB_APPLICATION_ERROR_SERVER')
            : 'The selected image could not be loaded.';

        Joomla.renderMessages({
            error: [message]
        });
    };

    const openPicker = (button, mediaField) => {
        const drawer = window.CopyMyPageContentDrawer;
        const panel = mediaField.closest(panelSelector);
        const url = mediaField.getAttribute('url') || '';

        if (
            !drawer
            || typeof drawer.open !== 'function'
            || url.trim() === ''
        ) {
            return false;
        }

        drawer.open({
            title: (
                panel instanceof HTMLElement
                    ? panel.dataset.cmpAvatarPickerTitle
                    : ''
            ) || mediaField.getAttribute('modal-title') || button.textContent.trim(),
            transport: 'document',
            trigger: button,
            url
        }).then((opened) => {
            if (!opened && typeof mediaField.show === 'function') {
                mediaField.show();
            }
        }).catch(() => {
            if (typeof mediaField.show === 'function') {
                mediaField.show();
            }
        });

        return true;
    };

    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest(panelSelector + ' .button-select')
            : null;
        const mediaField = button instanceof HTMLButtonElement
            ? button.closest('joomla-field-media')
            : null;

        if (
            !(button instanceof HTMLButtonElement)
            || !(mediaField instanceof HTMLElement)
            || !openPicker(button, mediaField)
        ) {
            return;
        }

        // Joomla's Media field normally opens its own dialog at the target.
        // The profile workflow delegates this exact button to CopyMyPage's Drawer.
        event.preventDefault();
        event.stopImmediatePropagation();
    }, true);

    document.addEventListener(selectionEvent, async (event) => {
        if (
            processingSelection
            || !('detail' in event)
            || !event.detail
            || typeof event.detail !== 'object'
            || event.detail.type !== 'file'
            || typeof event.detail.path !== 'string'
            || event.detail.path.trim() === ''
        ) {
            return;
        }

        const mediaField = getMediaField();
        const input = mediaField instanceof HTMLElement
            ? mediaField.querySelector('.field-media-input')
            : null;

        if (
            !(mediaField instanceof HTMLElement)
            || !(input instanceof HTMLInputElement)
            || !Joomla
            || typeof Joomla.getMedia !== 'function'
        ) {
            showSelectionError();
            return;
        }

        processingSelection = true;
        Joomla.selectedMediaFile = event.detail;

        try {
            await Joomla.getMedia(event.detail, input, mediaField);

            if (
                window.CopyMyPageContentDrawer
                && typeof window.CopyMyPageContentDrawer.close === 'function'
            ) {
                window.CopyMyPageContentDrawer.close();
            }
        } catch (error) {
            showSelectionError();
        } finally {
            Joomla.selectedMediaFile = {};
            processingSelection = false;
        }
    });
})(window, document, window.Joomla);
