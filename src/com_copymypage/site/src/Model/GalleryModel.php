<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.9
 */

namespace Joomla\Component\CopyMyPage\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Gallery model for the CopyMyPage component.
 */
class GalleryModel extends BaseDatabaseModel
{
    /**
     * Loads one published Sigplus site module by id.
     *
     * @param   int|null  $id  Optional module id override.
     *
     * @return  object|null
     */
    public function getItem($id = null): ?object
    {
        $app = Factory::getApplication();
        $id  = (int) ($id ?? $this->getState('item.id'));

        if ($id <= 0) {
            return null;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select(
                [
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('params'),
                ]
            )
            ->from($db->quoteName('#__modules'))
            ->where(
                [
                    $db->quoteName('published') . ' = 1',
                    $db->quoteName('module') . ' = :module',
                    $db->quoteName('client_id') . ' = :clientId',
                    $db->quoteName('id') . ' = :id',
                ]
            )
            ->bind(':module', 'mod_sigplus', ParameterType::STRING)
            ->bind(':clientId', 0, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        try {
            $item = $db->setQuery($query)->loadObject();

            return \is_object($item) ? $item : null;
        } catch (\RuntimeException $e) {
            $app->enqueueMessage($e->getMessage(), 'error');

            return null;
        }
    }

    /**
     * Loads the Sigplus content plugin row from #__extensions.
     *
     * @return  object|null
     */
    public function getSigplusPlugin(): ?object
    {
        $db = $this->getDatabase();

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
            ->bind(':folder', 'content', ParameterType::STRING)
            ->bind(':element', 'sigplus', ParameterType::STRING)
            ->bind(':type', 'plugin', ParameterType::STRING);

        $plugin = $db->setQuery($query)->loadObject();

        return \is_object($plugin) ? $plugin : null;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     */
    protected function populateState(): void
    {
        $app    = Factory::getApplication();
        $params = $app->getParams('com_copymypage');

        $this->setState('params', $params);
        $this->setState('item.id', $app->getInput()->getInt('id'));
        $this->setState('item.count', $app->getInput()->getInt('imageCount'));
    }
}
