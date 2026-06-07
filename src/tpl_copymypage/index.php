<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.14
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Helper\CopyMyPageHelper;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app    = Factory::getApplication();
$di     = Factory::getContainer();
$input  = $app->getInput();
$wa     = $this->getWebAssetManager();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Build path variables.
$logoPath = 'com_' . $this->template . '/logo/';

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

// Provide global Open Graph defaults unless a view has already defined them.
$siteName = trim((string) $app->get('sitename'));
$locale   = str_replace('-', '_', $app->getLanguage()->getTag());

CopyMyPageHelper::addMetaPropertyIfMissing($this, 'og:type', 'website');

if ($siteName !== '') {
    CopyMyPageHelper::addMetaPropertyIfMissing($this, 'og:site_name', $siteName);
}

if ($locale !== '') {
    CopyMyPageHelper::addMetaPropertyIfMissing($this, 'og:locale', $locale);
}

// Detect basic context (for body classes, CSS, JS hooks).
$option = $input->getCmd('option', '');
$view   = $input->getCmd('view', '');
$layout = $input->getCmd('layout', '');
$task   = $input->getCmd('task', '');
$itemId = (int) $input->getInt('Itemid', 0);

// Onepage context is determined by view or menu item; it enables scrollspy-nav and section tracking.
$isOnepage = CopyMyPageHelper::isOnepage($option, $view);

// Template params (DB): store raw HTML class/id tokens; build selectors only where needed.
$pageWrapperClass   = (string) $this->params->get('pageWrapperClass', 'cmp-page');
$navbarClass        = (string) $this->params->get('navbarClass', 'cmp-navbar');
$mobileMenuClass    = (string) $this->params->get('mobileMenuClass', 'cmp-mobilemenu');
$backToTopID        = (string) $this->params->get('backToTopId', 'back-top');
$mainContentID      = (string) $this->params->get('mainContentId', 'main-content');
$headerOffset       = (int) $this->params->get('headerOffset', 80);
$templateTokenStyle = $di->get('copymypage.helper.templateTokens')
    ->buildRootTokenStyle($this->params, $headerOffset);

// Preloader config (DB): get all values at once since we'll need them all in the template.
$preloaderConfig  = $di->get('copymypage.helper.preloader')
    ->getConfig($this->params, $this->template);
$preloaderEnabled = $preloaderConfig['enabled'];
$preloaderID      = $preloaderConfig['id'];
$preloaderType    = $preloaderConfig['type'];
$preloaderText    = $preloaderConfig['text'];
$preloaderLogoUrl = $preloaderConfig['logoUrl'];
$hasAlertModules  = $this->countModules('alert') > 0;

// Register and load web assets (aligned with offline.php).
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->usePreset($this->template . '.site')
   ->addInlineStyle($templateTokenStyle);

// Onepage script adds scrollspy-nav behavior and active section tracking.
if ($isOnepage) {
    $wa->useScript('copymypage.onepage');
}

// Enable modal dev harness if in debug mode or URL param is set.
if ((\defined('JDEBUG') && JDEBUG) || (int) $input->getInt('cmpdev', 0) === 1) {
    $wa->useScript('copymypage.modal.dev');
}

// Build body classes and navbar attributes.
$bodyClasses = [
    'cmp-site',
    $preloaderEnabled ? 'is-preloader-active' : '',
    $option ?: 'no-option',
    'view-' . ($view ?: 'no-view'),
    $layout ? 'layout-' . $layout : 'no-layout',
    $task ? 'task-' . $task : 'no-task',
    $itemId ? 'itemid-' . $itemId : '',
    $isOnepage ? 'is-onepage' : 'no-onepage',
    $hasAlertModules ? 'is-alert-active' : '',
    // ViewportFeature adds current viewport classes such as is-mobile or is-desktop.
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
<html lang="<?php echo $escape($this->language); ?>" dir="<?php echo $escape($this->direction); ?>">
    <head>
        <jdoc:include type="metas" />
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
        <?php if ($preloaderEnabled) : ?>
            <noscript>
                <style>
                    body.is-preloader-active {
                        overflow: auto !important;
                    }

                    #<?php echo $escape($preloaderID); ?> {
                        display: none !important;
                    }
                </style>
            </noscript>
        <?php endif; ?>
    </head>
    <body class="<?php echo $escape($bodyClass); ?>">

        <?php if ($preloaderEnabled) : ?>
            <!-- Preloader -->
            <div
                id="<?php echo $escape($preloaderID); ?>"
                class="cmp-preloader cmp-preloader--<?php echo $escape($preloaderType); ?>"
                data-cmp-preloader-type="<?php echo $escape($preloaderType); ?>"
                aria-hidden="true"
            >
                <div class="cmp-preloader__content">
                    <?php switch ($preloaderType) :
                        case 'ring': ?>
                            <span class="cmp-preloader__ring"></span>
                            <?php break; ?>

                        <?php case 'bars': ?>
                            <div class="cmp-preloader__bars">
                                <span class="cmp-preloader__bar"></span>
                                <span class="cmp-preloader__bar"></span>
                                <span class="cmp-preloader__bar"></span>
                                <span class="cmp-preloader__bar"></span>
                            </div>
                            <?php break; ?>

                        <?php case 'pulse': ?>
                            <div class="cmp-preloader__pulse">
                                <span class="cmp-preloader__pulse-halo"></span>
                                <span class="cmp-preloader__pulse-core"></span>
                            </div>
                            <?php break; ?>

                        <?php case 'logo': ?>
                            <div class="cmp-preloader__logo-wrap">
                                <img
                                    class="cmp-preloader__logo"
                                    src="<?php echo $escape($preloaderLogoUrl); ?>"
                                    alt=""
                                    loading="eager"
                                    decoding="async"
                                >
                            </div>
                            <?php break; ?>

                        <?php case 'dots':
                        default: ?>
                            <div class="cmp-preloader__dots">
                                <span class="cmp-preloader__dot"></span>
                                <span class="cmp-preloader__dot"></span>
                                <span class="cmp-preloader__dot"></span>
                                <span class="cmp-preloader__dot"></span>
                            </div>
                    <?php endswitch; ?>

                    <?php if ($preloaderText !== '') : ?>
                        <p class="cmp-preloader__text"><?php echo $escape($preloaderText); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Page Wrapper -->
        <div id="page" class="<?php echo $escape($pageWrapperClass); ?>">

            <?php if ($hasAlertModules) : ?>
                <!-- Module Alert -->
                <div
                    id="alert"
                    class="cmp-alert-slot"
                    role="region"
                    aria-label="<?php echo $escape(Text::_('TPL_COPYMYPAGE_MODULE_ALERT')); ?>"
                >
                    <jdoc:include type="modules" name="alert" style="none" />
                </div>
            <?php endif; ?>

            <!-- Header -->
            <header id="top" class="cmp-header" role="banner">
                <?php if ($this->countModules('navbar')) : ?>
                    <!-- Module Navbar -->
                    <nav
                        id="navbar"
                        class="<?php echo $escape($navbarClass); ?>"
                        aria-label="<?php echo $escape(Text::_('TPL_COPYMYPAGE_MODULE_NAVBAR')); ?>"
                        <?php echo $navbarAttr; ?>
                    >
                        <jdoc:include type="modules" name="navbar" style="none" />
                    </nav>
                <?php endif; ?>

                <?php if ($this->countModules('mobilemenu')) : ?>
                    <!-- Module Mobile Menu -->
                    <nav
                        id="mobilemenu"
                        class="<?php echo $escape($mobileMenuClass); ?>"
                        aria-label="<?php echo $escape(Text::_('TPL_COPYMYPAGE_MODULE_MOBILEMENU')); ?>"
                    >
                        <jdoc:include type="modules" name="mobilemenu" style="none" />
                    </nav>
                <?php endif; ?>
            </header>

            <!-- Main Content -->
            <main id="<?php echo $escape($mainContentID); ?>" class="cmp-main" role="main">

                <?php if ($isOnepage) : ?>

                    <?php foreach (CopyMyPageHelper::getOnepageSections() as $sectionSlot => $section) : ?>
                        <?php if ($this->countModules($sectionSlot)) : ?>
                            <?php
                            $sectionLabel = (string) ($section['label'] ?? '');
                            $sectionTitle = $sectionLabel !== '' ? Text::_($sectionLabel) : ucfirst($sectionSlot);
                            ?>
                            <!-- Module <?php echo $escape(ucfirst($sectionSlot)); ?> -->
                            <section id="<?php echo $escape($sectionSlot); ?>" class="cmp-section cmp-section--<?php echo $escape($sectionSlot); ?>" role="region" aria-label="<?php echo $escape($sectionTitle); ?>">
                                <jdoc:include type="modules" name="<?php echo $escape($sectionSlot); ?>" style="none" />
                            </section>
                        <?php endif; ?>
                    <?php endforeach; ?>

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
                href="#<?php echo $escape($mainContentID); ?>"
                id="<?php echo $escape($backToTopID); ?>"
                class="cmp-back-to-top"
                aria-label="<?php echo $escape(Text::_('TPL_COPYMYPAGE_BUTTON_BACKTOTOP')); ?>"
            >
                <span class="uk-icon" uk-icon="chevron-up" aria-hidden="true"></span>
            </a>

            <!-- Debug area if active -->
            <jdoc:include type="modules" name="debug" style="none" />

        </div>
    </body>
</html>
