<?php

/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** @var \Joomla\Component\Users\Site\View\Registration\HtmlView $this */

$app = Factory::getApplication();
$app->getLanguage()->load('tpl_copymypage', JPATH_SITE, null, true);

$escape        = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageTitle     = Text::_('COM_USERS_REGISTRATION');
$pageHeading   = trim((string) $this->params->get('page_heading', ''));
$heading       = $this->params->get('show_page_heading') && $pageHeading !== ''
    ? $pageHeading
    : $pageTitle;
$siteName      = trim((string) $app->get('sitename', ''));
$documentTitle = $siteName !== ''
    ? $pageTitle . ' | ' . $siteName
    : $pageTitle;

$this->getDocument()->setTitle($documentTitle);
?>
<div class="cmp-auth cmp-auth--complete com-users-registration-complete registration-complete">
    <header class="cmp-auth__header">
        <h1 class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>
        <div class="cmp-auth__lead">
            <p><?php echo $escape(Text::_('TPL_COPYMYPAGE_REGISTRATION_COMPLETE_CLOSE')); ?></p>
        </div>
    </header>
</div>
