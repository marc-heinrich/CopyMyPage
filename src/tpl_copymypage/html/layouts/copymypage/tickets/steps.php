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
?>
<nav
    class="cmp-ticket-steps dp-steps"
    role="list"
    aria-label="<?php echo $escape(Text::_('COM_COPYMYPAGE_TICKET_STEPS_LABEL')); ?>"
>
    <?php for ($step = 1; $step <= $totalSteps; $step++) : ?>
        <?php $isCurrent = $step === $activeStep; ?>
        <span
            class="dp-step<?php echo $isCurrent ? ' dp-step_active' : ''; ?>"
            role="listitem"
            <?php echo $isCurrent ? ' aria-current="step"' : ''; ?>
        >
            <span class="dp-step__number" aria-hidden="true"><?php echo $step; ?></span>
            <span class="visually-hidden">
                <?php echo $escape(Text::sprintf(
                    $isCurrent
                        ? 'COM_COPYMYPAGE_TICKET_STEPS_STEP_CURRENT'
                        : 'COM_COPYMYPAGE_TICKET_STEPS_STEP',
                    $step,
                    $totalSteps
                )); ?>
            </span>
        </span>

        <?php if ($step < $totalSteps) : ?>
            <span class="dp-steps__separator" uk-icon="icon: chevron-right" aria-hidden="true"></span>
        <?php endif; ?>
    <?php endfor; ?>
</nav>
