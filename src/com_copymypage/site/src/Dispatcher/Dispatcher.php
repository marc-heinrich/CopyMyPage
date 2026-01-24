<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Component\CopyMyPage\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * ComponentDispatcher class for com_copymypage (site).
 *
 * Loads additional language packs required by shared UI elements and
 * performs view-based access checks.
 *
 * @since  0.0.4
 */
final class Dispatcher extends ComponentDispatcher
{
    /**
     * Constructor for the CopyMyPage Dispatcher.
     *
     * @param   CMSApplicationInterface  $app         The application instance.
     * @param   Input                    $input       The input instance.
     * @param   MVCFactoryInterface      $mvcFactory  The MVC factory instance.
     *
     * @since   0.0.4
     */
    public function __construct(
        CMSApplicationInterface $app,
        Input $input,
        MVCFactoryInterface $mvcFactory
    ) {
        parent::__construct($app, $input, $mvcFactory);
    }

    /**
     * Load the language.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    protected function loadLanguage(): void
    {
        $lang = $this->app->getLanguage();

        // Load component language files (site + administrator).
        $lang->load('com_copymypage', JPATH_SITE, null, true);
        $lang->load('com_copymypage', JPATH_ADMINISTRATOR, null, true);

        // Load language files from other components used by shared UI.
        $lang->load('com_users', JPATH_SITE, null, true);
        $lang->load('com_users', JPATH_ADMINISTRATOR, null, true);
        $lang->load('com_contact', JPATH_SITE, null, true);
        $lang->load('com_contact', JPATH_ADMINISTRATOR, null, true);

        parent::loadLanguage();
    }

    /**
     * Method to check component access permission.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    protected function checkAccess(): void
    {
        parent::checkAccess();

        $view   = (string) $this->input->getCmd('view');
        $option = (string) $this->input->getCmd('option');
        $user   = $this->app->getIdentity();

        // Access check for a potential dashboard view (kept from legacy project logic).
        if ($option === 'com_copymypage' && $view === 'dashboard') {
            if (!$user->authorise('core.login.site', 'com_copymypage')) {
                throw new NotAllowed(
                    $this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'),
                    403
                );
            }
        }
    }

    /**
     * Get a controller from the component.
     *
     * @param   string  $name    Controller name.
     * @param   string  $client  Optional client (like Administrator, Site etc.).
     * @param   array   $config  Optional controller config.
     *
     * @return  BaseController  The requested controller instance.
     *
     * @since   0.0.4
     */
    public function getController(
        string $name,
        string $client = '',
        array $config = []
    ): BaseController {
        return parent::getController($name, $client, $config);
    }
}
