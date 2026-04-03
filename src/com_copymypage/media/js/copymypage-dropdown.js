/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

window.CopyMyPage = window.CopyMyPage || {};

(function (UIkit, window, document, CopyMyPage) {
    'use strict';

    const BaseFeature = CopyMyPage.BaseFeature;
    const CleanupBag = CopyMyPage.CleanupBag;
    const { constants } = CopyMyPage;

    if (!BaseFeature || !CleanupBag || !constants) {
        return;
    }

    class DesktopUserDropdownFeature extends BaseFeature {
        constructor(host) {
            super(host);
            this._bindings = new CleanupBag();
        }

        destroy() {
            this._teardown();
            super.destroy();
        }

        sync(viewportState = null) {
            const options = this.mod.navbar || {};

            if (!options || !UIkit?.util) {
                this._teardown();
                return;
            }

            const enabled = this.toBool(options.userDropdownHoldOpenEnabled);

            if (!enabled || !viewportState?.uiDesktop) {
                this._teardown();
                return;
            }

            if (!this._bindings.isEmpty()) {
                return;
            }

            const elements = this._resolveElements(options);

            if (!elements) {
                this._teardown();
                return;
            }

            const { toggle, dropdown, navbarDropdown } = elements;
            const drop = UIkit.getComponent(dropdown, 'drop') || UIkit.drop(dropdown);

            if (!drop?.show || !drop?.hide) {
                this._teardown();
                return;
            }

            const closeDelay = this.toInteger(options.userDropdownCloseDelay, constants.TIMINGS.dropdownCloseFallback);
            const closeOnNavClick = this.toBool(options.userDropdownCloseOnNavClick);
            let closeTimer = null;

            const clearCloseTimer = () => {
                window.clearTimeout(closeTimer);
                closeTimer = null;
            };

            const hideNavbarDropdown = () => {
                if (!navbarDropdown) {
                    return;
                }

                const navbarDrop = UIkit.getComponent(navbarDropdown, 'drop') || UIkit.drop(navbarDropdown);
                navbarDrop?.hide?.(false);
            };

            const isInside = () =>
                toggle.matches(':hover, :focus')
                || dropdown.matches(':hover, :focus')
                || dropdown.contains(document.activeElement);

            const keepOpen = () => clearCloseTimer();

            const closeLater = () => {
                clearCloseTimer();

                closeTimer = window.setTimeout(() => {
                    if (!isInside()) {
                        drop.hide(false);
                    }
                }, closeDelay);
            };

            const onBeforeHide = (event) => {
                if (isInside()) {
                    event.preventDefault();
                }
            };

            const onToggleMouseEnter = () => {
                keepOpen();
                drop.show();
                hideNavbarDropdown();
            };

            const onDropdownClick = (event) => {
                const link = event.target?.closest?.('a');

                if (link && link.getAttribute('href') && link.getAttribute('href') !== '#') {
                    drop.hide(false);
                }
            };

            UIkit.util.on(dropdown, 'beforehide', onBeforeHide);
            this._bindings.add(() => UIkit.util.off?.(dropdown, 'beforehide', onBeforeHide));

            toggle.addEventListener('mouseenter', onToggleMouseEnter);
            this._bindings.add(() => toggle.removeEventListener('mouseenter', onToggleMouseEnter));

            toggle.addEventListener('mouseleave', closeLater);
            this._bindings.add(() => toggle.removeEventListener('mouseleave', closeLater));

            dropdown.addEventListener('mouseenter', keepOpen);
            this._bindings.add(() => dropdown.removeEventListener('mouseenter', keepOpen));

            dropdown.addEventListener('mouseleave', closeLater);
            this._bindings.add(() => dropdown.removeEventListener('mouseleave', closeLater));

            if (closeOnNavClick) {
                dropdown.addEventListener('click', onDropdownClick);
                this._bindings.add(() => dropdown.removeEventListener('click', onDropdownClick));
            }

            this._bindings.add(clearCloseTimer);
        }

        _resolveElements(options) {
            const selectors = {
                root: options.userDropdownSelectorRoot,
                user: options.userDropdownSelectorUser,
                toggle: options.userDropdownSelectorToggle,
                dropdown: options.userDropdownSelectorDropdown,
                navbarDropdown: options.userDropdownSelectorNavbarDropdown,
            };

            const root = this.select(selectors.root);
            const user = this.select(selectors.user);
            const toggle = this.select(selectors.toggle);
            const dropdown = this.select(selectors.dropdown);
            const navbarDropdown = selectors.navbarDropdown ? this.select(selectors.navbarDropdown) : null;

            if (!root || !user || !toggle || !dropdown) {
                return null;
            }

            return { root, user, toggle, dropdown, navbarDropdown };
        }

        _teardown() {
            this._bindings.flush();
            this._bindings = new CleanupBag();
        }
    }

    CopyMyPage.features.DesktopUserDropdown = DesktopUserDropdownFeature;
})(window.UIkit, window, document, window.CopyMyPage);
