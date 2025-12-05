<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.2
 */

namespace Joomla\Component\CopyMyPage\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Controller\Exception;
use Joomla\Component\CopyMyPage\Site\Helper\Registry as CopyMyPageRegistry;

/**
 * Controller for displaying views in com_copymypage.
 *
 * This controller is responsible for routing incoming requests
 * to the appropriate view and layout. For the "dashboard" view it
 * integrates with the CopyMyPage helper registry to enrich the
 * primary model with additional data (for example from com_users)
 * based on the current layout.
 *
 * @since  0.0.2
 */
class DisplayController extends BaseController
{
    /**
     * Default view for the component.
     *
     * @var    string
     *
     * @since  0.0.2
     */
    protected $default_view = 'onepage';

    /**
     * Default layout for the component.
     *
     * @var    string
     *
     * @since  0.0.2
     */
    protected $default_layout = 'default';

    /**
     * Method to display a view.
     *
     * This method sets up the view and its layout, manages the
     * primary model, and for the "dashboard" view uses the helper
     * registry from the DI container to attach extra data to the
     * model's state based on the current layout.
     *
     * @param   boolean  $cachable   If true, enables caching for the view.
     * @param   array    $urlparams  Array of URL parameters to pass safely.
     *
     * @return  static   Returns this object for method chaining.
     *
     * @since   0.0.3
     *
     * @throws  Exception\ResourceNotFound  If the view or model cannot be loaded.
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Retrieve the document type and determine view and layout from the request.
        $document = $this->app->getDocument();
        $vName    = $this->input->getCmd('view', $this->default_view);
        $lName    = $this->input->getCmd('layout', $this->default_layout);
        $vFormat  = $document->getType();

        // Ensure the primary model exists.
        if (!$model = $this->getModel($vName)) {
            throw new Exception\ResourceNotFound(
                Text::sprintf('JLIB_APPLICATION_ERROR_MODELCLASS_NOT_FOUND', $vName),
                404
            );
        }

        // Ensure the view exists.
        if (!$view = $this->getView($vName, $vFormat)) {
            throw new Exception\ResourceNotFound(
                Text::sprintf('JLIB_APPLICATION_ERROR_VIEW_CLASS_NOT_FOUND', $vName),
                404
            );
        }

        // For the dashboard view, load extra data via the helper registry
        // and store it in the model state as "extra_data".
        $this->attachDashboardExtraDataFromHelper($model, $vName, $lName);

        // Associate the model with the view, set the layout, and pass the document.
        $view->setModel($model, true);
        $view->setLayout($lName);
        $view->setLanguage($this->app->getLanguage());
        $view->document = $document;

        // Render the view.
        $view->display();

        return $this;
    }

    /**
     * Attach extra data to the primary model using a helper from the DI container.
     *
     * This method only acts for the "dashboard" view. It uses the current layout
     * name as the service key in the helper registry (for example "user"), resolves
     * the corresponding helper class from the DI container, calls its getExtraData()
     * method and stores the returned data in the model's state under "extra_data".
     *
     * @param   object  $primaryModel  The primary model instance for the active view.
     * @param   string  $viewName      The current view name.
     * @param   string  $layoutName    The current layout name.
     *
     * @return  void
     *
     * @since   0.0.3
     */
    protected function attachDashboardExtraDataFromHelper(object $primaryModel, string $viewName, string $layoutName): void
    {
        // Only the "dashboard" view uses helper-based extra data.
        if ($viewName !== 'dashboard') {
            return;
        }

        // The model must support setState() to store extra data.
        if (!\method_exists($primaryModel, 'setState')) {
            return;
        }

        $container = Factory::getContainer();

        // If the helper registry is not registered in the container, bail out.
        if (!$container->has(CopyMyPageRegistry::class)) {
            return;
        }

        /** @var CopyMyPageRegistry $registry */
        $registry = $container->get(CopyMyPageRegistry::class);

        // Use the layout name as the service key, e.g. "user".
        $serviceKey = $layoutName;

        if (!$registry->hasService($serviceKey)) {
            return;
        }

        $handler = $registry->getService($serviceKey);

        // If the handler is a class name, instantiate it.
        if (\is_string($handler) && \class_exists($handler)) {
            $handler = new $handler();
        }

        // Expect a helper with a generic getExtraData() method.
        if (!\is_object($handler) || !\method_exists($handler, 'getExtraData')) {
            return;
        }

        // Let the helper load the external model (via bootComponent) and return raw data.
        $extraData = $handler->getExtraData();

        // Store the extra data and the layout name in the model state.
        $primaryModel->setState('extra_data', $extraData);
        $primaryModel->setState('form_name', $layoutName);
    }
}
