<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Shared presentation data for authenticated CopyMyPage account pages.
 */
final class DashboardHelper
{
    /**
     * Load the component language when a shared layout is rendered by com_users.
     *
     * @return  void
     */
    public static function loadLanguage(): void
    {
        $language = Factory::getApplication()->getLanguage();

        $language->load(
            'com_copymypage',
            JPATH_SITE . '/components/com_copymypage',
            null,
            true
        );
        $language->load('com_users', JPATH_SITE, null, true);
        $language->load('com_users', JPATH_ADMINISTRATOR, null, true);
    }

    /**
     * Disable shared caching and search indexing for a personal page.
     *
     * @param   HtmlDocument  $document  Active HTML document.
     * @param   string        $title     Optional translated page title.
     *
     * @return  void
     */
    public static function preparePersonalPage(HtmlDocument $document, string $title = ''): void
    {
        $app = Factory::getApplication();

        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0', true);
        $app->setHeader('Pragma', 'no-cache', true);

        $document->setMetaData('robots', 'noindex, nofollow');

        $title = trim($title);

        if ($title === '') {
            return;
        }

        $siteName = trim((string) $app->get('sitename', ''));

        $document->setTitle($siteName !== '' ? $title . ' | ' . $siteName : $title);
    }

    /**
     * Build the dashboard view payload from current-user and com_users data.
     *
     * @param   User                  $user       Current Joomla user.
     * @param   array<string, mixed>  $extraData  Allowlisted payload from UserHelper.
     *
     * @return  array<string, mixed>
     */
    public static function buildDashboardData(User $user, array $extraData): array
    {
        $profile = isset($extraData['data']) && \is_array($extraData['data'])
            ? $extraData['data']
            : [];

        if ((int) ($profile['id'] ?? 0) !== (int) $user->id) {
            $profile = [];
        }

        $profile = array_replace(
            [
                'id'       => (int) $user->id,
                'name'     => trim((string) $user->name),
                'username' => trim((string) $user->username),
                'email'    => trim((string) $user->email),
            ],
            $profile
        );

        return [
            'profile' => [
                'avatar'   => \is_array($extraData['avatar'] ?? null)
                    ? $extraData['avatar']
                    : [],
                'id'       => (int) $profile['id'],
                'name'     => trim((string) $profile['name']),
                'username' => trim((string) $profile['username']),
                'email'    => trim((string) $profile['email']),
                'initials' => self::getInitials((string) $profile['name']),
            ],
            'futureSections' => [
                [
                    'key'   => 'orders',
                    'label' => 'COM_COPYMYPAGE_DASHBOARD_FUTURE_ORDERS',
                    'icon'  => 'bag',
                ],
                [
                    'key'   => 'tickets',
                    'label' => 'COM_COPYMYPAGE_DASHBOARD_FUTURE_TICKETS',
                    'icon'  => 'tag',
                ],
                [
                    'key'   => 'posts',
                    'label' => 'COM_COPYMYPAGE_DASHBOARD_FUTURE_POSTS',
                    'icon'  => 'file-text',
                ],
            ],
        ];
    }

    /**
     * Create a short avatar fallback from a display name.
     *
     * @param   string  $name  Display name.
     *
     * @return  string
     */
    public static function getInitials(string $name): string
    {
        $parts = preg_split('/[\s_-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return '?';
        }

        $letters = [mb_substr((string) $parts[0], 0, 1)];

        if (\count($parts) > 1) {
            $letters[] = mb_substr((string) $parts[array_key_last($parts)], 0, 1);
        }

        return mb_strtoupper(implode('', $letters));
    }
}
