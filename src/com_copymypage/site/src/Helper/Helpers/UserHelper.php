<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;

/**
 * Helper for loading external user-related data for the CopyMyPage dashboard.
 *
 * This helper boots com_users, creates the Profile model and returns its data
 * via the well-known getData() method. The raw data can then be attached to
 * the dashboard model as extra data and processed in the template.
 */
final class UserHelper
{
    /**
     * Load profile data from com_users for the current user.
     *
     * @return  array  The profile data as array. Returns an empty array if
     *                 no model or data is available.
     */
    public function getExtraData(): array
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0) {
            return [];
        }

        // Boot com_users and get its MVC factory.
        $usersComponent = $app->bootComponent('com_users');
        $mvcFactory     = $usersComponent->getMVCFactory();

        /** @var BaseDatabaseModel $profileModel */
        $profileModel = $mvcFactory->createModel('Profile', 'Site', ['ignore_request' => true]);

        // Ensure the model has the expected getData() method.
        if (!\method_exists($profileModel, 'getData')) {
            return [];
        }

        // Optionally set the user id on the model if required.
        if (\method_exists($profileModel, 'setState')) {
            $profileModel->setState('user.id', (int) $user->id);
        }

        $data = $profileModel->getData();

        // Normalise the result to an array to keep the dashboard model simple.
        if (\is_array($data)) {
            return $data;
        }

        if (\is_object($data)) {
            return (array) $data;
        }

        return [];
    }
}
