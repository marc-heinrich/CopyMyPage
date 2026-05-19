<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.3
 */

namespace Joomla\Plugin\System\CopyMyPage\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\PreloaderHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SigplusHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\TemplateTokenHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\UserHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\Event\SubscriberInterface;

/**
 * System plugin for CopyMyPage.
 *
 * Registers shared CopyMyPage helper services in the root DI container
 * so they can be resolved across extensions (components, modules, templates).
 *
 * @since  0.0.3
 */
final class CopyMyPage extends CMSPlugin implements SubscriberInterface
{
    /**
     * Automatically load the plugin language files (ini + sys.ini).
     *
     * @var bool
     *
     * @since 0.0.4
     */
    protected $autoloadLanguage = true;

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array<string, string>
     *
     * @since   0.0.3
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise'    => 'onAfterInitialise',
            'onContentPrepareForm' => 'onContentPrepareForm',
        ];
    }

    /**
     * Handler for the onAfterInitialise event.
     *
     * Note:
     * We intentionally use Factory::getContainer() here because CMSApplication::getContainer()
     * is not publicly accessible from extensions in Joomla 6.
     *
     * @return  void
     *
     * @since   0.0.4
     */
    public function onAfterInitialise(): void
    {
        $container = Factory::getContainer();

        $this->registerHelperServices($container);
    }

    /**
     * Register CopyMyPage helper services in the root DI container.
     *
     * @param   Container  $container  The root DI container.
     *
     * @return  void
     *
     * @since   0.0.14
     */
    private function registerHelperServices(Container $container): void
    {
        if (!$container->has(SigplusHelper::class)) {
            $container->share(
                SigplusHelper::class,
                static function (Container $container): SigplusHelper {
                    $helper = new SigplusHelper();
                    $helper->setDatabase($container->get(DatabaseInterface::class));

                    return $helper;
                },
                true
            );
        }

        if (!$container->has(TemplateTokenHelper::class)) {
            $container->share(
                TemplateTokenHelper::class,
                static fn(Container $container): TemplateTokenHelper => new TemplateTokenHelper(),
                true
            );
        }

        if (!$container->has(PreloaderHelper::class)) {
            $container->share(
                PreloaderHelper::class,
                static fn(Container $container): PreloaderHelper => new PreloaderHelper(),
                true
            );
        }

        if (!$container->has(UserHelper::class)) {
            $container->share(
                UserHelper::class,
                static fn(Container $container): UserHelper => new UserHelper(),
                true
            );
        }

        $container->alias('copymypage.helper.sigplus', SigplusHelper::class);
        $container->alias('copymypage.helper.preloader', PreloaderHelper::class);
        $container->alias('copymypage.helper.templateTokens', TemplateTokenHelper::class);
        $container->alias('copymypage.helper.user', UserHelper::class);
    }

    /**
     * Add CopyMyPage app settings to the contact edit form.
     *
     * @param   Model\PrepareFormEvent  $event  The form preparation event.
     *
     * @return  bool
     *
     * @since   0.0.14
     */
    public function onContentPrepareForm(Model\PrepareFormEvent $event): bool
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return true;
        }

        $form = $event->getForm();

        if ($form->getName() !== 'com_contact.contact') {
            return true;
        }

        $this->loadLanguage();

        $form->loadFile(JPATH_PLUGINS . '/system/copymypage/forms/contact.xml', false);

        return true;
    }
}
