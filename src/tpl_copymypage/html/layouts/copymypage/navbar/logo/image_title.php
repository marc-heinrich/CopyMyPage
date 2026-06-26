<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.15
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$escape  = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$context  = strtolower(trim((string) ($displayData['context'] ?? 'desktop')));
$isMobile = $context === 'mobile';
$logo     = rtrim(Uri::root(true), '/') . '/media/com_copymypage/images/logo/logo-cmp-1.svg';
$title    = $isMobile ? 'FBacher' : 'Fernbreitenbacher';
$subtitle = $isMobile ? 'CARNEVAL-e.V.' : 'CARNEVAL-VEREIN';
?>
<span class="cmp-navbar-logo-composite">
    <img
        class="cmp-navbar-logo"
        src="<?php echo $escape($logo); ?>"
        alt="CopyMyPage - Your website. Just copy it."
        width="221"
        height="218"
        loading="eager"
        decoding="async"
    >
    <span class="cmp-navbar-logo-title" aria-hidden="true">
        <span class="cmp-navbar-logo-title__name"><?php echo $escape($title); ?></span>
        <span class="cmp-navbar-logo-title__subtitle"><?php echo $escape($subtitle); ?></span>
    </span>
</span>
