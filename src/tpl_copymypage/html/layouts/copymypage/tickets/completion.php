<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$state          = \is_array($displayData['state'] ?? null) ? $displayData['state'] : [];
$icon           = (string) ($state['icon'] ?? 'warning');
$integrityOk    = !empty($state['integrityOk']);
$managed        = !empty($state['managed']);
$paymentAction  = trim((string) ($displayData['paymentAction'] ?? ''));
$paymentHandoff = trim((string) ($displayData['paymentHandoff'] ?? ''));
$showBack       = !empty($displayData['showBack']);
$showRefresh    = !empty($displayData['showRefresh']);
$showResume     = !empty($displayData['showResume'])
    && $paymentAction !== ''
    && preg_match('/^[a-f0-9]{64}$/D', $paymentHandoff) === 1;
$showSteps      = !empty($displayData['showSteps']);
$tone           = (string) ($state['tone'] ?? 'danger');

if (!\in_array($tone, ['danger', 'info', 'success', 'warning'], true)) {
    $tone = 'danger';
}

if (!\in_array($icon, ['check', 'clock', 'close', 'refresh', 'reply', 'warning'], true)) {
    $icon = 'warning';
}

$details = [];

if ($managed && !$integrityOk) {
    $details[] = [
        'body'  => Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR'),
        'title' => Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_DATA_ERROR_TITLE'),
    ];
}

$announcementRole = \in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status';
$announcementLive = $announcementRole === 'alert' ? 'assertive' : 'polite';
?>
<div class="cmp-booking-completion">
    <div class="uk-container cmp-booking-completion__container">
        <?php if ($showSteps) : ?>
            <?php echo LayoutHelper::render(
                'copymypage.tickets.steps',
                [
                    'activeStep' => 5,
                    'totalSteps' => 5,
                ]
            ); ?>
        <?php endif; ?>

        <?php echo LayoutHelper::render(
            'copymypage.tickets.message',
            [
                'ariaLive'   => $announcementLive,
                'body'       => Text::_((string) ($state['introKey'] ?? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_INTRO')),
                'class'      => 'cmp-booking-completion__message',
                'details'    => $details,
                'eyebrow'    => Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_LABEL'),
                'headingTag' => 'h1',
                'icon'       => $icon,
                'id'         => 'cmp-booking-completion-status',
                'role'       => $announcementRole,
                'title'      => Text::_((string) ($state['titleKey'] ?? 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_UNKNOWN_TITLE')),
                'tone'       => $tone,
            ]
        ); ?>

        <?php if ($showBack || $showRefresh || $showResume) : ?>
            <div class="cmp-booking-completion__actions">
                <?php if ($showBack && (string) ($displayData['selectionUrl'] ?? '') !== '') : ?>
                    <a
                        class="uk-button uk-button-default cmp-button cmp-button--secondary cmp-button--back"
                        href="<?php echo $escape($displayData['selectionUrl']); ?>"
                    >
                        <span uk-icon="icon: chevron-left" aria-hidden="true"></span>
                        <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_BACK')); ?>
                    </a>
                <?php endif; ?>

                <?php if ($showRefresh || $showResume) : ?>
                    <div class="cmp-booking-completion__actions-next">
                        <?php if ($showRefresh && (string) ($displayData['refreshUrl'] ?? '') !== '') : ?>
                            <a
                                class="uk-button uk-button-default cmp-button cmp-button--secondary"
                                href="<?php echo $escape($displayData['refreshUrl']); ?>"
                            >
                                <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_REFRESH')); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($showResume) : ?>
                            <form
                                class="cmp-form cmp-booking-completion__resume-form"
                                action="<?php echo $escape($paymentAction); ?>"
                                method="post"
                            >
                                <input
                                    type="hidden"
                                    name="cmp_payment_handoff"
                                    value="<?php echo $escape($paymentHandoff); ?>"
                                >
                                <button
                                    class="uk-button uk-button-primary cmp-button cmp-button--primary"
                                    type="submit"
                                >
                                    <?php echo $escape(Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_ACTION_RESUME')); ?>
                                </button>
                                <?php echo HTMLHelper::_('form.token'); ?>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
