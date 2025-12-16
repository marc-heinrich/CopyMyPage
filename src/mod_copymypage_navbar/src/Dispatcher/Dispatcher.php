<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Module\CopyMyPage\Navbar\Site\Helper\NavbarHelper;

\defined('_JEXEC') or die;

/**
 * Dispatcher class for the Navbar module
 *
 * @since  0.0.4
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    public function dispatch()
    {
        // Retrieve navbar data from the helper.
        $navbarParams = NavbarHelper::getParams();

        // Pass the parameters to the layout data.
        $displayData = $this->getLayoutData($navbarParams);

        // Ensure there is a logo before proceeding.
        if (empty($displayData['logo'])) {
            return; 
        }

        parent::dispatch(); 
    }

    /**
     * Returns the layout data for the Navbar module.
     *
     * @param  array  $navbarParams  The parameters for the Navbar module.
     *
     * @return  array  The layout data.
     *
     * @since   0.0.4
     */
    protected function getLayoutData($navbarParams)
    {
        $data = parent::getLayoutData();

        // Pass the navbar parameters into the layout data array.
        $data['logo']            = $navbarParams['logo'];
        $data['sticky']          = $navbarParams['sticky'];
        $data['moduleclass_sfx'] = $navbarParams['moduleclass_sfx'];

        return $data;
    }
}
