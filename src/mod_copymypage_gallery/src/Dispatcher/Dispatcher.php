<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

namespace Joomla\Module\CopyMyPage\Gallery\Site\Dispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

/**
 * Dispatcher class for mod_copymypage_gallery.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        if ($displayData === false) {
            return;
        }

        $baseLayout    = 'gallery';
        $layoutVariant = strtolower(trim((string) ($displayData['cfg']['layoutVariant'] ?? 'default')));
        $layout        = $baseLayout;

        if (
            $layoutVariant !== ''
            && $layoutVariant !== 'default'
            && str_starts_with($layoutVariant, $baseLayout . '_')
        ) {
            $layout = $layoutVariant;
        }

        $loader = static function (array $displayData, string $layout, string $fallbackLayout): void {
            if (!\array_key_exists('displayData', $displayData)) {
                extract($displayData);
                unset($displayData);
            } else {
                extract($displayData);
            }

            /**
             * Extracted variables
             * -----------------
             * @var \stdClass                 $module
             * @var \Joomla\Registry\Registry $params
             * @var array<string, mixed>      $cfg
             * @var string                    $helloMessage
             * @var string                    $moduleclass_sfx
             */

            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_gallery', $layout);

            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_gallery', $fallbackLayout);
            }

            require $layoutPath;
        };

        $loader($displayData, $layout, $baseLayout);
    }

    /**
     * Returns the layout data.
     *
     * @return array<string, mixed>|false
     */
    protected function getLayoutData(): array|false
    {
        $data   = parent::getLayoutData();
        $helper = $this->getHelperFactory()->getHelper('GalleryHelper');

        $data['cfg'] = ($data['params'] instanceof \Joomla\Registry\Registry)
            ? $data['params']->toArray()
            : [];
        $data['helloMessage'] = $helper->getHelloMessage($data['module'], $data['params']);
        $data['moduleclass_sfx'] = htmlspecialchars(
            (string) $data['params']->get('moduleclass_sfx', ''),
            ENT_COMPAT,
            'UTF-8'
        );

        return $data;
    }
}
