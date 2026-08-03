<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;
use Joomla\Component\CopyMyPage\Site\Service\ProfileAddressService;
use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;

/**
 * Supplies personal profile data to dashboard profile layouts.
 */
final class ProfileHelper
{
    public function __construct(
        private readonly UserFormProjectionService $forms,
        private readonly ProfileAddressService $addresses,
        private readonly AvatarService $avatars
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraData(string $layoutName = 'profile'): array
    {
        $user = $this->forms->getCurrentUser();

        if (!$user instanceof User) {
            return [];
        }

        $layoutName = strtolower(trim($layoutName));

        if ($layoutName === 'profile.address') {
            return [
                'profileAddress'     => $this->addresses->getAddress($user),
                'profileAddressForm' => $this->addresses->prepareForm($user),
                'userData'           => $user,
            ];
        }

        if ($layoutName !== 'profile.edit') {
            return [
                'profileAddress' => $this->addresses->getAddress($user),
                'userData'       => $user,
            ];
        }

        $prepared = $this->forms->prepareForm(
            UserFormProjectionService::CONTEXT_PROFILE_EDIT
        );

        return [
            'profileAvatarForm'                   => $this->avatars->prepareForm($user),
            'profileAvatarMaximumUploadSizeLabel' => $this->avatars->getMaximumUploadSizeLabel(),
            'profileForm'                         => $prepared['form'],
            'profileParams'                       => $prepared['params'],
            'userData'                            => $prepared['data'],
        ];
    }
}
