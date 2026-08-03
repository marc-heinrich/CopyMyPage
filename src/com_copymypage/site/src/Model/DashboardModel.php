<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Helper\DashboardHelper;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;
use Joomla\Registry\Registry;

/**
 * Model for the authenticated CopyMyPage dashboard.
 */
class DashboardModel extends BaseDatabaseModel
{
    /**
     * Build the dashboard data for the current Joomla identity.
     *
     * @return  array<string, mixed>|null
     */
    public function getItem(): ?array
    {
        $identity = Factory::getApplication()->getIdentity();

        if (!$identity instanceof User || (int) $identity->id === 0) {
            return null;
        }

        $extraData = $this->getState('extra_data', []);
        $extraData = \is_array($extraData) ? $extraData : [];
        $layout    = (string) $this->getState('form_name', 'default');
        $avatar    = Factory::getContainer()
            ->get(AvatarService::class)
            ->getAvatar($identity);

        if ($layout === 'default') {
            $extraData['avatar'] = $avatar;

            return DashboardHelper::buildDashboardData($identity, $extraData);
        }

        $userData        = $extraData['userData'] ?? null;
        $userForm        = $extraData['profileForm'] ?? null;
        $avatarForm      = $extraData['profileAvatarForm'] ?? null;
        $address         = $extraData['profileAddress'] ?? [];
        $addressForm     = $extraData['profileAddressForm'] ?? null;
        $params          = $extraData['profileParams'] ?? null;
        $requiresForm    = \in_array($layout, ['profile.edit', 'security', 'security.edit'], true);
        $requiresAvatar  = $layout === 'profile.edit';
        $requiresAddress = $layout === 'profile.address';

        if (
            !$userData instanceof User
            || (int) $userData->id !== (int) $identity->id
            || ($requiresForm && !$userForm instanceof Form)
            || ($requiresAvatar && !$avatarForm instanceof Form)
            || ($requiresAddress && !$addressForm instanceof Form)
        ) {
            return null;
        }

        return [
            'address'                      => \is_array($address) ? $address : [],
            'addressForm'                  => $addressForm instanceof Form ? $addressForm : null,
            'avatarForm'                   => $avatarForm instanceof Form ? $avatarForm : null,
            'avatarMaximumUploadSizeLabel' => (string) (
                $extraData['profileAvatarMaximumUploadSizeLabel'] ?? ''
            ),
            'data'                         => $userData,
            'form'                         => $userForm instanceof Form ? $userForm : null,
            'mfaActive'                    => (bool) ($extraData['mfaActive'] ?? false),
            'mfaConfigurationUI'           => $extraData['mfaConfigurationUI'] ?? null,
            'params'                       => $params instanceof Registry ? $params : new Registry(),
            'profile'                      => [
                'email'    => trim((string) $identity->email),
                'id'       => (int) $identity->id,
                'initials' => DashboardHelper::getInitials((string) $identity->name),
                'name'     => trim((string) $identity->name),
                'username' => trim((string) $identity->username),
                'avatar'   => $avatar,
            ],
        ];
    }

    /**
     * Populate component parameters without accepting request-controlled user ids.
     *
     * @return  void
     */
    protected function populateState(): void
    {
        $this->setState('params', Factory::getApplication()->getParams('com_copymypage'));
    }
}
