<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

// Retrieve Navbar parameters from the dispatcher.
$logo = $displayData['logo'];  
$stickyClass = $displayData['sticky'] ? 'uk-sticky' : '';  
$moduleClassSfx = $displayData['moduleclass_sfx'];  

// Build the final module class with the optional suffix.
$moduleClass = 'cmp-module cmp-module--navbar ' . $stickyClass;

if ($moduleClassSfx !== '') {
    $moduleClass .= ' ' . htmlspecialchars($moduleClassSfx, ENT_QUOTES, 'UTF-8');
}
?>

<nav id="navbar" class="<?php echo $moduleClass; ?>" uk-sticky="sel-target: .uk-navbar-container; cls-active: uk-navbar-sticky">
    <div class="uk-container">
        <div class="uk-navbar-left">
            <!-- Logo linked to the root of the site -->
            <a href="<?php echo Uri::root(); ?>" class="uk-navbar-item uk-logo">
                <img src="<?php echo $logo; ?>" alt="Logo" class="cmp-logo">
            </a>
        </div>
    </div>
</nav>
