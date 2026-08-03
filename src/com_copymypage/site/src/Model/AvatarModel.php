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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;

/**
 * Model for the current user's isolated avatar workflow.
 */
final class AvatarModel extends BaseDatabaseModel
{
    /**
     * @return array<string, mixed>|null
     */
    public function getItem(): ?array
    {
        $identity = Factory::getApplication()->getIdentity();

        if (!$identity instanceof User || (int) $identity->id === 0 || (bool) $identity->guest) {
            return null;
        }

        $avatars = Factory::getContainer()->get(AvatarService::class);

        return [
            'avatar' => $avatars->getAvatar($identity),
            'items'  => $avatars->getPickerItems($identity),
            'user'   => $identity,
        ];
    }
}
