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
use Joomla\CMS\Event\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Service\AccountMenuProvider;
use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;
use Joomla\Component\Users\Site\Model\ProfileModel;

/**
 * Shared controller workflow for projected current-user forms.
 */
abstract class AbstractUserFormController extends BaseController
{
    /**
     * Enter a projected edit layout for the authenticated user only.
     */
    protected function editProjectedForm(string $context, string $editLayout): bool
    {
        $user        = $this->app->getIdentity();
        $currentId   = $user instanceof User ? (int) $user->id : 0;
        $requestedId = $this->input->getInt('user_id', $currentId);

        if ($currentId === 0 || $requestedId !== $currentId) {
            $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->app->setHeader('status', 403, true);

            return false;
        }

        if (!$this->hasPasswordAuthenticatedSession($user)) {
            return false;
        }

        // Resolve and validate the form context before entering its layout.
        $this->getFormService()->getStateKey($context);
        $this->setRedirect($this->getDashboardUrl($editLayout));

        return true;
    }

    /**
     * Validate and persist an explicitly projected com_users form.
     *
     * @return bool|null
     */
    protected function saveProjectedForm(
        string $context,
        string $editLayout,
        string $successLayout,
        string $successMessage,
        string $failureMessage
    ): ?bool {
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

        $forms    = $this->getFormService();
        $prepared = $forms->prepareForm($context);
        $form     = $prepared['form'] ?? null;
        $model    = $prepared['model'] ?? null;

        if (!$form instanceof Form || !$model instanceof ProfileModel) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODELCLASS_NOT_FOUND'), 500);
        }

        $requestData = $this->app->getInput()->post->get('jform', [], 'array');
        $requestData = \is_array($requestData) ? $requestData : [];

        try {
            $this->prepareProjectedFormSupplement($context, $user, $requestData);
        } catch (\Throwable $exception) {
            $projectedData = $forms->projectSubmittedData($context, $requestData);

            $this->app->setUserState(
                $forms->getStateKey($context),
                $forms->getFailureState($context, $projectedData)
            );
            $this->setMessage(
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : Text::_($failureMessage),
                'warning'
            );
            $this->setRedirect($this->getDashboardUrl($editLayout));

            return false;
        }

        $requestData = $forms->projectSubmittedData($context, $requestData);

        // Give Joomla's normalisation event a server-controlled identity, then
        // apply the allowlist again so listeners cannot broaden this workflow.
        $normalisedData       = $requestData;
        $normalisedData['id'] = (int) $user->id;
        $objData              = (object) $normalisedData;

        $this->getDispatcher()->dispatch(
            'onContentNormaliseRequestData',
            new Model\NormaliseRequestDataEvent(
                'onContentNormaliseRequestData',
                [
                    'context' => 'com_users.user',
                    'data'    => $objData,
                    'subject' => $form,
                ]
            )
        );

        $requestData = $forms->projectSubmittedData($context, (array) $objData);
        $validated   = $model->validate($form, $requestData);

        if ($validated === false) {
            $this->enqueueModelErrors($model);
            $this->app->setUserState(
                $forms->getStateKey($context),
                $forms->getFailureState($context, $requestData)
            );
            $this->setRedirect($this->getDashboardUrl($editLayout));

            return false;
        }

        $saveData       = $forms->buildSaveData($context, $user, $validated);
        $originalOption = $this->input->get('option', null, 'raw');

        $this->input->set('option', 'com_users');

        try {
            // Core user plugins continue to see their native component context.
            $result = $model->save($saveData);
        } finally {
            $this->input->set('option', $originalOption);
        }

        if ($result === false) {
            $this->app->setUserState(
                $forms->getStateKey($context),
                $forms->getFailureState($context, $validated)
            );
            $this->setMessage(
                Text::sprintf($failureMessage, (string) $model->getError()),
                'warning'
            );
            $this->setRedirect($this->getDashboardUrl($editLayout));

            return false;
        }

        try {
            $this->saveProjectedFormSupplement($context, $user);
        } catch (\Throwable $exception) {
            $this->app->setUserState(
                $forms->getStateKey($context),
                $forms->getFailureState($context, $validated)
            );
            $this->setMessage(
                $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : Text::_($failureMessage),
                'warning'
            );
            $this->setRedirect($this->getDashboardUrl($editLayout));

            return false;
        }

        $this->app->setUserState($forms->getStateKey($context), null);
        $this->setMessage(Text::_($successMessage));
        $this->setRedirect($this->getDashboardUrl($successLayout));

        return null;
    }

    /**
     * Leave an edit workflow without retaining its submitted values.
     */
    protected function cancelProjectedForm(string $context, string $targetLayout): void
    {
        $this->checkToken();

        $forms = $this->getFormService();
        $user  = $this->app->getIdentity();

        if ($user instanceof User && (int) $user->id > 0 && !(bool) $user->guest) {
            $this->cancelProjectedFormSupplement($context, $user);
        }

        $this->app->setUserState($forms->getStateKey($context), null);
        $this->setRedirect($this->getDashboardUrl($targetLayout));
    }

    /**
     * Validate and retain data owned by a projected form's specialist service.
     *
     * @param array<string, mixed> $requestData Untrusted full jform payload.
     */
    protected function prepareProjectedFormSupplement(
        string $context,
        User $user,
        array $requestData
    ): void {
    }

    /**
     * Persist specialist data after Joomla has saved the projected user form.
     */
    protected function saveProjectedFormSupplement(string $context, User $user): void
    {
    }

    /**
     * Discard specialist state when the projected workflow is cancelled.
     */
    protected function cancelProjectedFormSupplement(string $context, User $user): void
    {
    }

    /**
     * Resolve the shared projection service from the root DI container.
     */
    private function getFormService(): UserFormProjectionService
    {
        return Factory::getContainer()->get(UserFormProjectionService::class);
    }

    /**
     * Disallow sensitive account changes from a remembered cookie login.
     */
    protected function hasPasswordAuthenticatedSession(User $user): bool
    {
        if (!isset($user->cookieLogin) || empty($user->cookieLogin)) {
            return true;
        }

        $this->app->enqueueMessage(Text::_('JGLOBAL_REMEMBER_MUST_LOGIN'), 'message');
        $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));

        return false;
    }

    /**
     * Push at most three validation errors to the message queue.
     */
    private function enqueueModelErrors(ProfileModel $model): void
    {
        $errors = $model->getErrors();

        for ($index = 0, $count = \count($errors); $index < $count && $index < 3; $index++) {
            $message = $errors[$index] instanceof \Throwable
                ? $errors[$index]->getMessage()
                : (string) $errors[$index];

            $this->app->enqueueMessage($message, CMSWebApplicationInterface::MSG_ERROR);
        }
    }

    /**
     * Create an internal dashboard URL.
     */
    protected function getDashboardUrl(string $layout): string
    {
        if ($this->app instanceof CMSWebApplicationInterface) {
            return Factory::getContainer()
                ->get(AccountMenuProvider::class)
                ->getDashboardUrl($this->app, $layout);
        }

        return Route::_(
            'index.php?option=com_copymypage&view=dashboard&layout=' . $layout,
            false
        );
    }
}
