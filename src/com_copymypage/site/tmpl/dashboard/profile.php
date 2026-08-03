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
$profile = (array) ($this->dashboard['profile'] ?? []);
$avatar  = \is_array($profile['avatar'] ?? null) ? $profile['avatar'] : [];
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
        <section class="cmp-profile-summary" aria-labelledby="cmp-profile-summary-title">
            <span class="cmp-dashboard__avatar cmp-profile-summary__avatar" aria-hidden="true">
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
            <div>
                <h2 id="cmp-profile-summary-title">
                    <?php echo $escape($this->data->name); ?>
                </h2>
                <p class="cmp-profile-summary__username">
                    @<?php echo $escape($this->data->username); ?>
                </p>
            </div>
        </section>

        <div class="cmp-profile-sections">
            <?php echo $this->loadTemplate('core'); ?>
            <?php echo $this->loadTemplate('address'); ?>
        </div>
    </div>
</div>
