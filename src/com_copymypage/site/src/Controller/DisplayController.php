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
use Joomla\Component\CopyMyPage\Administrator\Service\ModelRegistry;

/**
 * Controller for displaying views in com_copymypage.
 *
 * This controller is responsible for routing incoming requests
 * to the appropriate view and layout. It also integrates with
 * the ModelRegistry to enrich the primary model with additional
 * data from foreign models (for example com_users) based on the
 * current view and layout.
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
     * primary model, and uses the ModelRegistry to allow handlers
     * to enrich the model with additional data based on the current
     * view and layout.
     *
     * @param   boolean  $cachable   If true, enables caching for the view.
     * @param   array    $urlparams  Array of URL parameters to pass safely.
     *
     * @return  static   Returns this object for method chaining.
     *
     * @since   0.0.2
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

        // Enrich the primary model using the ModelRegistry, if a handler is registered.
        $this->enrichModelWithRegistryHandler($model, $vName, $lName);

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
     * Enrich the primary model using a registered handler from the ModelRegistry.
     *
     * The handler is looked up by a key composed of the current view and layout
     * (for example "dashboard.profile") and, if present, will receive the
     * primary model and the current user.
     *
     * @param   object  $primaryModel  The primary model instance for the active view.
     * @param   string  $viewName      The current view name.
     * @param   string  $layoutName    The current layout name.
     *
     * @return  void
     *
     * @since   0.0.2
     */
    protected function enrichModelWithRegistryHandler($primaryModel, string $viewName, string $layoutName): void
    {
        // Get the ModelRegistry from the DI container.
        $container = Factory::getContainer();

        if (!$container->has(ModelRegistry::class)) {
            return;
        }

        // Look up the handler by the "view.layout" key.
        $registry = $container->get(ModelRegistry::class);
        $key      = $viewName . '.' . $layoutName;
        $user     = $this->app->getIdentity();

        if (!$registry->hasHandler($key)) {
            return;
        }

        $handler = $registry->getHandler($key);

        // Invoke the handler to enrich the primary model.
        if (\is_callable($handler)) {
            $handler($primaryModel, $user);
        }
    }
}
