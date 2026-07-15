<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Handles contact-form submissions from mod_copymypage_contact.
 */
final class ContactController extends BaseController
{
    /**
     * Validate and send a CopyMyPage contact request.
     *
     * @return  bool
     */
    public function submit(): bool
    {
        $this->checkToken('post');

        $return   = $this->getReturnPage();
        $moduleId = $this->input->post->getInt('module_id');
        $module   = ModuleHelper::getModuleById((string) $moduleId);

        if (!$this->isValidContactModule($module, $moduleId)) {
            $this->setRedirect(
                $return,
                Text::_('COM_COPYMYPAGE_CONTACT_ERROR_INVALID_MODULE'),
                'error'
            );

            return false;
        }

        $model = $this->getModel('Contact', 'Site', ['ignore_request' => true]);

        if (!$model) {
            $this->setRedirect(
                $return,
                Text::_('COM_COPYMYPAGE_CONTACT_ERROR_MODEL_UNAVAILABLE'),
                'error'
            );

            return false;
        }

        $model->setState('contact.module', $module);

        $form = $model->getForm([], false);
        $data = $this->input->post->get('jform', [], 'array');

        if (!$form) {
            $this->setRedirect(
                $return,
                Text::_('COM_COPYMYPAGE_CONTACT_ERROR_FORM_UNAVAILABLE'),
                'error'
            );

            return false;
        }

        $validatedData = $model->validate($form, $data);
        $stateKey      = self::getFormStateKey($moduleId);

        if ($validatedData === false) {
            $hasValidationMessage = false;

            foreach ($model->getErrors() as $error) {
                $message = $error instanceof \Throwable
                    ? $error->getMessage()
                    : (string) $error;

                if ($message !== '') {
                    $this->app->enqueueMessage($message, 'error');
                    $hasValidationMessage = true;
                }
            }

            $this->app->setUserState($stateKey, $data);
            $this->setRedirect($return);

            if (!$hasValidationMessage) {
                $this->app->enqueueMessage(
                    Text::_('COM_COPYMYPAGE_CONTACT_ERROR_VALIDATION_FAILED'),
                    'error'
                );
            }

            return false;
        }

        if (!$model->sendMessage($validatedData)) {
            $this->app->setUserState($stateKey, $data);
            $message = trim((string) $model->getError());

            $this->setRedirect(
                $return,
                $message !== '' ? $message : Text::_('COM_COPYMYPAGE_CONTACT_ERROR_SEND_FAILED'),
                'error'
            );

            return false;
        }

        $this->app->setUserState($stateKey, null);
        $this->setRedirect(
            $return,
            Text::_('COM_COPYMYPAGE_CONTACT_SEND_SUCCESS'),
            'success'
        );

        return true;
    }

    /**
     * Validate the submitted module against the active frontend module list.
     *
     * @param   object  $module    Resolved module object.
     * @param   int     $moduleId  Submitted module ID.
     *
     * @return  bool
     */
    private function isValidContactModule(object $module, int $moduleId): bool
    {
        return $moduleId > 0
            && (int) ($module->id ?? 0) === $moduleId
            && (string) ($module->module ?? '') === 'mod_copymypage_contact'
            && strtolower(trim((string) ($module->position ?? ''))) === 'contact';
    }

    /**
     * Resolve a safe internal return URL.
     *
     * @return  string
     */
    private function getReturnPage(): string
    {
        $encoded = trim($this->input->post->getString('return', ''));
        $decoded = $encoded !== '' ? base64_decode($encoded, true) : false;

        if (!\is_string($decoded) || $decoded === '' || !Uri::isInternal($decoded)) {
            return Route::_('index.php?option=com_copymypage&view=onepage#contact', false);
        }

        return $decoded;
    }

    /**
     * Return the per-module session state key shared with ContactHelper.
     *
     * @param   int  $moduleId  Module instance ID.
     *
     * @return  string
     */
    private static function getFormStateKey(int $moduleId): string
    {
        return 'com_copymypage.contact.form.' . max(0, $moduleId);
    }
}
