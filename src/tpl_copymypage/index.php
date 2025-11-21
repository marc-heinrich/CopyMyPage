<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

// Set the page title if not already set.
if (!$this->getTitle()) {
    $this->setTitle($app->get('sitename'));
}

// Register and load web assets (aligned with offline.php).
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->usePreset($this->template . '.site');

// Favicon handling & progressive web app preparation (aligned with offline.php).
$logoPath = 'com_' . $this->template . '/logo/';
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath . 'favicon.svg', '', [], true, 1),
    'icon',
    'rel',
    ['type' => 'image/svg+xml']
);
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath . 'favicon.ico', '', [], true, 1),
    'alternate icon',
    'rel',
    ['type' => 'image/vnd.microsoft.icon']
);
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath . 'apple-touch-icon.png', '', [], true, 1),
    'apple-touch-icon',
    'rel',
    ['sizes' => '180x180']
);
$this->addHeadLink(
    'media/com_' . $this->template . '/images/logo/site.webmanifest',
    'manifest',
    'rel'
);

// Detect basic context (for body classes, CSS, JS hooks).
$option    = $input->getCmd('option', '');
$view      = $input->getCmd('view', '');
$layout    = $input->getCmd('layout', '');
$task      = $input->getCmd('task', '');
$itemId    = $input->getCmd('Itemid', '');
$menu      = $app->getMenu()->getActive();
$pageClass = $menu !== null ? (string) $menu->getParams()->get('pageclass_sfx', '') : '';

// Build body classes (simplified Cassiopeia-style).
$bodyClasses = [
    'cmp-site',
    $option ?: 'no-option',
    'view-' . ($view ?: 'no-view'),
    $layout ? 'layout-' . $layout : 'no-layout',
    $task ? 'task-' . $task : 'no-task',
    $itemId ? 'itemid-' . $itemId : '',
    $pageClass,
];

if ($this->direction === 'rtl') {
    $bodyClasses[] = 'rtl';
}

$bodyClass = trim(implode(' ', array_filter($bodyClasses)));
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
    <head>
        <jdoc:include type="metas" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
    </head>
    <body class="<?php echo $bodyClass; ?>">

        <div id="page" class="cmp-page">
            <header id="top" class="cmp-header" role="banner">
                <?php if ($this->countModules('navbar')) : ?>
                    <nav class="cmp-navbar" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_NAVBAR'); ?>">
                        <jdoc:include type="modules" name="navbar" style="none" />
                    </nav>
                <?php endif; ?>

                <?php if ($this->countModules('mobilemenu')) : ?>
                    <nav class="cmp-mobilemenu" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_MOBILEMENU'); ?>">
                        <jdoc:include type="modules" name="mobilemenu" style="none" />
                    </nav>
                <?php endif; ?>
            </header>

            <main id="main-content" class="cmp-main" role="main">
                <?php if ($this->countModules('hero')) : ?>
                    <section class="cmp-section cmp-hero" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_HERO'); ?>">
                        <jdoc:include type="modules" name="hero" style="none" />
                    </section>
                <?php endif; ?>

                <?php if ($this->countModules('gallery')) : ?>
                    <section class="cmp-section cmp-gallery" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_GALLERY'); ?>">
                        <jdoc:include type="modules" name="gallery" style="none" />
                    </section>
                <?php endif; ?>

                <?php if ($this->countModules('team')) : ?>
                    <section class="cmp-section cmp-team" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_TEAM'); ?>">
                        <jdoc:include type="modules" name="team" style="none" />
                    </section>
                <?php endif; ?>

                <?php if ($this->countModules('counts')) : ?>
                    <section class="cmp-section cmp-counts" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_COUNTS'); ?>">
                        <jdoc:include type="modules" name="counts" style="none" />
                    </section>
                <?php endif; ?>

                <?php if ($this->countModules('tickets')) : ?>
                    <section class="cmp-section cmp-tickets" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_TICKETS'); ?>">
                        <jdoc:include type="modules" name="tickets" style="none" />
                    </section>
                <?php endif; ?>

                <?php if ($this->countModules('contact')) : ?>
                    <section class="cmp-section cmp-contact" role="region" aria-label="<?php echo Text::_('TPL_COPYMYPAGE_CONTACT'); ?>">
                        <jdoc:include type="modules" name="contact" style="none" />
                    </section>
                <?php endif; ?>

                <!-- Component output (for non-onepage views, articles, etc.) -->
                <jdoc:include type="message" />
                <jdoc:include type="component" />
            </main>

            <footer class="cmp-footer" role="contentinfo">
                <?php if ($this->countModules('footer')) : ?>
                    <div class="cmp-footer-modules">
                        <jdoc:include type="modules" name="footer" style="none" />
                    </div>
                <?php endif; ?>
            </footer>

            <jdoc:include type="modules" name="debug" style="none" />
        </div>
    </body>
</html>
