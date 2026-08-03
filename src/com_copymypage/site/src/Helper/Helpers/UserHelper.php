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

use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;

/**
 * Helper for loading external user-related data for the CopyMyPage dashboard.
 *
 * The overview consumes only the stable current-user summary. Profile and
 * security form preparation belongs to their own layout helpers.
 */
final class UserHelper
{
    public function __construct(private readonly UserFormProjectionService $forms)
    {
    }

    /**
     * Load profile data from com_users for the current user.
     *
     * @param   string  $layoutName  Active dashboard layout.
     *
     * @return  array<string, mixed>  Profile and form data.
     */
    public function getExtraData(string $layoutName = 'default'): array
    {
        return ['data' => $this->forms->getCurrentUserSummary()];
    }
}
