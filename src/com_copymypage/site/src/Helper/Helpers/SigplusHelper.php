<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

namespace Joomla\Component\CopyMyPage\Site\Helper\Helpers;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

/**
 * Shared Sigplus helper for CopyMyPage module and component usage.
 */
final class SigplusHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * Loads the Sigplus content plugin row from #__extensions.
     *
     * @return  object|null
     */
    public function getPlugin(): ?object
    {
        $db      = $this->getDatabase();
        $folder  = 'content';
        $element = 'sigplus';
        $type    = 'plugin';

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('extension_id', 'id'),
                    $db->quoteName('enabled'),
                ]
            )
            ->from($db->quoteName('#__extensions'))
            ->where(
                [
                    $db->quoteName('folder') . ' = :folder',
                    $db->quoteName('element') . ' = :element',
                    $db->quoteName('type') . ' = :type',
                ]
            )
            ->bind(':folder', $folder, ParameterType::STRING)
            ->bind(':element', $element, ParameterType::STRING)
            ->bind(':type', $type, ParameterType::STRING);

        $plugin = $db->setQuery($query)->loadObject();

        return \is_object($plugin) ? $plugin : null;
    }

    /**
     * Checks whether the Sigplus content plugin exists and is enabled.
     *
     * @param   object|null  $plugin  Optional plugin row override.
     *
     * @return  bool
     */
    public function isAvailable(?object $plugin = null): bool
    {
        $plugin ??= $this->getPlugin();

        return $plugin !== null && (int) ($plugin->enabled ?? 0) === 1;
    }
}
