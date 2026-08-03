<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

/**
 * Attach localized strings required by the CopyMyPage content drawer.
 */
final class ContentDrawerAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{
    /**
     * Register the component language and its client-side drawer strings.
     *
     * @param   Document  $doc  The document instance.
     */
    public function onAttachCallback(Document $doc): void
    {
        Factory::getApplication()->getLanguage()->load(
            'com_copymypage',
            JPATH_SITE . '/components/com_copymypage',
            null,
            true
        );

        foreach (
            [
                'COM_COPYMYPAGE_CONTENT_DRAWER_CLOSE',
                'COM_COPYMYPAGE_CONTENT_DRAWER_ERROR',
                'COM_COPYMYPAGE_CONTENT_DRAWER_GENERIC_TITLE',
                'COM_COPYMYPAGE_CONTENT_DRAWER_LOADING',
                'COM_COPYMYPAGE_CONTENT_DRAWER_OPEN_NORMALLY',
            ] as $key
        ) {
            Text::script($key);
        }
    }
}
