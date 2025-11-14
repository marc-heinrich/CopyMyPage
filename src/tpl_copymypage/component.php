<?php
/**
 * @package     Joomla.Site
 * @subpackage  Template.tpl_copymypage
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?><!doctype html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <jdoc:include type="head" />
        <title><?php echo Text::_('TPL_COPYMYPAGE_SLOGAN'); ?></title>
    </head>

    <body class="cmp-componentPage">
        <main id="cmp-main" class="cmp-main" role="main">
            <jdoc:include type="message" />
            <jdoc:include type="component" />
        </main>
    </body>
</html>
