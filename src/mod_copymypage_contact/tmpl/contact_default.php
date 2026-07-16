<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Module\CopyMyPage\Contact\Site\Helper\ContactHelper;

/**
 * Extracted variables
 * -----------------
 * @var \stdClass                                             $module
 * @var \Joomla\CMS\Application\CMSApplicationInterface       $app
 * @var string                                                $eyebrow
 * @var string                                                $headline
 * @var string                                                $lead
 * @var array<int, array<string, mixed>>                      $infoItems
 * @var string                                                $mapUrl
 * @var string                                                $mapTitle
 * @var \Joomla\CMS\Form\Form|null                            $form
 * @var bool                                                  $showCopy
 * @var string                                                $warning
 * @var \Joomla\Module\CopyMyPage\Contact\Site\Helper\ContactHelper|null $contactHelper
 */

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$eyebrow    = trim((string) ($eyebrow ?? ''));
$headline   = trim((string) ($headline ?? ''));
$lead       = trim((string) ($lead ?? ''));
$infoItems  = \is_array($infoItems ?? null) ? $infoItems : [];
$mapUrl     = trim((string) ($mapUrl ?? ''));
$mapTitle   = trim((string) ($mapTitle ?? ''));
$showCopy   = (bool) ($showCopy ?? false);
$warning    = (string) ($warning ?? '');

if (!isset($contactHelper) || !$contactHelper instanceof ContactHelper) {
    return;
}

if ($warning !== '') {
    echo $warning;

    return;
}

if (!isset($form) || !$form instanceof \Joomla\CMS\Form\Form) {
    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->useScript('copymypage.formcheck');
    $wa->useScript('copymypage.modal.content');
}

Text::script('WARNING');
Text::script('JNOTICE');
Text::script('JGLOBAL_VALIDATION_FORM_FAILED');
Text::script('JCLOSE');
Text::script('COM_COPYMYPAGE_CONTENT_MODAL_LOADING');
Text::script('COM_COPYMYPAGE_CONTENT_MODAL_ERROR');
Text::script('MOD_COPYMYPAGE_CONTACT_ERROR_MAIL_INSTANTIATE');

$moduleId   = max(0, (int) ($module->id ?? 0));
$formId     = 'cmp-contact-form-' . $moduleId;
$currentUri = clone Uri::getInstance();
$currentUri->setFragment('contact');
$returnUrl  = base64_encode($currentUri->toString());
$hasInfo    = $infoItems !== [] || $mapUrl !== '';
$formWidth  = $hasInfo ? 'cmp-contact__form-column' : 'uk-width-1-1';
$hasConsent = (bool) $form->getField('consentbox');
$hasCopy    = $showCopy && (bool) $form->getField('contact_copy');
?>
<!-- Contact Module Template: UIkit Framework (https://getuikit.com/docs/form) and CopyMyPage formcheck behavior -->
<div class="cmp-module cmp-module--contact cmp-module--contact-default">
    <div class="uk-container cmp-contact__container">
        <?php if ($eyebrow !== '' || $headline !== '' || $lead !== '') : ?>
            <header class="cmp-section-header">
                <?php if ($eyebrow !== '') : ?>
                    <p class="cmp-section-header__eyebrow">
                        <?php echo $escape($eyebrow); ?>
                    </p>
                <?php endif; ?>

                <?php if ($headline !== '') : ?>
                    <h2 class="cmp-section-header__headline">
                        <?php echo $escape($headline); ?>
                    </h2>
                <?php endif; ?>

                <?php if ($lead !== '') : ?>
                    <div class="cmp-section-header__lead">
                        <?php echo $lead; ?>
                    </div>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <div class="cmp-contact__grid uk-grid-large uk-grid-match" uk-grid>
            <?php if ($hasInfo) : ?>
                <div class="cmp-contact__info-column">
                    <aside class="cmp-contact__info">
                        <?php if ($infoItems !== []) : ?>
                            <div class="cmp-contact__info-list">
                                <?php foreach ($infoItems as $item) : ?>
                                    <?php
                                    $key    = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item['key'] ?? '')) ?: 'item';
                                    $icon   = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item['icon'] ?? 'info')) ?: 'info';
                                    $label  = trim((string) ($item['label'] ?? ''));
                                    $value  = trim((string) ($item['value'] ?? ''));
                                    $href   = trim((string) ($item['href'] ?? ''));
                                    $isHtml = (bool) ($item['isHtml'] ?? false);

                                    if ($value === '') {
                                        continue;
                                    }
                                    ?>
                                    <div class="cmp-contact__info-item cmp-contact__info-item--<?php echo $escape($key); ?>">
                                        <span class="cmp-contact__info-icon" uk-icon="icon: <?php echo $escape($icon); ?>" aria-hidden="true"></span>
                                        <div class="cmp-contact__info-content">
                                            <?php if ($label !== '') : ?>
                                                <h3 class="cmp-contact__info-label">
                                                    <?php echo $escape($label); ?>
                                                </h3>
                                            <?php endif; ?>

                                            <div class="cmp-contact__info-value">
                                                <?php if ($href !== '') : ?>
                                                    <a href="<?php echo $escape($href); ?>">
                                                        <?php echo $escape($value); ?>
                                                    </a>
                                                <?php elseif ($isHtml) : ?>
                                                    <?php echo $value; ?>
                                                <?php else : ?>
                                                    <?php echo $escape($value); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($mapUrl !== '') : ?>
                            <div class="cmp-contact__map">
                                <iframe
                                    src="<?php echo $escape($mapUrl); ?>"
                                    title="<?php echo $escape($mapTitle); ?>"
                                    width="640"
                                    height="290"
                                    loading="lazy"
                                    referrerpolicy="no-referrer-when-downgrade"
                                    allowfullscreen
                                ></iframe>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            <?php endif; ?>

            <div class="<?php echo $escape($formWidth); ?>">
                <div class="cmp-contact__form-panel">
                    <form
                        id="<?php echo $escape($formId); ?>"
                        action="<?php echo Route::_('index.php?option=com_copymypage'); ?>"
                        method="post"
                        class="cmp-contact__form form-validate"
                    >
                        <fieldset class="uk-fieldset">
                            <div class="cmp-contact__identity-grid uk-grid-small" uk-grid>
                                <div class="uk-width-1-2@m">
                                    <?php echo $form->renderField('contact_name'); ?>
                                </div>
                                <div class="uk-width-1-2@m">
                                    <?php echo $form->renderField('contact_email'); ?>
                                </div>
                            </div>

                            <?php echo $form->renderField('contact_subject'); ?>
                            <?php echo $form->renderField('contact_message'); ?>

                            <?php if ($hasConsent || $hasCopy) : ?>
                                <div class="cmp-contact__options-grid uk-grid-small" uk-grid>
                                    <?php if ($hasConsent) : ?>
                                        <div class="<?php echo $hasCopy ? 'uk-width-1-2@m' : 'uk-width-1-1'; ?>">
                                            <div class="cmp-contact__consent">
                                                <?php echo $form->renderField('consentbox'); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($hasCopy) : ?>
                                        <div class="<?php echo $hasConsent ? 'uk-width-1-2@m' : 'uk-width-1-1'; ?>">
                                            <div class="cmp-contact__copy">
                                                <div class="form-check form-check-inline">
                                                    <?php echo $form->getInput('contact_copy'); ?>
                                                    <?php echo $form->getLabel('contact_copy'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($form->getField('captcha')) : ?>
                                <?php echo $form->renderField('captcha'); ?>
                            <?php endif; ?>
                        </fieldset>

                        <input type="hidden" name="option" value="com_copymypage">
                        <input type="hidden" name="task" value="">
                        <input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
                        <input type="hidden" name="return" value="<?php echo $escape($returnUrl); ?>">
                        <?php echo HTMLHelper::_('form.token'); ?>

                        <div class="cmp-contact__actions uk-text-center">
                            <button
                                type="button"
                                class="uk-button uk-button-primary"
                                data-submit-task="contact.submit"
                                data-submit-form="#<?php echo $escape($formId); ?>"
                                data-confirm="false"
                            >
                                <?php echo Text::_('MOD_COPYMYPAGE_CONTACT_FORM_BUTTON_SUBMIT'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
