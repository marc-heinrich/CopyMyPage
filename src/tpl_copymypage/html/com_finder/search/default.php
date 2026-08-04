<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

/** @var \Joomla\Component\Finder\Site\View\Search\HtmlView $this */
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
$wa
    ->useStyle('com_finder.finder')
    ->useScript('com_finder.finder')
    ->useScript('copymypage.finder.autosubmit');
?>
<div class="com-finder finder cmp-finder">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <header class="cmp-finder__header">
            <h1 class="cmp-finder__title">
                <?php if ($this->escape($this->params->get('page_heading'))) : ?>
                    <?php echo $this->escape($this->params->get('page_heading')); ?>
                <?php else : ?>
                    <?php echo $this->escape($this->params->get('page_title')); ?>
                <?php endif; ?>
            </h1>
        </header>
    <?php endif; ?>

    <div id="search-form" class="com-finder__form cmp-finder__search-panel">
        <?php echo $this->loadTemplate('form'); ?>
    </div>

    <?php // Load the search results layout if we are performing a search. ?>
    <?php if ($this->query->search === true) : ?>
        <div id="search-results" class="com-finder__results cmp-finder__results">
            <?php echo $this->loadTemplate('results'); ?>
        </div>
    <?php endif; ?>
</div>
