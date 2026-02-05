<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Module\CopyMyPage\Dev\Site\Helper\DevHelper;

/** @var \Joomla\Registry\Registry $params */
/** @var \stdClass $module */

// Prepare module class suffix.
$moduleclassSfx = htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8');

// Get debug data from helper (will be used inside the layout).
$debugData = DevHelper::getDebugData($module, $params);

// Load the layout.
require ModuleHelper::getLayoutPath('mod_copymypage_dev', $params->get('layout', 'default'));
