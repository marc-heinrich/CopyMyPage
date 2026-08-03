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

use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;

/**
 * Handles password changes within the dashboard security section.
 */
final class SecurityController extends AbstractUserFormController
{
    /**
     * Enter the projected password form.
     */
    public function edit(): bool
    {
        return $this->editProjectedForm(
            UserFormProjectionService::CONTEXT_SECURITY_EDIT,
            'security.edit'
        );
    }

    /**
     * Validate and save only the current user's new password.
     *
     * @return bool|null
     */
    public function save(): ?bool
    {
        $submittedLayout = strtolower(trim($this->input->post->getCmd('layout', 'security.edit')));
        $failureLayout   = \in_array($submittedLayout, ['security', 'security.edit'], true)
            ? $submittedLayout
            : 'security.edit';

        return $this->saveProjectedForm(
            UserFormProjectionService::CONTEXT_SECURITY_EDIT,
            $failureLayout,
            'security',
            'COM_COPYMYPAGE_SECURITY_SAVE_SUCCESS',
            'COM_COPYMYPAGE_SECURITY_SAVE_FAILED'
        );
    }

    /**
     * Cancel password editing.
     */
    public function cancel(): void
    {
        $this->cancelProjectedForm(
            UserFormProjectionService::CONTEXT_SECURITY_EDIT,
            'default'
        );
    }
}
