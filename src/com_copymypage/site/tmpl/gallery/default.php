<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Gallery\HtmlView $this */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$viewClass = 'cmp-gallery-view';
?>
<section id="gallery-view" class="<?php echo $escape($viewClass); ?>">
    <div class="uk-container">
        <div class="cmp-gallery-view__header">
            <h1 class="cmp-gallery-view__title">
                <?php echo $escape($this->headline); ?>
            </h1>

            <hr class="cmp-gallery-view__divider uk-divider-small">

            <?php if ($this->summary !== '') : ?>
                <p class="cmp-gallery-view__meta">
                    <span class="cmp-gallery-view__meta-icon" uk-icon="icon: calendar" aria-hidden="true"></span>
                    <span class="cmp-gallery-view__meta-text">
                        <?php echo $escape($this->summary); ?>
                    </span>
                </p>
            <?php endif; ?>

            <a class="cmp-gallery-view__back" href="<?php echo $escape($this->backUrl); ?>">
                <span><?php echo $escape(Text::_('COM_COPYMYPAGE_VIEW_GALLERY_BACKBUTTON')); ?></span>
            </a>
        </div>

        <?php if (!empty($this->warnings)) : ?>
            <?php echo LayoutHelper::render('copymypage.system.warning', ['messages' => $this->warnings]); ?>
        <?php else : ?>
            <div class="cmp-gallery-view__content cmp-gallery-view__content--instagram-grid">
                <?php
                echo HTMLHelper::_(
                    'content.prepare',
                    '{gallery layout=flow alignment=center}' . trim((string) $this->itemParams?->get('source', '')) . '{/gallery}'
                );
                ?>
            </div>
        <?php endif; ?>
    </div>
</section>
