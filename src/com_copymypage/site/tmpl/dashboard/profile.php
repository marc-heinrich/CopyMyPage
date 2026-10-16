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

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$heading = $this->params->get('show_page_heading')
    && trim((string) $this->params->get('page_heading', '')) !== ''
    ? (string) $this->params->get('page_heading')
    : Text::_('COM_COPYMYPAGE_PROFILE_TITLE');
?>
<div class="cmp-dashboard cmp-dashboard--profile com-users-profile profile">
    <header class="cmp-dashboard__page-header">
        <div>
            <h1 class="cmp-dashboard__page-title"><?php echo $escape($heading); ?></h1>
            <p class="cmp-dashboard__page-lead">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_LEAD'); ?>
            </p>
        </div>
    </header>

    <?php
    echo LayoutHelper::render(
        'copymypage.dashboard.navigation',
        $this->accountMenu
    );
    ?>

    <div class="cmp-dashboard__content">
        <section class="cmp-dashboard-section cmp-dashboard-link-group" aria-labelledby="cmp-profile-personal">
            <h2 id="cmp-profile-personal" class="cmp-dashboard-section__title">
                <?php echo Text::_('COM_COPYMYPAGE_PROFILE_PERSONAL_TITLE'); ?>
            </h2>
            <ul class="cmp-dashboard-link-group__list">
                <?php echo $this->loadTemplate('core'); ?>
                <?php echo $this->loadTemplate('address'); ?>
            </ul>
        </section>

        <section id="cmp-profile-security" class="cmp-dashboard-section cmp-dashboard-link-group" aria-labelledby="cmp-profile-security-title">
            <h2 id="cmp-profile-security-title" class="cmp-dashboard-section__title">
                <?php echo Text::_('COM_COPYMYPAGE_SECURITY_TITLE'); ?>
            </h2>
            <ul class="cmp-dashboard-link-group__list">
                <?php foreach ([
                    [
                        'url'         => $this->securityEditUrl,
                        'title'       => 'COM_COPYMYPAGE_SECURITY_PASSWORD_TITLE',
                        'description' => 'COM_COPYMYPAGE_SECURITY_PASSWORD_SUMMARY',
                        'icon'        => 'lock',
                    ],
                    [
                        'url'         => $this->securityMfaUrl,
                        'title'       => 'COM_COPYMYPAGE_SECURITY_MFA_TITLE',
                        'description' => 'COM_COPYMYPAGE_SECURITY_MFA_SUMMARY',
                        'icon'        => 'ban',
                    ],
                ] as $action) : ?>
                    <li class="cmp-dashboard-link-group__item">
                        <a class="cmp-dashboard-link-group__link" href="<?php echo $escape($action['url']); ?>">
                            <span class="cmp-dashboard-link-group__icon" aria-hidden="true">
                                <span uk-icon="icon: <?php echo $escape($action['icon']); ?>"></span>
                            </span>
                            <span class="cmp-dashboard-link-group__copy">
                                <span class="cmp-dashboard-link-group__label">
                                    <?php echo Text::_($action['title']); ?>
                                </span>
                                <span class="cmp-dashboard-link-group__description">
                                    <?php echo Text::_($action['description']); ?>
                                </span>
                            </span>
                            <span class="cmp-dashboard-link-group__chevron" aria-hidden="true">
                                <span uk-icon="icon: chevron-right"></span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
</div>
