<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Minimal routing class for com_copymypage.
 *
 * Keeps routing simple (RouterView + core rules) so Route::link() works without errors.
 */
class Router extends RouterView
{
    /**
     * Router constructor.
     *
     * @param SiteApplication $app  The application object.
     * @param AbstractMenu    $menu The menu object.
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        // Register the views we want to be routable.
        $this->registerView(new RouterViewConfiguration('onepage'));

        parent::__construct($app, $menu);

        // Core-like rule order (as in com_config).
        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}
