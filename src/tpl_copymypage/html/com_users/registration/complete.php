<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Joomla\Component\Users\Site\View\Registration\HtmlView $this */

$escape      = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageHeading = trim((string) $this->params->get('page_heading', ''));
$heading     = $this->params->get('show_page_heading') && $pageHeading !== ''
    ? $pageHeading
    : Text::_('COM_USERS_REGISTRATION');
?>
<div class="cmp-auth cmp-auth--complete com-users-registration-complete registration-complete">
    <header class="cmp-auth__header">
        <h1 class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>
    </header>
</div>
