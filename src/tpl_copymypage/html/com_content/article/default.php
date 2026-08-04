<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

/** @var \Joomla\Component\Content\Site\View\Article\HtmlView $this */
$coreLayout = JPATH_SITE . '/components/com_content/tmpl/article/default.php';
?>
<div class="cmp-article">
    <?php require $coreLayout; ?>
</div>
