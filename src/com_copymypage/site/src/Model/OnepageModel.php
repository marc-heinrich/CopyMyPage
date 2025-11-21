<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Onepage model for the CopyMyPage component.
 *
 * This is a lightweight dummy model that only exposes
 * the component parameters via the model state.
 */
class OnepageModel extends BaseDatabaseModel
{
    /**
     * Automatically populates the model state.
     *
     * @return  void
     */
    protected function populateState(): void
    {
        $params = Factory::getApplication()->getParams('com_copymypage');
        $this->setState('params', $params);
    }
}
