/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

window.CopyMyPageContentModal = window.CopyMyPageContentModal || {};

(function (window, document) {
    'use strict';

    const api = window.CopyMyPageContentModal;

    const getDrawer = () => window.CopyMyPageContentDrawer;

    api.open = (options = {}) => {
        const drawer = getDrawer();

        if (!drawer || typeof drawer.open !== 'function') {
            return Promise.resolve(false);
        }

        return drawer.open(Object.assign({
            transport: 'fragment'
        }, options));
    };

    api.initConsentLinks = (root = document) => {
        const drawer = getDrawer();

        if (!drawer || typeof drawer.init !== 'function') {
            return false;
        }

        return drawer.init(root);
    };

    api.initConsentLinks();
})(window, document);
