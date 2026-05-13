<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Module\CopyMyPage\Team\Site\Helper\TeamHelper;

/**
 * Extracted variables
 * -----------------
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<string, mixed>                            $cfg
 * @var string                                          $eyebrow
 * @var string                                          $headline
 * @var string                                          $lead
 * @var array<int, object>                              $items
 * @var string                                          $warning
 * @var string                                          $hint
 * @var \Joomla\Module\CopyMyPage\Team\Site\Helper\TeamHelper|null $teamHelper
 */

$cfg      = \is_array($cfg ?? null) ? $cfg : [];
$layout   = strtolower(trim((string) ($layout ?? '')));
$eyebrow  = trim((string) ($eyebrow ?? ''));
$headline = trim((string) ($headline ?? ''));
$lead     = trim((string) ($lead ?? ''));
$items    = \is_array($items ?? null) ? $items : [];
$warning  = (string) ($warning ?? '');
$hint     = (string) ($hint ?? '');

if (!isset($teamHelper) || !$teamHelper instanceof TeamHelper) {
    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();

    // Activate template-specific assets here when the active layout needs them.
}

if ($warning !== '') {
    echo $warning;

    return;
}

$layoutConfig     = TeamHelper::getLayoutConfig($cfg, $layout);
$showImages       = TeamHelper::cfgBool($layoutConfig, 'showImages', true);
$showDescriptions = TeamHelper::cfgBool($layoutConfig, 'showDescriptions', true);
$showSocial       = TeamHelper::cfgBool($layoutConfig, 'showSocial', true);
$cardStyle        = strtolower(trim(TeamHelper::cfgString($layoutConfig, 'cardStyle', 'default')));
$cardStyle        = \in_array($cardStyle, ['default', 'primary', 'secondary'], true) ? $cardStyle : 'default';
$moduleClass      = 'cmp-module cmp-module--team cmp-module--team-cards';
$cardClass        = 'cmp-team__card uk-card uk-card-' . $cardStyle . ' uk-card-small uk-card-hover';

if ($headline === '') {
    $headline = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
}
?>
<section class="<?php echo htmlspecialchars($moduleClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="uk-container">
        <?php if ($eyebrow !== '' || $headline !== '' || $lead !== '') : ?>
            <header class="cmp-team__header uk-margin-large-bottom">
                <?php if ($eyebrow !== '') : ?>
                    <p class="cmp-team__eyebrow uk-text-meta uk-text-uppercase">
                        <?php echo htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
                <?php if ($headline !== '') : ?>
                    <h2 class="cmp-team__headline">
                        <?php echo htmlspecialchars($headline, ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                <?php endif; ?>
                <?php if ($lead !== '') : ?>
                    <p class="cmp-team__lead">
                        <?php echo htmlspecialchars($lead, ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($items !== []) : ?>
            <div
                class="cmp-team__grid uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-3@l uk-grid-match"
                uk-grid
                uk-scrollspy="target: > .cmp-team__item; cls: uk-animation-fade; delay: 120; repeat: false"
            >
                <?php foreach ($items as $item) : ?>
                    <?php
                    if (!\is_object($item)) {
                        continue;
                    }

                    $name        = trim((string) ($item->name ?? ''));
                    $role        = trim((string) ($item->role ?? ''));
                    $description = trim((string) ($item->description ?? ''));
                    $image       = trim((string) ($item->image ?? ''));
                    $imageAlt    = trim((string) ($item->imageAlt ?? $name));
                    $imageWidth  = (int) ($item->imageWidth ?? 0);
                    $imageHeight = (int) ($item->imageHeight ?? 0);
                    $social      = \is_array($item->social ?? null) ? $item->social : [];

                    if ($name === '' && $description === '') {
                        continue;
                    }

                    if ($imageAlt === '') {
                        $imageAlt = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_IMAGE_ALT');
                    }
                    ?>
                    <div class="cmp-team__item">
                        <article class="<?php echo htmlspecialchars($cardClass, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($showImages && $image !== '') : ?>
                                <div class="cmp-team__media uk-card-media-top">
                                    <img
                                        src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?php echo htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php if ($imageWidth > 0) : ?>
                                            width="<?php echo $imageWidth; ?>"
                                        <?php endif; ?>
                                        <?php if ($imageHeight > 0) : ?>
                                            height="<?php echo $imageHeight; ?>"
                                        <?php endif; ?>
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                            <?php endif; ?>

                            <div class="cmp-team__body uk-card-body">
                                <?php if ($role !== '') : ?>
                                    <div class="cmp-team__role uk-card-badge uk-label">
                                        <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($name !== '') : ?>
                                    <h3 class="cmp-team__name uk-card-title">
                                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                                    </h3>
                                <?php endif; ?>

                                <?php if ($showDescriptions && $description !== '') : ?>
                                    <p class="cmp-team__description">
                                        <?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($showSocial && $social !== []) : ?>
                                    <div class="cmp-team__social uk-flex uk-flex-wrap uk-flex-middle uk-grid-small" uk-grid>
                                        <?php foreach ($social as $link) : ?>
                                            <?php
                                            if (!\is_array($link)) {
                                                continue;
                                            }

                                            $url   = trim((string) ($link['url'] ?? ''));
                                            $label = trim((string) ($link['label'] ?? Text::_('MOD_COPYMYPAGE_TEAM_SOCIAL_LINK')));
                                            $icon  = trim((string) ($link['icon'] ?? 'link'));

                                            if ($url === '') {
                                                continue;
                                            }
                                            ?>
                                            <div>
                                                <a
                                                    class="uk-icon-button"
                                                    href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
                                                    aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                                    title="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span uk-icon="icon: <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($hint !== '') : ?>
            <?php echo $hint; ?>
        <?php endif; ?>
    </div>
</section>
