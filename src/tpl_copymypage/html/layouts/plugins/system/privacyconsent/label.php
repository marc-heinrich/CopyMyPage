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
use Joomla\CMS\Router\Route;

extract($displayData);

/**
 * Layout variables
 * -----------------
 * @var   string   $autocomplete           Autocomplete attribute for the field.
 * @var   boolean  $autofocus              Is autofocus enabled?
 * @var   string   $class                  Classes for the input.
 * @var   boolean  $disabled               Is this field disabled?
 * @var   string   $group                  Group the field belongs to. <fields> section in form XML.
 * @var   boolean  $hidden                 Is this field hidden in the form?
 * @var   string   $hint                   Placeholder for the field.
 * @var   string   $id                     DOM id of the field.
 * @var   string   $label                  Label of the field.
 * @var   string   $labelclass             Classes to apply to the label.
 * @var   boolean  $multiple               Does this field support multiple values?
 * @var   string   $name                   Name of the input field.
 * @var   string   $onchange               Onchange attribute for the field.
 * @var   string   $onclick                Onclick attribute for the field.
 * @var   string   $pattern                Pattern (Reg Ex) of value of the form field.
 * @var   boolean  $readonly               Is this field read only?
 * @var   boolean  $repeat                 Allows extensions to duplicate elements.
 * @var   boolean  $required               Is this field required?
 * @var   integer  $size                   Size attribute of the input.
 * @var   boolean  $spellcheck             Spellcheck state for the form field.
 * @var   string   $validate               Validation rules to apply.
 * @var   string   $value                  Value attribute of the field.
 * @var   array    $options                Options available for this field.
 * @var   array    $privacynote            The privacy note that needs to be displayed
 * @var   array    $translateLabel         Should the label be translated?
 * @var   array    $translateHint          Should the hint be translated?
 * @var   array    $privacyArticle         The Article ID holding the Privacy Article.
 * @var   object   $article                The Article object.
 * @var   object   $privacyLink            Link to the privacy article or menu item.
 */

// Get the label text from the XML element, defaulting to the element name.
$text = $label ? (string) $label : (string) $name;
$text = $translateLabel ? Text::_($text) : $text;

// Build the class for the label.
$class = 'required';
$class = !empty($labelclass) ? $class . ' ' . $labelclass : $class;

if ($privacyLink) {
    $app      = Factory::getApplication();
    $language = $app->getLanguage();
    $wa       = $app->getDocument()->getWebAssetManager();

    // Registration is rendered by com_users, so load the shared CopyMyPage
    // language and asset registry before the outer template is processed.
    $language->load(
        'com_copymypage',
        JPATH_SITE . '/components/com_copymypage',
        null,
        true
    );

    $wa->getRegistry()->addExtensionRegistryFile('com_copymypage');
    $wa->useScript('copymypage.modal.content');

    Text::script('JCLOSE');
    Text::script('COM_COPYMYPAGE_CONTENT_MODAL_ERROR');
    Text::script('COM_COPYMYPAGE_CONTENT_MODAL_LOADING');

    $attribs = [
        'aria-haspopup'                => 'dialog',
        'class'                        => $class,
        'data-cmp-content-modal'       => 'privacy',
        'data-cmp-content-modal-title' => $text,
    ];

    // Keep the ordinary article URL as progressive fallback. JavaScript adds
    // tmpl=component only to the background request used by the dialog.
    $link = HTMLHelper::_(
        'link',
        Route::_((string) $privacyLink),
        $text,
        $attribs
    );
} else {
    $link = '<span class="' . $class . '">' . $text . '</span>';
}

// Add the label text and star.
$label = $link . '<span class="star" aria-hidden="true">&#160;*</span>';

echo $label;
