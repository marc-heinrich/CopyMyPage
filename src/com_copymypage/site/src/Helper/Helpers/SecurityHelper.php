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
use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;
use Joomla\Component\Users\Administrator\Helper\Mfa;

/**
 * Supplies password and MFA data to dashboard security layouts.
 */
final class SecurityHelper
{
    public function __construct(private readonly UserFormProjectionService $forms)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraData(string $layoutName = 'security'): array
    {
        $user = $this->forms->getCurrentUser();

        if (!$user instanceof User) {
            return [];
        }

        $prepared = $this->forms->prepareForm(
            UserFormProjectionService::CONTEXT_SECURITY_EDIT
        );

        $extraData = [
            'profileForm'   => $prepared['form'],
            'profileParams' => $prepared['params'],
            'userData'      => $prepared['data'],
        ];

        if (strtolower(trim($layoutName)) !== 'security.edit') {
            $extraData['mfaConfigurationUI'] = Mfa::getConfigurationInterface($user);
            $extraData['mfaActive'] = !empty($extraData['mfaConfigurationUI'])
                && $this->hasActiveMfaMethod($user);
        }

        return $extraData;
    }

    /**
     * Determine whether Joomla currently recognises an active MFA method.
     *
     * @param   User  $user  The user whose MFA configuration is checked.
     *
     * @return  boolean
     */
    private function hasActiveMfaMethod(User $user): bool
    {
        $availableMethods = Mfa::getMfaMethods();

        foreach (Mfa::getUserMfaRecords((int) $user->id) as $record) {
            if (isset($availableMethods[$record->method])) {
                return true;
            }
        }

        return false;
    }
}
