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

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$logo   = rtrim(Uri::root(true), '/') . '/media/com_copymypage/images/logo/logo-cmp-1.svg';
?>
<img
    class="cmp-navbar-logo"
    src="<?php echo $escape($logo); ?>"
    alt="CopyMyPage - Your website. Just copy it."
    width="221"
    height="218"
    loading="eager"
    decoding="async"
>
