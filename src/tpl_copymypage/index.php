<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.5
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app     = Factory::getApplication();
$input   = $app->getInput();
$wa      = $this->getWebAssetManager();
$preload = $this->getPreloadManager();
$root    = Uri::root(true);

// Build path variables.
$fontPath = $root . '/media/com_' . $this->template . '/css/fonts-local/';
$logoPath = 'com_' . $this->template . '/logo/';

// Preload custom font.
$preload->preload($fontPath . 'mona-sans/mona-sans-v4-latin-regular.woff2', [
    'as'          => 'font',
    'type'        => 'font/woff2',
    'crossorigin' => 'anonymous',
]);

// Add favicons and app manifest.
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

// Ensure HTML5 output mode for the document.
$this->setHtml5(true);

// Title fallback: only if no view/menu has set a title.
if ($this->getTitle() === '') {
    $this->setTitle($app->get('sitename'));
}

// Global, non-changeable meta tags.
$this->setMetaData('viewport', 'width=device-width, initial-scale=1.0, shrink-to-fit=no')
    ->setMetaData('robots', 'index, follow');

// Detect basic context (for body classes, CSS, JS hooks).
$option = $input->getCmd('option', '');
$view   = $input->getCmd('view', '');
$layout = $input->getCmd('layout', '');
$task   = $input->getCmd('task', '');
$itemId = (int) $input->getInt('Itemid', 0);

$menu      = $app->getMenu()->getActive();
$isOnepage = CopyMyPageHelper::isOnepage($option, $view);

// Template params (DB) -> convert simple selector (".class"/"#id") to token ("class"/"id").
$pageWrapperClass = CopyMyPageHelper::selectorToToken((string) $this->params->get('pageWrapperSelector'));
$navbarClass      = CopyMyPageHelper::selectorToToken((string) $this->params->get('navbarSelector'));
$mobileMenuClass  = CopyMyPageHelper::selectorToToken((string) $this->params->get('mobileMenuSelector'));
$backToTopID      = CopyMyPageHelper::selectorToToken((string) $this->params->get('backToTopSelector'));
$mainContentID    = CopyMyPageHelper::selectorToToken((string) $this->params->get('backToTopTargetSelector'));
$headerOffset     = (int) $this->params->get('headerOffset', 80);

// Register and load web assets (aligned with offline.php).
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->usePreset($this->template . '.site')
   ->addInlineStyle(":root {\n"
        . "    /* copymypage tokens */\n"
        . "    --cmp-header-offset: {$headerOffset}px;\n"
        . "}");

// Build body classes and navbar attributes.
$bodyClasses = [
    'cmp-site',
    $option ?: 'no-option',
    'view-' . ($view ?: 'no-view'),
    $layout ? 'layout-' . $layout : 'no-layout',
    $task ? 'task-' . $task : 'no-task',
    $itemId ? 'itemid-' . $itemId : '',
    $isOnepage ? 'is-onepage' : 'no-onepage',
];

$navbarAttrs = [
    $isOnepage
        ? 'uk-scrollspy-nav="closest: li; target: a[data-cmp-scroll=\'1\']; scroll: false; offset: ' . (int) $headerOffset . '"'
        : '',
];

$bodyClass  = trim(implode(' ', array_filter($bodyClasses)));
$navbarAttr = trim(implode(' ', array_filter($navbarAttrs)));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($this->language, ENT_QUOTES, 'UTF-8'); ?>" dir="<?php echo htmlspecialchars($this->direction, ENT_QUOTES, 'UTF-8'); ?>">
    <head>
        <jdoc:include type="metas" />
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
    </head>
    <body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">

        <!-- Page Wrapper -->
        <div id="page" class="<?php echo htmlspecialchars($pageWrapperClass, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Header -->
            <header id="top" class="cmp-header" role="banner">
                <?php if ($this->countModules('navbar')) : ?>
                    <!-- Module Navbar -->
                    <nav
                        id="navbar"
                        class="<?php echo htmlspecialchars($navbarClass, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_NAVBAR'), ENT_QUOTES, 'UTF-8'); ?>"
                        <?php echo $navbarAttr; ?>
                    >
                        <jdoc:include type="modules" name="navbar" style="none" />
                    </nav>
                <?php endif; ?>

                <?php if ($this->countModules('mobilemenu')) : ?>
                    <!-- Module Mobile Menu -->
                    <nav
                        id="mobilemenu"
                        class="<?php echo htmlspecialchars($mobileMenuClass, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_MOBILEMENU'), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <jdoc:include type="modules" name="mobilemenu" style="none" />
                    </nav>
                <?php endif; ?>
            </header>

            <!-- Main Content -->
            <main id="<?php echo htmlspecialchars($mainContentID, ENT_QUOTES, 'UTF-8'); ?>" class="cmp-main" role="main">

                <?php if ($isOnepage) : ?>

                    <?php if ($this->countModules('hero')) : ?>
                        <!-- Module Hero -->
                        <section id="hero" class="cmp-section cmp-section--hero" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_HERO'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="hero" style="none" />
                        </section>
                    <?php endif; ?>

                    <?php if ($this->countModules('gallery')) : ?>
                        <!-- Module Gallery -->
                        <section id="gallery" class="cmp-section cmp-section--gallery" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_GALLERY'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="gallery" style="none" />
                        </section>
                    <?php endif; ?>

                    <?php if ($this->countModules('team')) : ?>
                        <!-- Module Team -->
                        <section id="team" class="cmp-section cmp-section--team" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_TEAM'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="team" style="none" />
                        </section>
                    <?php endif; ?>

                    <?php if ($this->countModules('counts')) : ?>
                        <!-- Module Counts -->
                        <section id="counts" class="cmp-section cmp-section--counts" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_COUNTS'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="counts" style="none" />
                        </section>
                    <?php endif; ?>

                    <?php if ($this->countModules('tickets')) : ?>
                        <!-- Module Tickets -->
                        <section id="tickets" class="cmp-section cmp-section--tickets" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_TICKETS'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="tickets" style="none" />
                        </section>
                    <?php endif; ?>

                    <?php if ($this->countModules('contact')) : ?>
                        <!-- Module Contact -->
                        <section id="contact" class="cmp-section cmp-section--contact" role="region" aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_MODULE_CONTACT'), ENT_QUOTES, 'UTF-8'); ?>">
                            <jdoc:include type="modules" name="contact" style="none" />
                        </section>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- System Messages -->
                <jdoc:include type="message" />                
                
                <!-- Component Output -->
                <section id="component" class="cmp-section cmp-section--component">
                    <jdoc:include type="component" />
                </section>              

            </main>

            <!-- Footer -->
            <footer id="footer" class="cmp-footer" role="contentinfo">
                <?php if ($this->countModules('footer')) : ?>
                    <!-- Module Footer -->
                    <div class="cmp-footer-modules">
                        <jdoc:include type="modules" name="footer" style="none" />
                    </div>
                <?php endif; ?>
            </footer>

            <!-- Back to top button -->
            <a
                href="#<?php echo htmlspecialchars($mainContentID, ENT_QUOTES, 'UTF-8'); ?>"
                id="<?php echo htmlspecialchars($backToTopID, ENT_QUOTES, 'UTF-8'); ?>"
                class="cmp-back-to-top"
                aria-label="<?php echo htmlspecialchars(Text::_('TPL_COPYMYPAGE_BUTTON_BACKTOTOP'), ENT_QUOTES, 'UTF-8'); ?>"
            >
                <span class="uk-icon" uk-icon="chevron-up" aria-hidden="true"></span>
            </a>

            <!-- Debug area if active -->
            <jdoc:include type="modules" name="debug" style="none" />

        </div>
    </body>
</html>
