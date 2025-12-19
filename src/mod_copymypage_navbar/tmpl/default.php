<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * This is the fallback layout.
 * It is rendered when the module is published in an unsupported position.
 */

// Build an absolute URL to the Module Manager (works on any domain).
$modulesUrl = Uri::root() . 'administrator/index.php?option=com_modules&view=modules';
?>
<div class="cmp-module cmp-module--navbar cmp-module--navbar-fallback">
    <div class="uk-alert-warning" uk-alert="animation: uk-animation-scale-up; duration: 1000">
        <a class="uk-alert-close" uk-close></a>
        <p>
            <?php echo Text::sprintf('MOD_COPYMYPAGE_NAVBAR_ALERT_INVALID_POSITION', htmlspecialchars($modulesUrl, ENT_QUOTES, 'UTF-8')); ?>
        </p>
    </div>
</div>
