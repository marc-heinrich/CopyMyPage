<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$escape     = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$totalSteps = max(1, (int) ($displayData['totalSteps'] ?? 4));
$activeStep = min($totalSteps, max(1, (int) ($displayData['activeStep'] ?? 1)));
$stepMeta   = [
    1 => ['icon' => 'ticket-selection', 'label' => 'COM_COPYMYPAGE_TICKET_SELECTION_TITLE'],
    2 => ['icon' => 'seat-selection', 'label' => 'COM_COPYMYPAGE_SEAT_SELECTION_TITLE'],
    3 => ['icon' => 'customer-data', 'label' => 'COM_COPYMYPAGE_CUSTOMER_DATA_TITLE'],
    4 => ['icon' => 'order-review', 'label' => 'COM_COPYMYPAGE_ORDER_REVIEW_TITLE'],
    5 => ['icon' => 'booking-completion', 'label' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_STATUS_LABEL'],
];
?>
<nav
    class="cmp-ticket-steps dp-steps"
    role="list"
    aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_STEPS_LABEL')); ?>"
>
    <?php for ($step = 1; $step <= $totalSteps; $step++) : ?>
        <?php
        $isCurrent = $step === $activeStep;
        $meta      = $stepMeta[$step] ?? null;
        ?>
        <span
            class="dp-step<?php echo $isCurrent ? ' dp-step_active' : ''; ?>"
            role="listitem"
            <?php echo $isCurrent ? ' aria-current="step"' : ''; ?>
        >
            <span class="dp-step__number" aria-hidden="true"><?php echo $step; ?></span>
            <?php if ($meta !== null) : ?>
                <span
                    class="cmp-ticket-steps__icon cmp-ticket-steps__icon--<?php echo $escape($meta['icon']); ?>"
                    aria-hidden="true"
                ></span>
            <?php endif; ?>
            <span class="visually-hidden">
                <?php echo $escape(Text::sprintf(
                    $isCurrent
                        ? 'COM_COPYMYPAGE_TICKET_STEPS_STEP_CURRENT'
                        : 'COM_COPYMYPAGE_TICKET_STEPS_STEP',
                    $step,
                    $totalSteps
                )); ?>
                <?php if ($meta !== null) : ?>
                    <?php echo ': ' . $escape(Text::_($meta['label'])); ?>
                <?php endif; ?>
            </span>
        </span>

        <?php if ($step < $totalSteps) : ?>
            <span class="dp-steps__separator" uk-icon="icon: chevron-right" aria-hidden="true"></span>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
