<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app = Factory::getApplication();

// Send a proper 503 status for maintenance/offline pages.
if (!headers_sent()) {
    $app->setHeader('Status', '503 Service Unavailable', true);
    $app->setHeader('Retry-After', '3600', true);
    http_response_code(503);
}

// Basic document setup.
$this->setHtml5(true);

// Ensure that view-specific keywords do not leak into the offline page.
$head = $this->getHeadData();

if (isset($head['metaTags']['name']['keywords'])) {
    unset($head['metaTags']['name']['keywords']);
}

$this->setHeadData($head);

// Title + meta description for SEO / Lighthouse.
$this->setTitle(Text::_('TPL_COPYMYPAGE_OFFLINE_META_TITLE'));
$this->setMetaData('robots', 'noindex, nofollow')
     ->setMetaData('viewport', 'width=device-width, initial-scale=1.0, shrink-to-fit=no')
     ->setMetaData('description', Text::_('TPL_COPYMYPAGE_OFFLINE_META_DESCRIPTION'));

// Logo + favicon paths.
// Adjust to the actual image size if necessary.
$logoPath   = 'com_' . $this->template . '/logo/';
$logoWidth  = 600;  
$logoHeight = 600; 

// Favicons & PWA assets.
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath .'favicon.svg', '', [], true, 1),
    'icon',
    'rel',
    ['type' => 'image/svg+xml']
);
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath .'favicon.ico', '', [], true, 1),
    'alternate icon',
    'rel',
    ['type' => 'image/vnd.microsoft.icon']
);
$this->addHeadLink(
    HTMLHelper::_('image', $logoPath .'apple-touch-icon.png', '', [], true, 1),
    'apple-touch-icon',
    'rel',
    ['sizes' => '180x180']
);
$this->addHeadLink(
    'media/com_' . $this->template . '/images/logo/site.webmanifest',
    'manifest',
    'rel'
);
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
    <head>
        <jdoc:include type="metas" />
        <style>
            :root {
                --cmp-color-background-default: #fff;
                --cmp-color-background-default-rgb: 255, 255, 255;
                --cmp-color-text-default: #061225;
                --cmp-color-text-default-rgb: 6, 18, 37;
            }
            * {
                box-sizing: border-box;
            }
            html,
            body {
                margin: 0;
                padding: 0;
                height: 100%;
            }
            body.cmp-offline-page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: var(--cmp-color-background-default);
                color: var(--cmp-color-text-default);
            }
            .cmp-offline-page-main {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .cmp-offline-page-logo-wrapper {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                max-width: 90vw;
            }
            .cmp-offline-page-logo {
                display: block;
                max-width: 100%;
                height: auto;
            }
        </style>
    </head>
    <body class="cmp-offline-page">
        <main class="cmp-offline-page-main" role="main">
            <picture class="cmp-offline-page-logo-wrapper">
                <?php echo HTMLHelper::_(
                    'image',
                    $logoPath . 'logo-cmp-text-subtitles-1.png',
                    'CopyMyPage – Your website. Just copy it.',
                    [
                        'class'    => 'cmp-offline-page-logo',
                        'width'    => (string) $logoWidth,
                        'height'   => (string) $logoHeight,
                        'loading'  => 'eager',
                        'decoding' => 'async',
                    ],
                    true
                ); ?>
            </picture>
        </main>
    </body>
</html>
