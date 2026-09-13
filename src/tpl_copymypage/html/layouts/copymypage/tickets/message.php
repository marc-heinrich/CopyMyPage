<?php
/**
 * @package     Joomla.Site
 * @subpackage  Layouts.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.20
 */

\defined('_JEXEC') or die;

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
$id         = trim((string) ($displayData['id'] ?? 'cmp-ticket-message'));
$tone       = trim((string) ($displayData['tone'] ?? 'info'));
$icon       = trim((string) ($displayData['icon'] ?? 'info'));
$role       = trim((string) ($displayData['role'] ?? 'status'));
$ariaLive   = trim((string) ($displayData['ariaLive'] ?? 'polite'));
$headingTag = strtolower(trim((string) ($displayData['headingTag'] ?? 'h2')));
$eyebrow    = trim((string) ($displayData['eyebrow'] ?? ''));
$title      = trim((string) ($displayData['title'] ?? ''));
$body       = trim((string) ($displayData['body'] ?? ''));
$details    = \is_array($displayData['details'] ?? null) ? $displayData['details'] : [];
$classes    = preg_split('/\s+/', trim((string) ($displayData['class'] ?? ''))) ?: [];

if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $id) !== 1) {
    $id = 'cmp-ticket-message';
}

if (!\in_array($tone, ['danger', 'info', 'neutral', 'success', 'warning'], true)) {
    $tone = 'info';
}

if (!\in_array($icon, ['check', 'clock', 'close', 'info', 'refresh', 'reply', 'warning'], true)) {
    $icon = 'info';
}

if (!\in_array($role, ['', 'alert', 'status'], true)) {
    $role = 'status';
}

if (!\in_array($ariaLive, ['', 'assertive', 'polite'], true)) {
    $ariaLive = 'polite';
}

if (!\in_array($headingTag, ['h1', 'h2', 'h3', 'h4'], true)) {
    $headingTag = 'h2';
}

$classes = array_values(array_filter(
    $classes,
    static fn(string $class): bool => preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $class) === 1
));
$className = trim(implode(' ', [
    'cmp-ticket-message',
    'cmp-ticket-message--' . $tone,
    ...$classes,
]));
$titleId = $id . '-title';
?>
<section
    id="<?php echo $escape($id); ?>"
    class="<?php echo $escape($className); ?>"
    <?php echo $role !== '' ? ' role="' . $escape($role) . '"' : ''; ?>
    <?php echo $ariaLive !== '' ? ' aria-live="' . $escape($ariaLive) . '"' : ''; ?>
    aria-atomic="true"
    <?php echo $title !== '' ? ' aria-labelledby="' . $escape($titleId) . '"' : ''; ?>
>
    <span
        class="cmp-ticket-message__icon"
        uk-icon="icon: <?php echo $escape($icon); ?>; ratio: 1.4"
        aria-hidden="true"
    ></span>
    <div class="cmp-ticket-message__content">
        <?php if ($eyebrow !== '') : ?>
            <p class="cmp-ticket-message__eyebrow"><?php echo $escape($eyebrow); ?></p>
        <?php endif; ?>

        <?php if ($title !== '') : ?>
            <<?php echo $headingTag; ?> id="<?php echo $escape($titleId); ?>">
                <?php echo $escape($title); ?>
            </<?php echo $headingTag; ?>>
        <?php endif; ?>

        <?php if ($body !== '') : ?>
            <p class="cmp-ticket-message__body"><?php echo $escape($body); ?></p>
        <?php endif; ?>

        <?php foreach ($details as $detail) : ?>
            <?php if (\is_array($detail)) : ?>
                <?php
                $detailTitle = trim((string) ($detail['title'] ?? ''));
                $detailBody  = trim((string) ($detail['body'] ?? ''));
                ?>
                <?php if ($detailTitle !== '' || $detailBody !== '') : ?>
                    <div class="cmp-ticket-message__detail">
                        <?php if ($detailTitle !== '') : ?>
                            <strong><?php echo $escape($detailTitle); ?></strong>
                        <?php endif; ?>
                        <?php if ($detailBody !== '') : ?>
                            <p><?php echo $escape($detailBody); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
