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
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SigplusHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;
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
        $app      = Factory::getApplication();
        $id       = (int) ($id ?? $this->getState('item.id'));
        $module   = 'mod_sigplus';
        $clientId = 0;

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
            ->bind(':module', $module, ParameterType::STRING)
            ->bind(':clientId', $clientId, ParameterType::INTEGER)
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
        return $this->getSigplusHelper()->getPlugin();
    }

    /**
     * Resolves the shared Sigplus helper via the CopyMyPage registry.
     *
     * @return  SigplusHelper
     */
    private function getSigplusHelper(): SigplusHelper
    {
        $container = Factory::getContainer();
        $registry  = $container->has(CopyMyPageRegistry::class)
            ? $container->get(CopyMyPageRegistry::class)
            : new CopyMyPageRegistry();
        $handler   = $registry->getService('sigplus');

        if (\is_string($handler)) {
            $handler = new $handler();
        }

        if (!$handler instanceof SigplusHelper) {
            throw new \RuntimeException('The CopyMyPage sigplus helper is not available.');
        }

        $handler->setDatabase($this->getDatabase());

        return $handler;
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
