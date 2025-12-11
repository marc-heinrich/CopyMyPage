<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

/** @var array<int, array<string, mixed>> $slides */
/** @var \Joomla\Registry\Registry $params */
/** @var object $module */
/** @var string $options */

$moduleClassSfx = $params->get('moduleclass_sfx', '');
$moduleClass    = 'cmp-module cmp-module--slideshow';

if ($moduleClassSfx !== '') {
    $moduleClass .= ' ' . htmlspecialchars($moduleClassSfx, ENT_QUOTES, 'UTF-8');
}
?>
<div class="<?php echo $moduleClass; ?>">
    <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1"
        uk-slideshow="<?php echo htmlspecialchars($options, ENT_QUOTES, 'UTF-8'); ?>">
        <ul class="uk-slideshow-items" uk-height-viewport="offset-top: true">
            <?php foreach ($slides as $index => $slide) : ?>
                <li>
                    <img
                        src="<?php echo htmlspecialchars($slide['src'], ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($slide['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                        <?php if (!empty($slide['is_lazy']) && $slide['is_lazy'] === true) : ?>
                            loading="lazy"
                            decoding="async"
                        <?php endif; ?>
                        uk-cover
                    >
                    <?php if (!empty($slide['headline']) || !empty($slide['subline'])) : ?>
                        <div class="uk-position-center uk-text-center cmp-slideshow-overlay">
                            <?php if (!empty($slide['headline'])) : ?>
                                <h2 class="uk-heading-medium">
                                    <?php echo htmlspecialchars($slide['headline'], ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                            <?php endif; ?>

                            <?php if (!empty($slide['subline'])) : ?>
                                <p class="uk-text-lead">
                                    <?php echo htmlspecialchars($slide['subline'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <a class="uk-position-center-left uk-position-small uk-hidden-hover" href="#"
            uk-slidenav-previous uk-slideshow-item="previous"></a>
        <a class="uk-position-center-right uk-position-small uk-hidden-hover" href="#"
            uk-slidenav-next uk-slideshow-item="next"></a>

        <ul class="uk-slideshow-nav uk-dotnav uk-flex-center uk-margin"></ul>
    </div>
</div>
