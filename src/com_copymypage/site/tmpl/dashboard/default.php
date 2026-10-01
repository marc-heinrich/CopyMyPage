<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.22
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Dashboard\HtmlView $this */

$escape         = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$dashboard      = $this->dashboard;
$profile        = (array) ($dashboard['profile'] ?? []);
$avatar         = \is_array($profile['avatar'] ?? null) ? $profile['avatar'] : [];
$navigation     = $this->accountMenu;
$destinationKeys = [
    'profile'  => true,
    'security' => true,
    'tickets'  => true,
];
$destinationUrls = [];

$collectDestinationUrls = static function (array $items) use (
    &$collectDestinationUrls,
    &$destinationUrls,
    $destinationKeys
): void {
    foreach ($items as $item) {
        if (!\is_array($item)) {
            continue;
        }

        $key = trim((string) ($item['key'] ?? ''));
        $url = trim((string) ($item['url'] ?? ''));

        if (isset($destinationKeys[$key]) && $url !== '' && !isset($destinationUrls[$key])) {
            $destinationUrls[$key] = $url;
        }

        if (!empty($item['children']) && \is_array($item['children'])) {
            $collectDestinationUrls($item['children']);
        }
    }
};

$collectDestinationUrls((array) ($navigation['items'] ?? []));

$overviewGroups = [
    [
        'id'    => 'cmp-dashboard-account-actions',
        'title' => 'COM_COPYMYPAGE_DASHBOARD_OVERVIEW_ACCOUNT_TITLE',
        'items' => [
            [
                'key'   => 'profile',
                'label' => 'COM_COPYMYPAGE_DASHBOARD_OVERVIEW_PERSONAL_DETAILS',
                'icon'  => 'user',
            ],
            [
                'key'   => 'security',
                'label' => 'COM_COPYMYPAGE_DASHBOARD_OVERVIEW_SECURITY_SETTINGS',
                'icon'  => 'lock',
            ],
        ],
    ],
    [
        'id'    => 'cmp-dashboard-booking-actions',
        'title' => 'COM_COPYMYPAGE_DASHBOARD_OVERVIEW_BOOKINGS_TITLE',
        'items' => [
            [
                'key'   => 'tickets',
                'label' => 'COM_COPYMYPAGE_DASHBOARD_OVERVIEW_TICKETS',
                'icon'  => 'tag',
            ],
        ],
    ],
];

?>
<div class="cmp-dashboard cmp-dashboard--overview">
    <header class="cmp-dashboard__profile-header" aria-labelledby="cmp-dashboard-title">
        <span class="cmp-dashboard__avatar" aria-hidden="true">
            <span class="cmp-dashboard__avatar-content">
                <?php if (!empty($avatar['exists']) && trim((string) ($avatar['url'] ?? '')) !== '') : ?>
                    <img
                        src="<?php echo $escape($avatar['url']); ?>"
                        alt=""
                        loading="eager"
                        decoding="async"
                    >
                <?php else : ?>
                    <?php echo $escape($profile['initials'] ?? '?'); ?>
                <?php endif; ?>
            </span>
        </span>
        <div class="cmp-dashboard__profile-identity">
            <h1 id="cmp-dashboard-title" class="cmp-dashboard__title">
                <?php echo $escape($profile['name'] ?? ''); ?>
            </h1>
        </div>
    </header>

    <?php echo LayoutHelper::render('copymypage.dashboard.navigation', $navigation); ?>

    <div class="cmp-dashboard__content">
        <?php foreach ($overviewGroups as $group) : ?>
            <?php
            $groupItems = array_values(array_filter(
                $group['items'],
                static fn(array $item): bool => isset($destinationUrls[$item['key']])
            ));

            if ($groupItems === []) {
                continue;
            }
            ?>
            <section class="cmp-dashboard-section cmp-dashboard-link-group" aria-labelledby="<?php echo $escape($group['id']); ?>">
                <h2 id="<?php echo $escape($group['id']); ?>" class="cmp-dashboard-section__title">
                    <?php echo $escape(Text::_($group['title'])); ?>
                </h2>
                <ul class="cmp-dashboard-link-group__list">
                    <?php foreach ($groupItems as $item) : ?>
                        <li class="cmp-dashboard-link-group__item">
                            <a
                                class="cmp-dashboard-link-group__link"
                                href="<?php echo $escape($destinationUrls[$item['key']]); ?>"
                            >
                                <span class="cmp-dashboard-link-group__icon" aria-hidden="true">
                                    <span uk-icon="icon: <?php echo $escape($item['icon']); ?>"></span>
                                </span>
                                <span class="cmp-dashboard-link-group__label">
                                    <?php echo $escape(Text::_($item['label'])); ?>
                                </span>
                                <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
                                    <span uk-icon="icon: chevron-right"></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <?php
    echo LayoutHelper::render(
        'copymypage.dashboard.logout',
        (array) ($navigation['logout'] ?? [])
    );
    ?>
</div>
