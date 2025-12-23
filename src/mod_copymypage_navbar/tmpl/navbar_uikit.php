<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$moduleClass = 'cmp-module cmp-module--navbar';

if (!empty($moduleclass_sfx)) {
    $moduleClass .= ' ' . htmlspecialchars($moduleclass_sfx, ENT_QUOTES, 'UTF-8');
}

$mobileMenuTarget = 'cmp-mobilemenu';
?>

<div class="<?php echo $moduleClass; ?>">

    <div
        uk-sticky="start: 1; end: false; sel-target: .uk-navbar-container; 
        cls-active: cmp-navbar--scrolled; 
        cls-inactive: cmp-navbar--top uk-navbar-transparent uk-light; 
        animation: uk-animation-slide-top"
    >
        <div class="uk-navbar-container cmp-navbar-container">
            <div class="uk-container">
                <div class="uk-navbar" uk-navbar>

                    <!-- LEFT: Desktop = Logo, Mobile = Menu toggle -->
                    <div class="uk-navbar-left">
                        <!-- Mobile toggle -->
                        <button
                            class="uk-navbar-toggle uk-hidden@m cmp-navbar-toggle"
                            type="button"
                            aria-label="Open menu"
                            data-cmp-mobilemenu-toggle="#<?php echo $mobileMenuTarget; ?>"
                        >
                            <span uk-navbar-toggle-icon></span>
                        </button>

                        <!-- Desktop logo -->
                        <a
                            class="uk-navbar-item uk-logo uk-visible@m cmp-navbar-logo-link"
                            href="<?php echo Uri::root(); ?>"
                        >
                            <img
                                class="cmp-navbar-logo"
                                src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="CopyMyPage – Your website. Just copy it."
                                width="140"
                                height="32"
                                loading="eager"
                                decoding="async"
                            >
                        </a>
                    </div>

                    <!-- CENTER: Desktop = Nav items, Mobile = Logo -->
                    <div class="uk-navbar-center">
                        <!-- Mobile centered logo -->
                        <a
                            class="uk-navbar-item uk-logo uk-hidden@m cmp-navbar-logo-link"
                            href="<?php echo Uri::root(); ?>"
                        >
                            <img
                                class="cmp-navbar-logo"
                                src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="CopyMyPage – Your website. Just copy it."
                                width="140"
                                height="32"
                                loading="eager"
                                decoding="async"
                            >
                        </a>

                        <!-- Desktop navigation (placeholder for now) -->
                        <ul class="uk-navbar-nav uk-visible@m cmp-navbar-nav">
                            <li><a href="#">Home</a></li>
                            <li>
                                <a href="#">Features</a>
                                <div class="uk-navbar-dropdown">
                                    <ul class="uk-nav uk-navbar-dropdown-nav">
                                        <li><a href="#">Subitem 1</a></li>
                                        <li><a href="#">Subitem 2</a></li>
                                    </ul>
                                </div>
                            </li>
                            <li><a href="#">Contact</a></li>
                        </ul>
                    </div>

                    <!-- RIGHT: Desktop = Icons, Mobile = User icon -->
                    <div class="uk-navbar-right">
                        <!-- Mobile: only user icon -->
                        <a class="uk-navbar-item uk-hidden@m cmp-navbar-icon-link" href="#" aria-label="User">
                            <span uk-icon="user"></span>
                        </a>

                        <!-- Desktop: icon group -->
                        <ul class="uk-iconnav uk-visible@m cmp-navbar-icons">
                            <li><a href="#" aria-label="User" uk-icon="user"></a></li>
                            <li><a href="#" aria-label="Search" uk-icon="search"></a></li>
                            <li><a href="#" aria-label="Basket" uk-icon="cart"></a></li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
