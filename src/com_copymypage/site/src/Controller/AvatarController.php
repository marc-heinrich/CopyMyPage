<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;

/**
 * Handles the current user's isolated avatar workflow.
 */
final class AvatarController extends AbstractUserFormController
{
    /**
     * Persist a selected avatar or clear the existing value.
     */
    public function save(): ?bool
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        if (!$this->hasPasswordAuthenticatedSession($user)) {
            return false;
        }

        $submitted = $this->app->getInput()->post->get('jform', [], 'array');
        $value     = \is_array($submitted) && \is_scalar($submitted['avatar'] ?? null)
            ? trim((string) $submitted['avatar'])
            : '';

        try {
            $this->getAvatarService()->save($user, $value);
        } catch (\Throwable $exception) {
            $this->setMessage(
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SAVE_FAILED'),
                'warning'
            );
            $this->setRedirect($this->getAvatarUrl());

            return false;
        }

        $this->setMessage(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SAVE_SUCCESS'));
        $this->setRedirect($this->getDashboardUrl('default'));

        return null;
    }

    /**
     * Upload one validated raster image for selection in the private picker.
     */
    public function upload(): ?bool
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        if (!$this->hasPasswordAuthenticatedSession($user)) {
            return false;
        }

        $file = $this->app->getInput()->files->get('avatar_file', [], 'array');

        try {
            $this->getAvatarService()->upload($user, \is_array($file) ? $file : []);
        } catch (\Throwable $exception) {
            $this->setMessage(
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_UPLOAD'),
                'warning'
            );
            $this->setRedirect($this->getPickerUrl());

            return false;
        }

        $this->setMessage(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_UPLOAD_SUCCESS'));
        $this->setRedirect($this->getPickerUrl());

        return null;
    }

    /**
     * Discard pending uploads and return to the Dashboard overview.
     */
    public function cancel(): void
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if ($user instanceof User && (int) $user->id > 0 && !(bool) $user->guest) {
            $this->getAvatarService()->cancel($user);
        }

        $this->setRedirect($this->getDashboardUrl('default'));
    }

    private function getAvatarService(): AvatarService
    {
        return Factory::getContainer()->get(AvatarService::class);
    }

    private function getPickerUrl(): string
    {
        $drawerContext = $this->input->getCmd('cmp_context', '') === 'drawer'
            ? '&cmp_context=drawer'
            : '';

        return Route::_(
            'index.php?option=com_copymypage&view=avatar&layout=picker&tmpl=component'
                . $drawerContext
                . $this->getItemIdSuffix(),
            false
        );
    }

    private function getAvatarUrl(): string
    {
        return $this->getDashboardUrl('profile.edit') . '#cmp-profile-avatar';
    }

    private function getItemIdSuffix(): string
    {
        $itemId = $this->input->getInt('Itemid');

        return $itemId > 0 ? '&Itemid=' . $itemId : '';
    }
}
