<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app    = Factory::getApplication();
$di     = Factory::getContainer();
$input  = $app->getInput();
$wa     = $this->getWebAssetManager();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$componentContext = $input->getCmd('cmp_context', '') === 'drawer'
    ? 'drawer'
    : 'standalone';
$bodyClass         = 'cmp-component-page'
    . ($componentContext === 'drawer' ? ' cmp-component-page--drawer' : '');
$headerOffset       = (int) $this->params->get('headerOffset', 80);
$templateTokenStyle = $di->get('copymypage.helper.templateTokens')
    ->buildRootTokenStyle($this->params, $headerOffset);

// Component documents are both a usable no-JavaScript fallback and the stable
// same-origin response format consumed by CopyMyPage content drawers.
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->useStyle('template')
   ->useScript('uikit')
   ->useScript('uikit.icons')
   ->addInlineStyle($templateTokenStyle);

$this->setHtml5(true);
$this->setMetaData('viewport', 'width=device-width, initial-scale=1.0')
     ->setMetaData('robots', 'noindex, nofollow');

if ($this->getTitle() === '') {
    $this->setTitle((string) $app->get('sitename'));
}
?>
<!DOCTYPE html>
<html lang="<?php echo $escape($this->language); ?>" dir="<?php echo $escape($this->direction); ?>">
    <head>
        <jdoc:include type="metas" />
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
    </head>
    <body
        class="<?php echo $escape($bodyClass); ?>"
        data-cmp-component-document
        data-cmp-component-context="<?php echo $escape($componentContext); ?>"
    >
        <main
            id="cmp-component-main"
            class="cmp-component-page__main"
            role="main"
            data-cmp-component-content
        >
            <jdoc:include type="message" />

            <div class="cmp-component-page__content">
                <jdoc:include type="component" />
            </div>
        </main>
    </body>
</html>
