<?php
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$status = $this->item['status'];
$keys = [
    'ready' => 'READY', 'checked_in' => 'CHECKED_IN',
    'already_checked_in' => 'ALREADY_CHECKED_IN', 'rejected' => 'REJECTED',
    'failed' => 'FAILED', 'access_denied' => 'ACCESS_DENIED', 'invalid' => 'INVALID',
];
$statusKey = 'COM_COPYMYPAGE_TICKET_CHECKIN_' . ($keys[$status] ?? 'FAILED');
$ticket = $this->item['context']['ticket'] ?? [];
?>
<div class="cmp-ticketcheckin">
    <h1 class="cmp-ticketcheckin__title"><?php echo Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_TITLE'); ?></h1>
    <section class="cmp-ticketcheckin__surface" aria-labelledby="cmp-ticketcheckin-status">
        <div role="status" aria-live="polite" aria-atomic="true">
            <h2 id="cmp-ticketcheckin-status" class="cmp-ticketcheckin__status cmp-ticketcheckin__status--<?php echo $escape($status); ?>">
                <?php echo Text::_($statusKey); ?>
            </h2>
        </div>
        <?php if ($ticket !== []) : ?>
            <h3 class="cmp-ticketcheckin__event"><?php echo $escape($ticket['eventTitle']); ?></h3>
            <dl class="cmp-ticketcheckin__details">
                <?php foreach (['eventDate' => 'DATE', 'holderName' => 'HOLDER', 'typeLabel' => 'TYPE', 'seatLabel' => 'SEAT'] as $field => $key) : ?>
                    <?php if (trim((string) ($ticket[$field] ?? '')) !== '') : ?>
                        <div>
                            <dt><?php echo Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_' . $key); ?></dt>
                            <dd><?php echo $escape($ticket[$field]); ?></dd>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <?php if ($this->item['canCheckin'] ?? false) : ?>
            <form class="cmp-form cmp-ticketcheckin__form" method="post" action="<?php echo $escape(Route::_('index.php?option=com_copymypage&task=ticketcheckin.checkin', false)); ?>">
                <input type="hidden" name="uid" value="<?php echo $escape($this->item['uid']); ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
                <div class="cmp-form__actions">
                    <button type="submit" class="uk-button uk-button-primary cmp-button cmp-button--primary">
                        <?php echo Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_ACTION'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </section>
    <p class="cmp-ticketcheckin__next"><?php echo Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_NEXT'); ?></p>
</div>
