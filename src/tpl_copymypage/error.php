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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\ErrorDocument $this */

$app    = Factory::getApplication();
$di     = Factory::getContainer();
$wa     = $this->getWebAssetManager();
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$statusCode    = (int) ($this->error->getCode() ?? 0);
$errorDetail   = $statusCode >= 400 && $statusCode <= 499
    ? trim((string) $this->error->getMessage())
    : '';
$layoutKey     = match ($statusCode) {
    403     => '403',
    404     => '404',
    500     => '500',
    default => 'default',
};

$content = [
    '403' => [
        'icon'       => 'lock',
        'headingKey' => 'TPL_COPYMYPAGE_ERROR_403_HEADING',
        'bodyKey'    => 'TPL_COPYMYPAGE_ERROR_403_DESCRIPTION',
    ],
    '404' => [
        'icon'       => 'question',
        'headingKey' => 'TPL_COPYMYPAGE_ERROR_404_HEADING',
        'bodyKey'    => 'TPL_COPYMYPAGE_ERROR_404_DESCRIPTION',
    ],
    '500' => [
        'icon'       => 'warning',
        'headingKey' => 'TPL_COPYMYPAGE_ERROR_500_HEADING',
        'bodyKey'    => 'TPL_COPYMYPAGE_ERROR_500_DESCRIPTION',
    ],
    'default' => [
        'icon'       => 'info',
        'headingKey' => 'TPL_COPYMYPAGE_ERROR_DEFAULT_HEADING',
        'bodyKey'    => 'TPL_COPYMYPAGE_ERROR_DEFAULT_DESCRIPTION',
    ],
];

$displayData = [
    'status'      => $statusCode,
    'showStatus'  => $statusCode > 0,
    'icon'        => $content[$layoutKey]['icon'],
    'heading'     => Text::_($content[$layoutKey]['headingKey']),
    'description' => Text::_($content[$layoutKey]['bodyKey']),
    'detail'      => $errorDetail,
    'homeLabel'   => Text::_('TPL_COPYMYPAGE_ERROR_HOME'),
    'homeUrl'     => rtrim(Uri::root(true), '/') . '/index.php',
];

$headerOffset       = (int) $this->params->get('headerOffset', 80);
$templateTokenStyle = $di->get('copymypage.helper.templateTokens')->buildRootTokenStyle($this->params, $headerOffset);

// Keep the error document lean: the template stylesheet, local fonts and UIkit icons are sufficient.
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->useStyle('template')
   ->useScript('uikit.icons')
   ->addInlineStyle($templateTokenStyle);

$this->setHtml5(true);
$this->setMetaData('viewport', 'width=device-width, initial-scale=1.0')
     ->setMetaData('robots', 'noindex, nofollow');
$this->setTitle(
    $statusCode > 0
        ? Text::sprintf('TPL_COPYMYPAGE_ERROR_DOCUMENT_TITLE', $statusCode)
        : Text::_($content['default']['headingKey']) . ' – ' . $app->get('sitename')
);

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

$hasFooter = $this->countModules('footer') > 0;
?>
<!DOCTYPE html>
<html lang="<?php echo $escape($this->language); ?>" dir="<?php echo $escape($this->direction); ?>">
    <head>
        <jdoc:include type="metas" />
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
    </head>
    <body class="cmp-error-page cmp-error-page--<?php echo $escape($layoutKey); ?>">
        <main class="cmp-error-page__main" id="main-content">
            <div class="cmp-error-page__messages">
                <jdoc:include type="message" />
            </div>

            <?php echo LayoutHelper::render('copymypage.error.' . $layoutKey, $displayData); ?>

            <?php if ($this->debug) : ?>
                <section class="cmp-error-page__debug" aria-labelledby="cmp-error-debug-title">
                    <h2 id="cmp-error-debug-title"><?php echo $escape(Text::_('TPL_COPYMYPAGE_ERROR_DEBUG_HEADING')); ?></h2>
                    <?php echo $this->renderBacktrace(); ?>

                    <?php if ($this->error->getPrevious()) : ?>
                        <?php $loop = true; ?>
                        <?php $this->setError($this->_error->getPrevious()); ?>
                        <?php while ($loop === true) : ?>
                            <h3><?php echo $escape(Text::_('JERROR_LAYOUT_PREVIOUS_ERROR')); ?></h3>
                            <p><?php echo $escape($this->_error->getMessage()); ?></p>
                            <?php echo $this->renderBacktrace(); ?>
                            <?php $loop = $this->setError($this->_error->getPrevious()); ?>
                        <?php endwhile; ?>
                        <?php $this->setError($this->error); ?>
                    <?php endif; ?>

                    <jdoc:include type="modules" name="debug" style="none" />
                </section>
            <?php endif; ?>
        </main>

        <?php if ($hasFooter) : ?>
            <footer id="footer" class="cmp-footer cmp-error-page__footer" role="contentinfo">
                <div class="cmp-footer-modules">
                    <jdoc:include type="modules" name="footer" style="none" />
                </div>
            </footer>
        <?php endif; ?>
    </body>
</html>
