<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.4
 */

namespace Joomla\Module\CopyMyPage\Navbar\Site\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_copymypage_navbar.
 *
 * Selects the layout automatically based on the module position and a single variant field.
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Runs the dispatcher.
     *
     * @return void
     */
    public function dispatch()
    {
        $this->loadLanguage();

        $displayData = $this->getLayoutData();

        // Stop when display data is false.
        if ($displayData === false) {
            return;
        }

        $position = strtolower((string) ($displayData['module']->position ?? ''));

        // Resolve the base layout by module position.
        $baseLayout = match ($position) {
            'navbar'     => 'navbar',
            'mobilemenu' => 'mobilemenu',
            default      => 'default',
        };

        // Read a single variant value (e.g. "navbar_uikit", "mobilemenu_mmenu", "default").
        $layoutVariant = strtolower(trim((string) $displayData['params']->get('layout_variant', 'default')));

        // Build the final layout name:
        // - If base is default (unsupported position): always use "default".
        // - If variant is "default" or does not match the base prefix: fall back to base layout.
        // - If variant matches the base prefix: use variant layout as-is.
        if ($baseLayout === 'default') {
            $layout = 'default';
        } else {
            $expectedPrefix = $baseLayout . '_';

            if ($layoutVariant !== '' && $layoutVariant !== 'default' && str_starts_with($layoutVariant, $expectedPrefix)) {
                $layout = $layoutVariant;
            } else {
                $layout = $baseLayout;
            }
        }

        // Execute the layout without the module context (core pattern).
        $loader = static function (array $displayData, string $layout, string $fallbackLayout) {
            // If $displayData doesn't exist in extracted data, unset the variable.
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
             * @var \Joomla\CMS\Application\CMSApplicationInterface $app
             * @var string                    $logo
             * @var bool                      $sticky
             * @var string                    $moduleclass_sfx
             */

            // Prefer the variant layout if it exists; otherwise fall back.
            $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $layout);

            if (!is_file($layoutPath)) {
                $layoutPath = ModuleHelper::getLayoutPath('mod_copymypage_navbar', $fallbackLayout);
            }

            require $layoutPath;
        };

        $fallbackLayout = ($baseLayout === 'default') ? 'default' : $baseLayout;

        $loader($displayData, $layout, $fallbackLayout);
    }

    /**
     * Returns the layout data.
     *
     * @return array|false
     */
    protected function getLayoutData()
    {
        $data   = parent::getLayoutData();
        $helper = $this->getHelperFactory()->getHelper('NavbarHelper');

        // Use module params (stored in the DB) as the single source of truth.
        $logoImage = (string) $data['params']->get('logo_image', '');

        // If the admin did not set a logo, use the helper default.
        if ($logoImage === '') {
            $defaults  = $helper->getParams();
            $logoImage = (string) ($defaults['logo'] ?? '');
        }

        $data['logo']            = $logoImage;
        $data['sticky']          = true;
        $data['moduleclass_sfx'] = (string) $data['params']->get('moduleclass_sfx', '');

        // Always render; the default layout shows the warning/alert fallback anyway.
        return $data;
    }
}
