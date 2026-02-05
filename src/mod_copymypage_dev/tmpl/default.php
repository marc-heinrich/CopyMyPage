<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/** @var array<string, mixed> $debugData */
/** @var string $moduleclassSfx */
/** @var Registry $params */

$showModuleInfo = (bool) $params->get('show_module_info', 1);
$showMenuInfo   = (bool) $params->get('show_menu_info', 1);

// Collect module params as array for display.
$paramsArray = $params->toArray();

$hasModuleMeta = $showModuleInfo && !empty($debugData['module']);
$hasMenuInfo   = $showMenuInfo && !empty($debugData['menu']);
$hasContext    = !empty($debugData['context']);
$hasParams     = !empty($paramsArray);

/**
 * Render a simple key/value list (UIkit).
 *
 * @param iterable<string, mixed> $items
 */
$renderKvList = static function (iterable $items, bool $encodeComplex = false): void {
    ?>
    <ul class="uk-list uk-list-divider uk-text-small uk-margin-remove">
        <?php foreach ($items as $key => $value) : ?>
            <?php
                if ($value === null) {
                    $valueString = 'null';
                } elseif (\is_bool($value)) {
                    $valueString = $value ? 'true' : 'false';
                } elseif (\is_scalar($value)) {
                    $valueString = (string) $value;
                } else {
                    if ($encodeComplex) {
                        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $valueString = ($encoded !== false) ? $encoded : trim((string) print_r($value, true));
                    } else {
                        $valueString = trim((string) print_r($value, true));
                    }
                }
            ?>
            <li class="uk-flex uk-flex-between uk-flex-top">
                <span class="uk-text-bold">
                    <?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="uk-text-muted uk-text-right uk-text-break uk-margin-small-left">
                    <?php echo htmlspecialchars($valueString, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};

$rootSfx = $moduleclassSfx !== ''
    ? ' ' . htmlspecialchars($moduleclassSfx, ENT_QUOTES, 'UTF-8')
    : '';
?>
<div class="cmp-module cmp-module--dev uk-container uk-margin-medium-top uk-margin-medium-bottom<?php echo $rootSfx; ?>">
    <div
        class="uk-grid-small uk-grid-match uk-child-width-1-1 uk-child-width-1-2@m uk-child-width-1-3@xl"
        uk-grid
    >
        <?php if ($hasModuleMeta || $hasParams) : ?>
            <div>
                <div class="uk-card uk-card-default uk-card-small">
                    <div class="uk-card-header">
                        <h3 class="uk-card-title uk-margin-remove">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_MODULE'); ?>
                        </h3>
                    </div>

                    <div class="uk-card-body">
                        <?php if ($hasModuleMeta) : ?>
                            <?php $renderKvList($debugData['module']); ?>                            
                        <?php endif; ?>

                        <?php if ($hasParams) : ?>
                            <?php if ($hasModuleMeta) : ?>
                                <hr class="uk-margin-small">
                            <?php endif; ?>

                            <h4 class="uk-text-small uk-text-uppercase uk-text-muted uk-margin-remove-bottom">
                                <?php echo Text::_('JOPTIONS'); ?>
                            </h4>
                            <div class="uk-margin-small-top">
                                <?php $renderKvList($paramsArray, true); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasMenuInfo) : ?>
            <div>
                <div class="uk-card uk-card-default uk-card-small">
                    <div class="uk-card-header">
                        <h3 class="uk-card-title uk-margin-remove">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_MENU'); ?>
                        </h3>
                    </div>

                    <div class="uk-card-body">
                        <?php $renderKvList($debugData['menu']); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasContext) : ?>
            <div>
                <div class="uk-card uk-card-default uk-card-small">
                    <div class="uk-card-header">
                        <h3 class="uk-card-title uk-margin-remove">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_CONTEXT'); ?>
                        </h3>
                    </div>

                    <div class="uk-card-body">
                        <?php $renderKvList($debugData['context']); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
