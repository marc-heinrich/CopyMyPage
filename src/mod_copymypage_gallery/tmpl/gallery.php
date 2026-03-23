<?php
/**
 * Base gallery layout for mod_copymypage_gallery.
 */

\defined('_JEXEC') or die;

/** @var string $helloMessage */
/** @var string $moduleclass_sfx */

$moduleClass = 'cmp-module cmp-module--gallery';

if (!empty($moduleclass_sfx)) {
    $moduleClass .= ' ' . $moduleclass_sfx;
}
?>
<div class="<?php echo $moduleClass; ?>">
    <?php echo htmlspecialchars((string) $helloMessage, ENT_QUOTES, 'UTF-8'); ?>
</div>
