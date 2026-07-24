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
$wa     = $this->getWebAssetManager();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$headerOffset       = (int) $this->params->get('headerOffset', 80);
$templateTokenStyle = $di->get('copymypage.helper.templateTokens')
    ->buildRootTokenStyle($this->params, $headerOffset);

// Component documents are both a usable no-JavaScript fallback and the stable
// same-origin response format consumed by CopyMyPage content dialogs.
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->useStyle('template')
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
    <body class="cmp-component-page">
        <main id="main-content" class="cmp-component-page__main" role="main">
            <jdoc:include type="message" />

            <div class="cmp-component-page__content" data-cmp-component-content>
                <jdoc:include type="component" />
            </div>
        </main>
    </body>
</html>
