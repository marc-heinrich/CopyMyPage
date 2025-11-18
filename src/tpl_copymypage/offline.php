<?php
/**
 * @package     Joomla.Site
 * @subpackage  Templates.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

// Set the page title.
$this->setTitle(Factory::getApplication()->get('sitename'));

// Register & load web assets.
$wa = $this->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_' . $this->template);
$wa->usePreset($this->template .'.site.offline');

// Favicon handling & progressive web app preparation.
$logoPath = 'com_' . $this->template . '/logo/';
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <jdoc:include type="styles" />
        <jdoc:include type="scripts" />
    </head>
    <body class="cmp-offline-page">
        <main class="cmp-offline-page-main" role="main">
            <picture class="cmp-offline-page-logo-wrapper">
                <?php echo HTMLHelper::_(
                    'image',
                    $logoPath . 'logo-cmp-text-subtitles-1.png',
                    'CopyMyPage – Your website. Just copy it.',
                    ['class' => 'cmp-offline-page-logo'],
                    true
                ); ?>
            </picture>
        </main>
    </body>
</html>
