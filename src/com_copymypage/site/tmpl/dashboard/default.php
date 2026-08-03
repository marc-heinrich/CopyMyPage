<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
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
$futureSections = (array) ($dashboard['futureSections'] ?? []);
$editUrl        = $this->profileEditUrl;
$profileUrl     = $this->profileUrl;

?>
<div class="cmp-dashboard cmp-dashboard--overview">
    <header class="cmp-dashboard__profile-header" aria-labelledby="cmp-dashboard-title">
        <span class="cmp-dashboard__avatar cmp-dashboard__avatar--editable">
            <span class="cmp-dashboard__avatar-content" aria-hidden="true">
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
            <a
                class="cmp-dashboard__avatar-action"
                href="<?php echo $escape($editUrl . '#cmp-profile-avatar'); ?>"
                aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_EDIT_TITLE')); ?>"
            >
                <span><?php echo Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ACTION_EDIT'); ?></span>
            </a>
        </span>
        <div class="cmp-dashboard__profile-identity">
            <h1 id="cmp-dashboard-title" class="cmp-dashboard__title">
                <?php echo $escape($profile['name'] ?? ''); ?>
            </h1>
            <a class="cmp-dashboard-account-manage" href="<?php echo $escape($editUrl); ?>">
                <?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_ACTION_MANAGE_ACCOUNT'); ?>
                <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
            </a>
        </div>
    </header>

    <?php echo LayoutHelper::render('copymypage.dashboard.navigation', $navigation); ?>

    <div class="cmp-dashboard__content">
        <section class="cmp-dashboard-section" aria-labelledby="cmp-dashboard-account-title">
            <h2 id="cmp-dashboard-account-title" class="cmp-dashboard-section__title">
                <?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_ACCOUNT_DETAILS_TITLE'); ?>
            </h2>
            <dl class="cmp-dashboard-details">
                <div>
                    <dt><?php echo Text::_('COM_USERS_PROFILE_USERNAME_LABEL'); ?></dt>
                    <dd><?php echo $escape($profile['username'] ?? ''); ?></dd>
                </div>
                <div>
                    <dt><?php echo Text::_('COM_USERS_PROFILE_EMAIL1_LABEL'); ?></dt>
                    <dd><?php echo $escape($profile['email'] ?? ''); ?></dd>
                </div>
            </dl>
            <a class="cmp-dashboard-row-action" href="<?php echo $escape($profileUrl); ?>">
                <span><?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_ACTION_VIEW_PROFILE'); ?></span>
                <span uk-icon="icon: chevron-right" aria-hidden="true"></span>
            </a>
        </section>

        <section class="cmp-dashboard-section cmp-dashboard-future" aria-labelledby="cmp-dashboard-future-title">
            <h2 id="cmp-dashboard-future-title" class="cmp-dashboard-section__title">
                <?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_FUTURE_TITLE'); ?>
            </h2>
            <ul class="cmp-dashboard-future__list">
                <?php foreach ($futureSections as $section) : ?>
                    <li>
                        <span class="cmp-dashboard-future__icon" aria-hidden="true">
                            <span uk-icon="icon: <?php echo $escape($section['icon'] ?? 'future'); ?>"></span>
                        </span>
                        <span><?php echo Text::_((string) ($section['label'] ?? '')); ?></span>
                        <small><?php echo Text::_('COM_COPYMYPAGE_DASHBOARD_FUTURE_PLANNED'); ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <?php
    echo LayoutHelper::render(
        'copymypage.dashboard.logout',
        (array) ($navigation['logout'] ?? [])
    );
    ?>
</div>
