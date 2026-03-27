<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\CopyMyPage\Site\View\Gallery\HtmlView $this */
?>
<section id="gallery-view" class="cmp-gallery-view">
    <div class="uk-container">
        <div class="cmp-gallery-view__header">
            <a class="cmp-gallery-view__back" href="<?php echo htmlspecialchars($this->backUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <span uk-icon="icon: arrow-left" aria-hidden="true"></span>
                <span><?php echo htmlspecialchars(Text::_('COM_COPYMYPAGE_VIEW_GALLERY_BACK'), ENT_QUOTES, 'UTF-8'); ?></span>
            </a>

            <h1 class="cmp-gallery-view__title">
                <?php echo htmlspecialchars($this->headline, ENT_QUOTES, 'UTF-8'); ?>
            </h1>

            <?php if ($this->summary !== '') : ?>
                <p class="cmp-gallery-view__lead">
                    <?php echo htmlspecialchars($this->summary, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <?php if ($this->imageCount > 0) : ?>
                <p class="cmp-gallery-view__meta">
                    <?php echo $this->imageCount; ?>
                    <?php echo htmlspecialchars(Text::_('COM_COPYMYPAGE_VIEW_GALLERY_IMAGES'), ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($this->warnings)) : ?>
            <?php echo LayoutHelper::render('copymypage.system.warning', ['messages' => $this->warnings]); ?>
        <?php else : ?>
            <div class="cmp-gallery-view__content">
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
