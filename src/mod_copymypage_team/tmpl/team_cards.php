<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.15
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
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

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

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
$cardStyle        = strtolower(trim(TeamHelper::cfgString($layoutConfig, 'cardStyle', 'default')));
$cardStyle        = \in_array($cardStyle, ['default', 'primary', 'secondary'], true) ? $cardStyle : 'default';
$moduleClass      = 'cmp-module cmp-module--team cmp-module--team-cards';

if ($headline === '') {
    $headline = Text::_('MOD_COPYMYPAGE_TEAM_DEFAULT_HEADLINE');
}
?>
<!-- Team Module Template: UIkit Framework (https://getuikit.com/docs/card) -->
<div class="<?php echo $escape($moduleClass); ?>">
    <div class="uk-container">
        <?php if ($eyebrow !== '' || $headline !== '' || $lead !== '') : ?>
            <header class="cmp-team__header cmp-section-header">
                <?php if ($eyebrow !== '') : ?>
                    <p class="cmp-team__eyebrow cmp-section-header__eyebrow">
                        <?php echo $escape($eyebrow); ?>
                    </p>
                <?php endif; ?>
                <?php if ($headline !== '') : ?>
                    <h2 class="cmp-team__headline cmp-section-header__headline">
                        <?php echo $escape($headline); ?>
                    </h2>
                <?php endif; ?>
                <?php if ($lead !== '') : ?>
                    <p class="cmp-team__lead cmp-section-header__lead">
                        <?php echo $escape($lead); ?>
                    </p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <?php if ($items !== []) : ?>
            <div
                class="cmp-team__grid uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-grid-column-small uk-grid-row-small uk-grid-match"
                uk-grid
                uk-scrollspy="target: > .cmp-team__item; cls: uk-animation-fade; delay: 120; repeat: false"
            >
                <?php foreach ($items as $item) : ?>
                    <?php
                    if (!\is_object($item)) {
                        continue;
                    }
                    ?>
                    <div class="cmp-team__item">
                        <?php echo LayoutHelper::render(
                            'copymypage.team.card',
                            [
                                'item'            => $item,
                                'cardStyle'       => $cardStyle,
                                'showImage'       => $showImages,
                                'showDescription' => $showDescriptions,
                                'headingTag'      => 'h3',
                                'variant'         => 'card',
                            ]
                        ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($hint !== '') : ?>
            <?php echo $hint; ?>
        <?php endif; ?>
    </div>
</div>
