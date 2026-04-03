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
            <h1 class="cmp-gallery-view__title">
                <?php echo htmlspecialchars($this->headline, ENT_QUOTES, 'UTF-8'); ?>
            </h1>

            <hr class="cmp-gallery-view__divider uk-divider-small">

            <?php if ($this->summary !== '') : ?>
                <p class="cmp-gallery-view__meta">
                    <span class="cmp-gallery-view__meta-icon" uk-icon="icon: calendar" aria-hidden="true"></span>
                    <span class="cmp-gallery-view__meta-text">
                        <?php echo htmlspecialchars($this->summary, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </p>
            <?php endif; ?>

            <a class="cmp-gallery-view__back" href="<?php echo htmlspecialchars($this->backUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo htmlspecialchars(Text::_('COM_COPYMYPAGE_VIEW_GALLERY_BACKBUTTON'), ENT_QUOTES, 'UTF-8'); ?></span>
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
