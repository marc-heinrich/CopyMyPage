<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  WebAssetItem
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 * @see         https://docs.joomla.org/J4.x:Web_Assets and CoreAssetItem.php
 */

namespace Joomla\CMS\WebAsset\AssetItem;

\defined('_JEXEC') or die;

use Joomla\CMS\Document\Document;
use Joomla\CMS\WebAsset\WebAssetAttachBehaviorInterface;
use Joomla\CMS\WebAsset\WebAssetItem;

/**
 * Web Asset Item class for CopyMyPage.
 *
 * @since 0.0.1
 */
final class CopyMyPageAssetItem extends WebAssetItem implements WebAssetAttachBehaviorInterface
{    
    public function onAttachCallback(Document $doc): void
    {
        // todo: will be filled later if needed.
    }
}
