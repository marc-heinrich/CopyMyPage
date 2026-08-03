<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

/**
 * Normalized account navigation shared by navbar, mobile menu and dashboard.
 */
final class AccountMenuProvider
{
    /**
     * Component-owned Joomla menu type.
     *
     * @var string
     */
    public const MENU_TYPE = 'copymypage-account';

    /**
     * Request-local cache of normalized destination items.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $itemsCache = [];

    /**
     * Build account destinations and session actions for the current identity.
     *
     * @param   CMSWebApplicationInterface  $app              Active site application.
     * @param   string|null                 $dashboardLayout  Optional dashboard layout override.
     *
     * @return array{
     *     guest: bool,
     *     activeKey: string,
     *     items: array<int, array<string, mixed>>,
     *     login: array<string, mixed>|null,
     *     logout: array<string, mixed>|null
     * }
     */
    public function getMenu(CMSWebApplicationInterface $app, ?string $dashboardLayout = null): array
    {
        $this->loadLanguage($app);

        $user    = $app->getIdentity();
        $isGuest = !$user instanceof User || (int) $user->id === 0 || (bool) $user->guest;

        if ($isGuest) {
            return [
                'guest'     => true,
                'activeKey' => '',
                'items'     => [],
                'login'     => $this->buildLoginAction(),
                'logout'    => null,
            ];
        }

        $items           = $this->getDestinationItems($app, $user);
        $navigationState = $this->resolveNavigationState($app, $dashboardLayout, $items);
        $items           = $this->applyNavigationState($items, $navigationState);

        return [
            'guest'     => false,
            'activeKey' => $navigationState['activeKey'],
            'items'     => $items,
            'login'     => null,
            'logout'    => $this->buildLogoutAction(),
        ];
    }

    /**
     * Build a dashboard URL while retaining the matching account-menu Itemid.
     */
    public function getDashboardUrl(
        CMSWebApplicationInterface $app,
        string $layout = 'default'
    ): string {
        $layout = strtolower(trim($layout));
        $layout = \in_array(
            $layout,
            ['default', 'profile', 'profile.address', 'profile.edit', 'security', 'security.edit'],
            true
        )
            ? $layout
            : 'default';
        $key    = $layout === 'default' ? 'overview' : explode('.', $layout, 2)[0];
        $itemId = 0;
        $user   = $app->getIdentity();

        if ($user instanceof User && (int) $user->id > 0 && !(bool) $user->guest) {
            $item   = $this->findItemByKey($this->getDestinationItems($app, $user), $key);
            $itemId = (int) ($item['id'] ?? 0);
        }

        $url = 'index.php?option=com_copymypage&view=dashboard';

        if ($layout !== 'default') {
            $url .= '&layout=' . rawurlencode($layout);
        }

        if ($itemId > 0) {
            $url .= '&Itemid=' . $itemId;
        }

        return Route::link('site', $url, false);
    }

    /**
     * Build the profile editor URL anchored at the avatar panel.
     */
    public function getAvatarUrl(CMSWebApplicationInterface $app): string
    {
        return $this->getDashboardUrl($app, 'profile.edit') . '#cmp-profile-avatar';
    }

    /**
     * Load and normalize the visible account destinations.
     *
     * @param   CMSWebApplicationInterface  $app   Active site application.
     * @param   User                        $user  Current authenticated user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDestinationItems(CMSWebApplicationInterface $app, User $user): array
    {
        $levels = $user->getAuthorisedViewLevels();
        sort($levels);

        $cacheKey = implode(
            ':',
            [
                (string) $user->id,
                $app->getLanguage()->getTag(),
                implode(',', $levels),
            ]
        );

        if (isset($this->itemsCache[$cacheKey])) {
            return $this->itemsCache[$cacheKey];
        }

        $menuItems        = $app->getMenu('site')->getItems('menutype', self::MENU_TYPE) ?: [];
        $nodes            = [];
        $childrenByParent = [];

        foreach ($menuItems as $menuItem) {
            $params = $menuItem->getParams();

            if ((int) $params->get('menu_show', 1) !== 1) {
                continue;
            }

            $node = $this->normalizeMenuItem($menuItem);

            if ($node === null) {
                continue;
            }

            $id       = (int) $node['id'];
            $parentId = (int) $node['parentId'];
            $parentId = $parentId > 1 ? $parentId : 1;

            $nodes[$id]                    = $node;
            $childrenByParent[$parentId][] = $id;
        }

        $visited = [];
        $items   = $this->buildItemTree($nodes, $childrenByParent, 1, $visited);

        $this->itemsCache[$cacheKey] = $items;

        return $items;
    }

    /**
     * Normalize one Joomla menu item for all account-menu renderers.
     *
     * @param   object  $menuItem  Joomla site menu item.
     *
     * @return array<string, mixed>|null
     */
    private function normalizeMenuItem(object $menuItem): ?array
    {
        $id = (int) ($menuItem->id ?? 0);

        if ($id <= 0) {
            return null;
        }

        $params = $menuItem->getParams();
        $key    = $this->resolveItemKey(
            (string) $params->get('copymypage_account_key', ''),
            (string) ($menuItem->note ?? ''),
            (string) ($menuItem->alias ?? '')
        );
        $type  = $this->resolveNodeType((string) ($menuItem->type ?? ''));
        $label = trim(Text::_((string) ($menuItem->title ?? '')));
        $url   = $type === 'link' ? $this->resolveItemUrl($menuItem) : '';

        if ($type === 'link' && ($key === '' || $label === '' || $url === '')) {
            return null;
        }

        if ($type === 'heading' && $label === '') {
            return null;
        }

        return [
            'id'          => $id,
            'parentId'    => (int) ($menuItem->parent_id ?? 1),
            'level'       => max(1, (int) ($menuItem->level ?? 1)),
            'key'         => $key,
            'type'        => $type,
            'label'       => $label,
            'url'         => $url,
            'icon'        => $this->normalizeToken(
                (string) $params->get(
                    'copymypage_account_icon',
                    $this->getDefaultIcon($key)
                )
            ),
            'current'     => false,
            'activeTrail' => false,
            'ariaCurrent' => '',
            'children'    => [],
        ];
    }

    /**
     * Build a safe tree containing only nodes reachable from Joomla's menu root.
     *
     * @param   array<int, array<string, mixed>>       $nodes             Normalized nodes by id.
     * @param   array<int, array<int, int>>            $childrenByParent  Ordered child ids by parent.
     * @param   int                                    $parentId          Current parent id.
     * @param   array<int, bool>                       $visited           Cycle guard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildItemTree(
        array $nodes,
        array $childrenByParent,
        int $parentId,
        array &$visited
    ): array {
        $items = [];

        foreach ($childrenByParent[$parentId] ?? [] as $id) {
            if (isset($visited[$id]) || !isset($nodes[$id])) {
                continue;
            }

            $visited[$id]     = true;
            $node             = $nodes[$id];
            $node['children'] = $this->buildItemTree(
                $nodes,
                $childrenByParent,
                $id,
                $visited
            );
            $items[] = $node;
        }

        return $items;
    }

    /**
     * Resolve the current destination and active branch from the request.
     *
     * @param   CMSWebApplicationInterface       $app              Active site application.
     * @param   string|null                      $dashboardLayout  Optional Dashboard layout.
     * @param   array<int, array<string, mixed>>  $items            Normalized item tree.
     *
     * @return array{
     *     activeKey: string,
     *     exactId: int,
     *     locationId: int,
     *     trailIds: array<int, int>
     * }
     */
    private function resolveNavigationState(
        CMSWebApplicationInterface $app,
        ?string $dashboardLayout,
        array $items
    ): array {
        $input = $app->getInput();

        if (
            $input->getCmd('option', '') === 'com_copymypage'
            && $input->getCmd('view', '') === 'dashboard'
        ) {
            $layout = strtolower(trim($dashboardLayout ?? $input->getString('layout', 'default')));
            $layout = $layout === '' ? 'default' : $layout;
            $key    = $layout === 'default'
                ? 'overview'
                : $this->normalizeToken(explode('.', $layout, 2)[0]);
            $item   = $this->findItemByKey($items, $key);
            $itemId = (int) ($item['id'] ?? 0);
            $exact  = !str_contains($layout, '.');

            return [
                'activeKey'  => $key,
                'exactId'    => $exact ? $itemId : 0,
                'locationId' => $exact ? 0 : $itemId,
                'trailIds'   => [],
            ];
        }

        $active = $app->getMenu('site')->getActive();

        if (!$active || (string) ($active->menutype ?? '') !== self::MENU_TYPE) {
            return [
                'activeKey'  => '',
                'exactId'    => 0,
                'locationId' => 0,
                'trailIds'   => [],
            ];
        }

        $activeKey = $this->resolveItemKey(
            (string) $active->getParams()->get('copymypage_account_key', ''),
            (string) ($active->note ?? ''),
            (string) ($active->alias ?? '')
        );

        return [
            'activeKey'  => $activeKey,
            'exactId'    => (int) ($active->id ?? 0),
            'locationId' => 0,
            'trailIds'   => array_values(
                array_filter(
                    array_map('intval', (array) ($active->tree ?? [])),
                    static fn(int $id): bool => $id > 1
                )
            ),
        ];
    }

    /**
     * Apply exact-current and active-branch state recursively.
     *
     * @param   array<int, array<string, mixed>>  $items  Normalized item tree.
     * @param   array<string, mixed>              $state  Resolved navigation state.
     *
     * @return array<int, array<string, mixed>>
     */
    private function applyNavigationState(array $items, array $state): array
    {
        foreach ($items as &$item) {
            $children      = \is_array($item['children'] ?? null) ? $item['children'] : [];
            $children      = $this->applyNavigationState($children, $state);
            $id            = (int) ($item['id'] ?? 0);
            $current       = $id > 0 && $id === (int) ($state['exactId'] ?? 0);
            $location      = $id > 0 && $id === (int) ($state['locationId'] ?? 0);
            $inTrail       = $id > 0 && \in_array($id, (array) ($state['trailIds'] ?? []), true);
            $childIsActive = false;

            foreach ($children as $child) {
                if ((bool) ($child['activeTrail'] ?? false)) {
                    $childIsActive = true;
                    break;
                }
            }

            $item['children']    = $children;
            $item['current']     = $current;
            $item['activeTrail'] = $current || $location || $inTrail || $childIsActive;
            $item['ariaCurrent'] = $current ? 'page' : ($location ? 'location' : '');
        }

        unset($item);

        return $items;
    }

    /**
     * Find the first item with a stable key in depth-first menu order.
     *
     * @param   array<int, array<string, mixed>>  $items  Normalized item tree.
     * @param   string                           $key    Stable destination key.
     *
     * @return array<string, mixed>|null
     */
    private function findItemByKey(array $items, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        foreach ($items as $item) {
            if ((string) ($item['key'] ?? '') === $key) {
                return $item;
            }

            $child = $this->findItemByKey(
                \is_array($item['children'] ?? null) ? $item['children'] : [],
                $key
            );

            if ($child !== null) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Normalize Joomla menu item types into the renderer contract.
     */
    private function resolveNodeType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'heading'   => 'heading',
            'separator' => 'separator',
            default     => 'link',
        };
    }

    /**
     * Resolve a stable item key from menu params, note or alias.
     */
    private function resolveItemKey(string $parameterKey, string $note, string $alias): string
    {
        $key = $this->normalizeToken($parameterKey);

        if ($key !== '') {
            return $key;
        }

        $notePrefix = 'copymypage.account.';

        if (str_starts_with($note, $notePrefix)) {
            $key = $this->normalizeToken(substr($note, \strlen($notePrefix)));

            if ($key !== '') {
                return $key;
            }
        }

        return $this->normalizeToken(
            preg_replace('/^cmp-account-/', '', strtolower(trim($alias))) ?? ''
        );
    }

    /**
     * Build the frontend URL for a Joomla menu item.
     */
    private function resolveItemUrl(object $menuItem): string
    {
        $id   = (int) ($menuItem->id ?? 0);
        $type = (string) ($menuItem->type ?? '');

        if ($id > 0 && $type === 'component') {
            return Route::link('site', 'index.php?Itemid=' . $id, false);
        }

        if ($id > 0 && $type === 'alias') {
            $targetId = (int) $menuItem->getParams()->get('aliasoptions', 0);

            return Route::link(
                'site',
                'index.php?Itemid=' . ($targetId > 0 ? $targetId : $id),
                false
            );
        }

        $link = trim((string) ($menuItem->link ?? ''));

        if ($link === '') {
            return '';
        }

        if (
            $type === 'url'
            && $id > 0
            && str_starts_with($link, 'index.php?')
            && !preg_match('/(?:^|[?&])Itemid=/', $link)
        ) {
            $link .= '&Itemid=' . $id;
        }

        return str_starts_with($link, 'index.php?')
            ? Route::link('site', $link, false)
            : $link;
    }

    /**
     * Build the guest login action.
     *
     * @return array<string, mixed>
     */
    private function buildLoginAction(): array
    {
        return [
            'key'     => 'login',
            'label'   => Text::_('JLOGIN'),
            'url'     => Route::link('site', 'index.php?option=com_users&view=login', false),
            'icon'    => 'sign-in',
            'current' => false,
        ];
    }

    /**
     * Build the authenticated logout action with a current form token.
     *
     * @return array<string, mixed>
     */
    private function buildLogoutAction(): array
    {
        $return = rawurlencode(base64_encode(Uri::root() . 'index.php'));

        return [
            'key'     => 'logout',
            'label'   => Text::_('JLOGOUT'),
            'url'     => Route::link(
                'site',
                'index.php?option=com_users&task=user.logout&'
                . Session::getFormToken()
                . '=1&return='
                . $return,
                false
            ),
            'icon'    => 'sign-out',
            'current' => false,
        ];
    }

    /**
     * Return a default UIkit icon for known account sections.
     */
    private function getDefaultIcon(string $key): string
    {
        return match ($key) {
            'overview' => 'grid',
            'profile'  => 'user',
            'security' => 'lock',
            'orders'   => 'bag',
            'tickets'  => 'tag',
            'posts'    => 'file-text',
            default    => '',
        };
    }

    /**
     * Normalize values used as stable keys or icon tokens.
     */
    private function normalizeToken(string $value): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($value))) ?? '';
    }

    /**
     * Load language strings needed outside com_copymypage.
     */
    private function loadLanguage(CMSWebApplicationInterface $app): void
    {
        $language = $app->getLanguage();

        $language->load(
            'com_copymypage',
            JPATH_SITE . '/components/com_copymypage',
            null,
            true
        );
        $language->load('com_users', JPATH_SITE, null, true);
    }
}
