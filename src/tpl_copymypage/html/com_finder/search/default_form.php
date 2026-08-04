<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Finder\Site\View\Search\HtmlView $this */
if ($this->params->get('show_autosuggest', 1)) {
    $this->getDocument()->getWebAssetManager()->usePreset('awesomplete');
    $this->getDocument()->addScriptOptions(
        'finder-search',
        ['url' => Route::_('index.php?option=com_finder&task=suggestions.suggest&format=json&tmpl=component', false)]
    );

    Text::script('COM_FINDER_SEARCH_FORM_LIST_LABEL');
    Text::script('JLIB_JS_AJAX_ERROR_OTHER');
    Text::script('JLIB_JS_AJAX_ERROR_PARSE');
}

$advancedClass = 'com-finder__advanced js-finder-advanced cmp-finder__advanced collapse';
?>
<form
    action="<?php echo Route::_($this->query->toUri()); ?>"
    method="get"
    class="cmp-form cmp-finder__form js-finder-searchform"
>
    <?php echo $this->getFields(); ?>

    <fieldset class="com-finder__search word mb-3 cmp-finder__query-fieldset">
        <legend class="com-finder__search-legend visually-hidden">
            <?php echo Text::_('COM_FINDER_SEARCH_FORM_LEGEND'); ?>
        </legend>

        <div class="form-inline cmp-finder__query-layout">
            <label for="q" class="control-label me-2">
                <?php echo Text::_('COM_FINDER_SEARCH_TERMS'); ?>
            </label>

            <div class="input-group cmp-finder__query-group">
                <input
                    type="text"
                    name="q"
                    id="q"
                    class="js-finder-search-query form-control"
                    value="<?php echo $this->escape($this->query->input); ?>"
                >

                <div class="cmp-form__actions cmp-finder__query-actions">
                    <button type="submit" class="btn btn-primary cmp-button cmp-button--primary">
                        <span class="icon-search icon-white" aria-hidden="true"></span>
                        <?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>
                    </button>

                    <?php if ($this->params->get('show_advanced', 1)) : ?>
                        <?php HTMLHelper::_('bootstrap.collapse'); ?>
                        <button
                            class="btn btn-secondary cmp-button cmp-button--secondary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#advancedSearch"
                            aria-expanded="false"
                            aria-controls="advancedSearch"
                        >
                            <span class="icon-search-plus" aria-hidden="true"></span>
                            <?php echo Text::_('COM_FINDER_ADVANCED_SEARCH_TOGGLE'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </fieldset>

    <?php if ($this->params->get('show_advanced', 1)) : ?>
        <fieldset
            id="advancedSearch"
            class="<?php echo $this->escape($advancedClass); ?>"
        >
            <legend class="com-finder__search-advanced visually-hidden">
                <?php echo Text::_('COM_FINDER_SEARCH_ADVANCED_LEGEND'); ?>
            </legend>

            <div class="cmp-finder__advanced-panel">
                <?php if ($this->params->get('show_advanced_tips', 1)) : ?>
                    <div class="com-finder__tips card card-outline-secondary mb-3 cmp-finder__tips">
                        <div class="card-body">
                            <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_INTRO'); ?>
                            <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_AND'); ?>
                            <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_NOT'); ?>
                            <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_OR'); ?>
                            <?php if ($this->params->get('tuplecount', 1) > 1) : ?>
                                <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_PHRASE'); ?>
                            <?php endif; ?>
                            <?php echo Text::_('COM_FINDER_ADVANCED_TIPS_OUTRO'); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="finder-filter-window" class="com-finder__filter cmp-finder__filter">
                    <?php echo HTMLHelper::_('filter.select', $this->query, $this->params); ?>
                </div>
            </div>
        </fieldset>
    <?php endif; ?>
</form>
