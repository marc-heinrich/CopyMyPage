<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
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
?>
<div class="mod-copymypage-dev container my-4<?php echo $moduleclassSfx ? ' ' . $moduleclassSfx : ''; ?>">
    <div class="row g-3">
        <?php if ($hasModuleMeta || $hasParams) : ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm mod-copymypage-dev__card">
                    <div class="card-header">
                        <span class="fw-semibold">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_MODULE'); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($hasModuleMeta) : ?>
                            <h6 class="card-subtitle text-muted small text-uppercase mb-2">
                                <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_MODULE'); ?>
                            </h6>
                            <ul class="list-unstyled small mb-3">
                                <?php foreach ($debugData['module'] as $key => $value) : ?>
                                    <li class="d-flex justify-content-between">
                                        <span class="fw-semibold">
                                            <?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="text-muted ms-2 text-end">
                                            <?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if ($hasParams) : ?>
                            <?php if ($hasModuleMeta) : ?>
                                <hr class="my-2">
                            <?php endif; ?>
                            <h6 class="card-subtitle text-muted small text-uppercase mb-2">
                                <?php echo Text::_('JGLOBAL_FIELDSET_OPTIONS'); ?>
                            </h6>
                            <ul class="list-unstyled small mb-0">
                                <?php foreach ($paramsArray as $key => $value) : ?>
                                    <?php
                                        if (is_scalar($value) || $value === null) {
                                            $valueString = (string) $value;
                                        } else {
                                            $valueString = json_encode(
                                                $value,
                                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                            );
                                        }
                                    ?>
                                    <li class="d-flex justify-content-between">
                                        <span class="fw-semibold">
                                            <?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <span class="text-muted ms-2 text-end text-break">
                                            <?php echo htmlspecialchars((string) $valueString, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasMenuInfo) : ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm mod-copymypage-dev__card">
                    <div class="card-header">
                        <span class="fw-semibold">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_MENU'); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($debugData['menu'] as $key => $value) : ?>
                                <li class="d-flex justify-content-between">
                                    <span class="fw-semibold">
                                        <?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="text-muted ms-2 text-end text-break">
                                        <?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasContext) : ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 shadow-sm mod-copymypage-dev__card">
                    <div class="card-header">
                        <span class="fw-semibold">
                            <?php echo Text::_('MOD_COPYMYPAGE_DEV_HEADING_CONTEXT'); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-0">
                            <?php foreach ($debugData['context'] as $key => $value) : ?>
                                <li class="d-flex justify-content-between">
                                    <span class="fw-semibold">
                                        <?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <span class="text-muted ms-2 text-end text-break">
                                        <?php echo htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
