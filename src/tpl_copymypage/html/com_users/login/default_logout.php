<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.18
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Users\Site\View\Login\HtmlView $this */

$app           = Factory::getApplication();
$escape        = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageTitle     = Text::_('JLOGOUT');
$pageHeading   = trim((string) $this->params->get('page_heading', ''));
$heading       = $this->params->get('show_page_heading') && $pageHeading !== ''
    ? $pageHeading
    : $pageTitle;
$siteName      = trim((string) $app->get('sitename', ''));
$documentTitle = $siteName !== ''
    ? $pageTitle . ' | ' . $siteName
    : $pageTitle;

$this->getDocument()->setTitle($documentTitle);

$showDescription = (int) $this->params->get('logoutdescription_show', 0) === 1;
$description     = (string) $this->params->get('logout_description', '');
$image           = (string) $this->params->get('logout_image', '');
$hasDescription  = ($showDescription && trim($description) !== '') || $image !== '';
$redirect        = $this->params->get('logout_redirect_url')
    ? (string) $this->params->get(
        'logout_redirect_url',
        $this->form->getValue('return', null, '')
    )
    : (string) $this->params->get(
        'logout_redirect_menuitem',
        $this->form->getValue('return', null, '')
    );
?>
<div class="cmp-auth cmp-auth--logout com-users-logout logout">
    <header class="cmp-auth__header">
        <h1 id="cmp-auth-title" class="cmp-auth__title">
            <?php echo $escape($heading); ?>
        </h1>

        <?php if ($hasDescription) : ?>
            <div class="cmp-auth__lead com-users-logout__description logout-description">
                <?php if ($showDescription) : ?>
                    <?php echo $description; ?>
                <?php endif; ?>

                <?php if ($image !== '') : ?>
                    <?php
                    echo HTMLHelper::_(
                        'image',
                        $image,
                        empty($this->params->get('logout_image_alt'))
                            && empty($this->params->get('logout_image_alt_empty'))
                            ? false
                            : $this->params->get('logout_image_alt'),
                        ['class' => 'cmp-auth__image com-users-logout__image logout-image']
                    );
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <form
        action="<?php echo Route::_('index.php?task=user.logout'); ?>"
        method="post"
        class="cmp-form cmp-auth__form com-users-logout__form form-horizontal well"
        aria-labelledby="cmp-auth-title"
    >
        <div class="cmp-form__actions cmp-auth__actions com-users-logout__submit control-group">
            <div class="controls">
                <button
                    type="submit"
                    class="btn btn-primary cmp-button cmp-button--primary w-100"
                >
                    <span class="icon-backward-2 icon-white" aria-hidden="true"></span>
                    <?php echo Text::_('JLOGOUT'); ?>
                </button>
            </div>
        </div>

        <input type="hidden" name="return" value="<?php echo $escape(base64_encode($redirect)); ?>">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
