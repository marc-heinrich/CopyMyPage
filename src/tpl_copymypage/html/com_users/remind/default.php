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
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Remind\HtmlView $this */

$app = Factory::getApplication();

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
   ->useScript('form.validate');

$escape        = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageTitle     = Text::_('COM_USERS_REMIND');
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
<div class="cmp-auth cmp-auth--remind com-users-remind remind">
    <header class="cmp-auth__header">
        <h1 id="cmp-auth-title" class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>
    </header>

    <form
        action="<?php echo Route::_('index.php?task=remind.remind'); ?>"
        method="post"
        id="user-remind"
        class="cmp-form cmp-auth__form com-users-remind__form form-validate"
        aria-labelledby="cmp-auth-title"
    >
        <?php foreach ($this->form->getFieldsets() as $fieldset) : ?>
            <fieldset class="cmp-auth__fieldset">
                <?php if (isset($fieldset->label)) : ?>
                    <legend class="cmp-auth__fieldset-intro"><?php echo Text::_($fieldset->label); ?></legend>
                <?php endif; ?>
                <?php echo $this->form->renderFieldset($fieldset->name); ?>
            </fieldset>
        <?php endforeach; ?>

        <div class="cmp-form__actions cmp-auth__actions com-users-remind__submit control-group">
            <div class="controls">
                <button type="submit" class="uk-button uk-button-primary validate">
                    <?php echo Text::_('JSUBMIT'); ?>
                </button>
            </div>
        </div>

        <?php echo $this->form->renderControlFields(); ?>
    </form>
</div>
