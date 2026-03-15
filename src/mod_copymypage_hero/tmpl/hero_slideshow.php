<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.6
 */

\defined('_JEXEC') or die;

/** @var array<int, object> $slides */
/** @var \Joomla\Registry\Registry $params */
/** @var object $module */
/** @var string $slideshowOptions */
/** @var string $moduleclass_sfx */

$moduleClass = 'cmp-module cmp-module--hero cmp-module--hero-slideshow';
$lazyPlaceholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

if (!empty($moduleclass_sfx)) {
    $moduleClass .= ' ' . $moduleclass_sfx;
}
?>
<div class="<?php echo $moduleClass; ?>">
    <div class="uk-position-relative uk-visible-toggle uk-light" tabindex="-1"
        uk-slideshow="<?php echo htmlspecialchars($slideshowOptions, ENT_QUOTES, 'UTF-8'); ?>">
        <ul class="uk-slideshow-items">
            <?php foreach ($slides as $slide) : ?>
                <?php
                $src = htmlspecialchars((string) ($slide->src ?? ''), ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars((string) ($slide->alt ?? ''), ENT_QUOTES, 'UTF-8');
                $isLazy = !empty($slide->isLazy) && $slide->isLazy === true;
                $width = (int) ($slide->width ?? 0);
                $height = (int) ($slide->height ?? 0);
                $fetchPriority = htmlspecialchars((string) ($slide->fetchPriority ?? ($isLazy ? 'low' : 'high')), ENT_QUOTES, 'UTF-8');
                ?>
                <li>
                    <img
                        <?php if ($isLazy) : ?>
                            src="<?php echo $lazyPlaceholder; ?>"
                            data-src="<?php echo $src; ?>"
                            loading="lazy"
                            decoding="async"
                            fetchpriority="<?php echo $fetchPriority; ?>"
                            uk-img
                        <?php else : ?>
                            src="<?php echo $src; ?>"
                            loading="eager"
                            decoding="async"
                            fetchpriority="<?php echo $fetchPriority; ?>"
                        <?php endif; ?>
                        <?php if ($width > 0) : ?>
                            width="<?php echo $width; ?>"
                        <?php endif; ?>
                        <?php if ($height > 0) : ?>
                            height="<?php echo $height; ?>"
                        <?php endif; ?>
                        alt="<?php echo $alt; ?>"
                        uk-cover
                    >
                    <?php if (!empty($slide->headline) || !empty($slide->subline)) : ?>
                        <div class="uk-position-center uk-text-center cmp-hero-overlay">
                            <?php if (!empty($slide->headline)) : ?>
                                <h2 class="uk-heading-medium">
                                    <?php echo htmlspecialchars((string) $slide->headline, ENT_QUOTES, 'UTF-8'); ?>
                                </h2>
                            <?php endif; ?>

                            <?php if (!empty($slide->subline)) : ?>
                                <p class="uk-text-lead">
                                    <?php echo htmlspecialchars((string) $slide->subline, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <a class="uk-position-center-left uk-position-small" href="#"
            uk-slidenav-previous uk-slideshow-item="previous"></a>
        <a class="uk-position-center-right uk-position-small" href="#"
            uk-slidenav-next uk-slideshow-item="next"></a>

        <ul class="uk-slideshow-nav uk-dotnav uk-flex-center"></ul>
    </div>
</div>
