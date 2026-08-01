<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$label  = trim((string) ($displayData['label'] ?? ''));
$url    = trim((string) ($displayData['url'] ?? ''));

if ($label === '' || $url === '') {
    return;
}
?>
<div class="cmp-dashboard-logout">
    <a class="cmp-dashboard-logout__button" href="<?php echo $escape($url); ?>">
        <?php echo $escape($label); ?>
    </a>
</div>
