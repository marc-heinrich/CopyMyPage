<?php
/**
 * @package     Joomla.Site
 * @subpackage  Template.tpl_copymypage
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$code    = $this->error->getCode() ?? 0;
$message = $this->error->getMessage() ?? '';
?><!doctype html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <jdoc:include type="head" />
        <title><?php echo Text::_('TPL_COPYMYPAGE_SLOGAN'); ?></title>
    </head>

    <body class="cmp-errorPage">
        <main id="cmp-main" class="cmp-main" role="main">
            <h1><?php echo Text::_('TPL_COPYMYPAGE_SLOGAN'); ?></h1>
            <p><?php echo (int) $code; ?> — <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <jdoc:include type="message" />
        </main>
    </body>
</html>
