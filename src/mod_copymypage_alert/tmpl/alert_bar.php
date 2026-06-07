<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Module\CopyMyPage\Alert\Site\Helper\AlertHelper;

/**
 * Extracted variables
 * -----------------
 * @var \stdClass                                      $module
 * @var \Joomla\CMS\Application\CMSApplicationInterface $app
 * @var array<string, mixed>                           $cfg
 * @var object|null                                    $notice
 * @var string                                         $warning
 * @var string                                         $hint
 * @var \Joomla\Module\CopyMyPage\Alert\Site\Helper\AlertHelper|null $alertHelper
 */

// Closure for escaping output.
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$cfg     = \is_array($cfg ?? null) ? $cfg : [];
$layout  = strtolower(trim((string) ($layout ?? '')));
$warning = (string) ($warning ?? '');
$hint    = (string) ($hint ?? '');
$notice  = \is_object($notice ?? null) ? $notice : null;

if (!isset($alertHelper) || !$alertHelper instanceof AlertHelper) {
    return;
}

if ($warning !== '') {
    echo $warning;

    return;
}

if (!$notice) {
    echo $hint;

    return;
}

$style           = strtolower(trim((string) ($notice->style ?? 'warning')));
$colorMode       = strtolower(trim((string) ($notice->colorMode ?? 'preset')));
$backgroundColor = trim((string) ($notice->backgroundColor ?? ''));
$textColor       = trim((string) ($notice->textColor ?? ''));
$label           = trim((string) ($notice->label ?? ''));
$message         = trim((string) ($notice->message ?? ''));
$ctaLabel        = trim((string) ($notice->ctaLabel ?? ''));
$ctaUrl          = trim((string) ($notice->ctaUrl ?? ''));
$ctaTarget       = trim((string) ($notice->ctaTarget ?? '_self'));
$displayMode     = strtolower(trim((string) ($notice->displayMode ?? 'static')));
$tickerDuration  = (int) ($notice->tickerDuration ?? 28);
$showClose       = (bool) ($notice->showClose ?? true);
$dismissBehavior = strtolower(trim((string) ($notice->dismissBehavior ?? 'session')));
$dismissKey      = trim((string) ($notice->dismissKey ?? ''));
$cookieDays      = (int) ($notice->cookieDays ?? 7);

if ($message === '') {
    echo $hint;

    return;
}

if (isset($app) && $app instanceof \Joomla\CMS\Application\CMSApplicationInterface) {
    /** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->useScript('copymypage.alertbar');
}

$uikitClass = match ($style) {
    'primary', 'success', 'warning', 'danger' => 'uk-alert-' . $style,
    'maintenance' => 'uk-alert-primary',
    default => '',
};

$moduleClass = trim('cmp-module cmp-module--alert cmp-module--alert-bar');
$barClasses  = array_filter([
    'cmp-alert-bar',
    'cmp-alert-bar--' . ($style !== '' ? $style : 'default'),
    $colorMode === 'custom' ? 'cmp-alert-bar--custom' : '',
    $displayMode === 'ticker' ? 'cmp-alert-bar--ticker' : 'cmp-alert-bar--static',
    $uikitClass,
]);
$target = \in_array($ctaTarget, ['_self', '_blank'], true) ? $ctaTarget : '_self';
$rel    = $target === '_blank' ? 'noopener noreferrer' : '';
$role   = \in_array($style, ['warning', 'danger', 'maintenance'], true) ? 'alert' : 'status';
$ariaLive = $role === 'alert' ? 'assertive' : 'polite';

$inlineStyles = [];

if ($displayMode === 'ticker') {
    $inlineStyles[] = '--cmp-alert-ticker-duration: ' . max(8, min(120, $tickerDuration)) . 's;';
}

if ($colorMode === 'custom') {
    if ($backgroundColor !== '') {
        $inlineStyles[] = '--cmp-alert-background: ' . $backgroundColor . ';';
    }

    if ($textColor !== '') {
        $inlineStyles[] = '--cmp-alert-color: ' . $textColor . ';';
    }
}

$styleAttribute = $inlineStyles !== []
    ? ' style="' . $escape(implode(' ', $inlineStyles)) . '"'
    : '';
?>
<section class="<?php echo $escape($moduleClass); ?>">
    <div
        class="<?php echo $escape(implode(' ', $barClasses)); ?>"
        uk-alert="animation: true; duration: 150"
        role="<?php echo $escape($role); ?>"
        aria-live="<?php echo $escape($ariaLive); ?>"
        data-cmp-alert-bar
        data-cmp-alert-key="<?php echo $escape($dismissKey); ?>"
        data-cmp-alert-dismiss="<?php echo $escape($dismissBehavior); ?>"
        data-cmp-alert-cookie-days="<?php echo max(1, min(365, $cookieDays)); ?>"
        <?php echo $styleAttribute; ?>
    >
        <?php if ($showClose) : ?>
            <button
                class="uk-alert-close cmp-alert-bar__close"
                type="button"
                uk-close
                aria-label="<?php echo $escape(Text::_('MOD_COPYMYPAGE_ALERT_CLOSE_LABEL')); ?>"
            ></button>
        <?php endif; ?>

        <div class="uk-container cmp-alert-bar__container">
            <div class="cmp-alert-bar__inner">
                <?php if ($label !== '') : ?>
                    <span class="cmp-alert-bar__label">
                        <?php echo $escape($label); ?>
                    </span>
                <?php endif; ?>

                <?php if ($displayMode === 'ticker') : ?>
                    <div class="cmp-alert-bar__viewport" tabindex="0">
                        <div class="cmp-alert-bar__ticker">
                            <span class="cmp-alert-bar__message"><?php echo $message; ?></span>
                            <span class="cmp-alert-bar__message cmp-alert-bar__message--clone" aria-hidden="true"><?php echo $message; ?></span>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="cmp-alert-bar__message">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($ctaUrl !== '' && $ctaLabel !== '') : ?>
                    <a
                        class="cmp-alert-bar__action uk-button uk-button-default uk-button-small"
                        href="<?php echo $escape($ctaUrl); ?>"
                        target="<?php echo $escape($target); ?>"
                        <?php if ($rel !== '') : ?>
                            rel="<?php echo $escape($rel); ?>"
                        <?php endif; ?>
                    >
                        <?php echo $escape($ctaLabel); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
