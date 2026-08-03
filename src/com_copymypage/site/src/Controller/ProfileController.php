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

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;
use Joomla\Component\CopyMyPage\Site\Service\ProfileAddressService;
use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;

/**
 * Handles the current user's personal profile details.
 */
final class ProfileController extends AbstractUserFormController
{
    /**
     * Canonical avatar value prepared before the projected profile save.
     */
    private string $avatarSelection = '';

    /**
     * Enter the projected personal-details form.
     */
    public function edit(): bool
    {
        return $this->editProjectedForm(
            UserFormProjectionService::CONTEXT_PROFILE_EDIT,
            'profile.edit'
        );
    }

    /**
     * Validate and save name, email and required legal consent fields.
     *
     * @return bool|null
     */
    public function save(): ?bool
    {
        return $this->saveProjectedForm(
            UserFormProjectionService::CONTEXT_PROFILE_EDIT,
            'profile.edit',
            'profile',
            'COM_USERS_PROFILE_SAVE_SUCCESS',
            'COM_USERS_PROFILE_SAVE_FAILED'
        );
    }

    /**
     * Cancel personal-details editing.
     */
    public function cancel(): void
    {
        $this->cancelProjectedForm(
            UserFormProjectionService::CONTEXT_PROFILE_EDIT,
            'default'
        );
    }

    /**
     * Validate and retain the avatar submitted with the personal profile form.
     *
     * @param array<string, mixed> $requestData Untrusted full jform payload.
     */
    protected function prepareProjectedFormSupplement(
        string $context,
        User $user,
        array $requestData
    ): void {
        if ($context !== UserFormProjectionService::CONTEXT_PROFILE_EDIT) {
            return;
        }

        if (!\array_key_exists('avatar', $requestData) || !\is_scalar($requestData['avatar'])) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_SELECTION'));
        }

        $this->avatarSelection = $this->getAvatarService()->retainSelection(
            $user,
            trim((string) $requestData['avatar'])
        );
    }

    /**
     * Save the retained avatar after Joomla has saved name and email.
     */
    protected function saveProjectedFormSupplement(string $context, User $user): void
    {
        if ($context === UserFormProjectionService::CONTEXT_PROFILE_EDIT) {
            $this->getAvatarService()->save($user, $this->avatarSelection);
        }
    }

    /**
     * Remove pending avatar files when profile editing is cancelled.
     */
    protected function cancelProjectedFormSupplement(string $context, User $user): void
    {
        if ($context === UserFormProjectionService::CONTEXT_PROFILE_EDIT) {
            $this->getAvatarService()->cancel($user);
        }
    }

    /**
     * Validate and save the current user's private profile address.
     *
     * @return bool|null
     */
    public function saveAddress(): ?bool
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return false;
        }

        if (!$this->hasPasswordAuthenticatedSession($user)) {
            return false;
        }

        $addresses   = $this->getAddressService();
        $form        = $addresses->prepareForm($user);
        $requestData = $this->app->getInput()->post->get('jform', [], 'array');
        $validation  = $addresses->validate(
            $form,
            \is_array($requestData) ? $requestData : []
        );

        if ($validation['errors'] !== []) {
            $this->enqueueAddressErrors($validation['errors']);
            $this->app->setUserState(
                $addresses->getStateKey($user),
                $validation['data']
            );
            $this->setRedirect($this->getDashboardUrl('profile.address'));

            return false;
        }

        try {
            $addresses->save($user, $validation['data']);
        } catch (\Throwable) {
            $this->app->setUserState(
                $addresses->getStateKey($user),
                $validation['data']
            );
            $this->setMessage(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SAVE_FAILED'), 'warning');
            $this->setRedirect($this->getDashboardUrl('profile.address'));

            return false;
        }

        $this->app->setUserState($addresses->getStateKey($user), null);
        $this->setMessage(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SAVE_SUCCESS'));
        $this->setRedirect($this->getDashboardUrl('profile'));

        return null;
    }

    /**
     * Return local region options for the authenticated address form.
     */
    public function regions(): void
    {
        if (!Session::checkToken('get')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $this->app->close();

            return;
        }

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            echo new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true);
            $this->app->close();

            return;
        }

        $countryCode = $this->app->getInput()->getCmd('country', '');
        $regions     = [];

        foreach ($this->getAddressService()->getRegions($countryCode) as $code => $name) {
            $regions[] = [
                'value' => $code,
                'text'  => $name,
            ];
        }

        echo new JsonResponse($regions);
        $this->app->close();
    }

    /**
     * Leave address editing without retaining submitted values.
     */
    public function cancelAddress(): void
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user instanceof User || (int) $user->id === 0 || (bool) $user->guest) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        $addresses = $this->getAddressService();

        $this->app->setUserState($addresses->getStateKey($user), null);
        $this->setRedirect($this->getDashboardUrl('default'));
    }

    /**
     * Resolve the profile-address service from the root container.
     */
    private function getAddressService(): ProfileAddressService
    {
        return Factory::getContainer()->get(ProfileAddressService::class);
    }

    /**
     * Resolve the avatar service from the root container.
     */
    private function getAvatarService(): AvatarService
    {
        return Factory::getContainer()->get(AvatarService::class);
    }

    /**
     * Push at most three address validation errors to Joomla's message queue.
     *
     * @param array<int, \Throwable|string> $errors
     */
    private function enqueueAddressErrors(array $errors): void
    {
        for ($index = 0, $count = \count($errors); $index < $count && $index < 3; $index++) {
            $message = $errors[$index] instanceof \Throwable
                ? $errors[$index]->getMessage()
                : (string) $errors[$index];

            $this->app->enqueueMessage($message, CMSWebApplicationInterface::MSG_ERROR);
        }
    }
}
