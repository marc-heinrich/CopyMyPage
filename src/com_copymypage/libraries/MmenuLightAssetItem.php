<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.10
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

/**
 * Web Asset Item class for MmenuLight bootstrap logic.
 */
final class MmenuLightAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Attach the MmenuLight initializer.
     *
     * @param  Document  $doc  The document instance.
     */
    public function onAttachCallback(Document $doc): void
    {
        Text::script('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED');

        $doc->addScriptDeclaration("
            (function () {
                const initMmenuLight = function () {
                    {$this->getMmenuLightJS()}
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initMmenuLight, { once: true });
                    return;
                }

                initMmenuLight();
            })();
        ");
    }

    /**
     * Build the MmenuLight initialization script.
     *
     * @return  string  JavaScript initializer snippet.
     */
    private function getMmenuLightJS(): string
    {
        return "
            if (typeof window.MmenuLight === 'undefined') {
                console.error(Joomla.Text._('TPL_COPYMYPAGE_JS_ERROR_NOT_DEFINED').replace('%s', 'MmenuLight'));
                return;
            }

            const options = Joomla.getOptions('copymypage.params', {}) || {};
            const cfg = options.mod?.navbarSlots?.mobilemenu || options.mod?.navbar || {};

            const mediaQuery = cfg.mmenuLightMediaQuery || 'all';
            const theme = cfg.mmenuLightTheme || 'light';
            const selected = cfg.mmenuLightSelectedClass || '';
            const closeOnClick = !!cfg.mmenuLightCloseOnClick;
            const slidingSubmenus = (cfg.mmenuLightSlidingSubmenus !== undefined)
                ? !!cfg.mmenuLightSlidingSubmenus
                : true;

            const menus = [
                {
                    id: cfg.navOffcanvasId,
                    title: cfg.mmenuLightNavTitle,
                    position: cfg.mmenuLightNavPosition || 'left'
                },
                {
                    id: cfg.userOffcanvasId,
                    title: cfg.mmenuLightUserTitle,
                    position: cfg.mmenuLightUserPosition || 'right'
                },
                {
                    id: cfg.basketOffcanvasId,
                    title: cfg.mmenuLightBasketTitle,
                    position: cfg.mmenuLightBasketPosition || 'right'
                }
            ].filter((menu) => !!(menu && menu.id));

            const drawers = [];

            const closeAllExcept = (keep) => {
                drawers.forEach((drawer) => {
                    if (drawer !== keep && drawer && typeof drawer.close === 'function') {
                        drawer.close();
                    }
                });
            };

            menus.forEach((menuConfig) => {
                const nav = document.getElementById(menuConfig.id);

                // Skip missing or already initialized nodes to keep re-inits idempotent.
                if (!nav || nav.dataset.cmpMmenulightInit === '1') {
                    return;
                }

                nav.dataset.cmpMmenulightInit = '1';

                const menu = new window.MmenuLight(nav, mediaQuery);

                menu.navigation({
                    theme: theme,
                    selected: selected || undefined,
                    slidingSubmenus: slidingSubmenus,
                    title: (menuConfig.title && String(menuConfig.title).trim())
                        ? String(menuConfig.title).trim()
                        : undefined
                });

                const drawer = menu.offcanvas({
                    position: (menuConfig.position === 'right') ? 'right' : 'left'
                });

                drawers.push(drawer);

                const openers = Array.from(
                    document.querySelectorAll('[data-cmp-mmenulight-open=\"#' + menuConfig.id + '\"]')
                );

                const setOpenersState = (isOpen) => {
                    openers.forEach((opener) => {
                        opener.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

                        if (typeof opener.state !== 'undefined') {
                            opener.state = isOpen ? 'cross' : 'bars';
                        }
                    });
                };

                const open = () => {
                    closeAllExcept(drawer);

                    if (drawer && typeof drawer.open === 'function') {
                        drawer.open();
                    }
                };

                if (drawer && drawer.wrapper instanceof Element) {
                    const syncState = () => {
                        setOpenersState(drawer.wrapper.classList.contains('mm-ocd--open'));
                    };

                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.attributeName === 'class') {
                                syncState();
                            }
                        });
                    });

                    observer.observe(drawer.wrapper, {
                        attributes: true,
                        attributeFilter: ['class']
                    });

                    syncState();
                } else {
                    setOpenersState(false);
                }

                openers.forEach((opener) => {
                    opener.addEventListener('click', (event) => {
                        event.preventDefault();
                        open();
                    });

                    opener.addEventListener('keyup', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            open();
                        }
                    });
                });

                if (closeOnClick) {
                    nav.addEventListener('click', (event) => {
                        const link = event.target.closest('a');

                        if (!link) {
                            return;
                        }

                        const href = link.getAttribute('href') || '';

                        if (!href || href === '#') {
                            return;
                        }

                        if (drawer && typeof drawer.close === 'function') {
                            drawer.close();
                        }
                    });
                }
            });
        ";
    }
}
