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

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\ImageHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\PreloaderHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\ProfileHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SecurityHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\SigplusHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\TemplateTokenHelper;
use Joomla\Component\CopyMyPage\Site\Helper\Helpers\UserHelper;
use Joomla\Component\CopyMyPage\Site\Repository\ProfileAddressRepository;
use Joomla\Component\CopyMyPage\Site\Service\AccountMenuProvider;
use Joomla\Component\CopyMyPage\Site\Service\AddressCatalogService;
use Joomla\Component\CopyMyPage\Site\Service\AvatarService;
use Joomla\Component\CopyMyPage\Site\Service\ContactClaimService;
use Joomla\Component\CopyMyPage\Site\Service\CountryCodeResolver;
use Joomla\Component\CopyMyPage\Site\Service\ProfileAddressService;
use Joomla\Component\CopyMyPage\Site\Service\UserFormProjectionService;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

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
     * Original request values while the core privacy plugin sees com_users.
     *
     * @var    array<string, mixed>
     * @since  0.0.17
     */
    private array $profileRequestContext = [];

    /**
     * Ensure compatibility listeners are registered only once per request.
     *
     * @var    bool
     * @since  0.0.17
     */
    private bool $profileRouteListenersRegistered = false;

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
        $this->configurePasswordResetRoute();
        $this->registerProfileRouteCompatibility();
    }

    /**
     * Point Joomla's mandatory password-reset gate at the dedicated password form.
     *
     * @return  void
     *
     * @since   0.0.17
     */
    private function configurePasswordResetRoute(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $app->set('site_reset_password_override', 1);
        $app->set('site_reset_password_option', 'com_copymypage');
        $app->set('site_reset_password_view', 'dashboard');
        $app->set('site_reset_password_layout', 'security.edit');
        $app->set(
            'site_reset_password_urls',
            [
                ['option' => 'com_copymypage', 'task' => 'security.save'],
                ['option' => 'com_users', 'task' => 'user.logout'],
                ['option' => 'com_users', 'task' => 'user.menulogout'],
                ['option' => 'com_users', 'task' => 'captive.validate'],
                ['option' => 'com_users', 'view' => 'captive'],
                ['option' => 'com_users', 'view' => 'methods'],
                ['option' => 'com_users', 'view' => 'method'],
                ['option' => 'com_users', 'task' => 'method.add'],
                ['option' => 'com_users', 'task' => 'method.save'],
            ]
        );
    }

    /**
     * Wrap the core privacy-consent listener in a temporary com_users request context.
     *
     * @return  void
     *
     * @since   0.0.17
     */
    private function registerProfileRouteCompatibility(): void
    {
        if ($this->profileRouteListenersRegistered || !$this->getApplication()->isClient('site')) {
            return;
        }

        $dispatcher = $this->getDispatcher();

        $dispatcher->addListener(
            'onAfterRoute',
            [$this, 'beginProfileRouteCompatibility'],
            Priority::ABOVE_NORMAL
        );
        $dispatcher->addListener(
            'onAfterRoute',
            [$this, 'restoreProfileRouteCompatibility'],
            Priority::BELOW_NORMAL
        );

        $this->profileRouteListenersRegistered = true;
    }

    /**
     * Keep users without consent inside the dashboard and let the core plugin validate its form.
     *
     * @param   AfterRouteEvent  $event  The after-route event.
     *
     * @return  void
     *
     * @since   0.0.17
     */
    public function beginProfileRouteCompatibility(AfterRouteEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSWebApplicationInterface) {
            return;
        }

        $input  = $app->getInput();
        $userId = (int) $app->getIdentity()->id;

        if (
            $userId === 0
            || $input->getCmd('option', '') !== 'com_copymypage'
            || !$this->userNeedsPrivacyConsent($userId)
        ) {
            return;
        }

        if (
            !$this->isDashboardProfileConsentRequest()
            && !$this->userRequiresPasswordReset()
        ) {
            $app->enqueueMessage($this->getPrivacyConsentRedirectMessage(), 'notice');
            $app->redirect(
                Factory::getContainer()
                    ->get(AccountMenuProvider::class)
                    ->getDashboardUrl($app, 'profile.edit')
            );

            return;
        }

        $this->profileRequestContext = [
            'layout' => $input->get('layout', null, 'raw'),
            'option' => $input->get('option', null, 'raw'),
            'view'   => $input->get('view', null, 'raw'),
        ];

        $input->set('option', 'com_users');
        $input->set('view', 'profile');
        $input->set('layout', 'edit');
    }

    /**
     * Restore the CopyMyPage route after normal-priority system plugins have run.
     *
     * @param   AfterRouteEvent  $event  The after-route event.
     *
     * @return  void
     *
     * @since   0.0.17
     */
    public function restoreProfileRouteCompatibility(AfterRouteEvent $event): void
    {
        if ($this->profileRequestContext === []) {
            return;
        }

        $input = $this->getApplication()->getInput();

        foreach ($this->profileRequestContext as $key => $value) {
            $input->set($key, $value);
        }

        $this->profileRequestContext = [];
    }

    /**
     * Check whether the current CopyMyPage request may collect the missing consent.
     *
     * @return  bool
     *
     * @since   0.0.17
     */
    private function isDashboardProfileConsentRequest(): bool
    {
        $input = $this->getApplication()->getInput();
        $task  = $input->getCmd('task', '');

        if ($task === 'profile.save') {
            return true;
        }

        return $input->getCmd('view', '') === 'dashboard'
            && $input->getCmd('layout', '') === 'profile.edit';
    }

    /**
     * Let a forced password reset finish before collecting a missing consent.
     *
     * @return  bool
     *
     * @since   0.0.17
     */
    private function userRequiresPasswordReset(): bool
    {
        return (bool) $this->getApplication()->getIdentity()->requireReset;
    }

    /**
     * Mirror the core plugin's persisted-consent check without creating consent records.
     *
     * @param   int  $userId  The Joomla user ID.
     *
     * @return  bool
     *
     * @since   0.0.17
     */
    private function userNeedsPrivacyConsent(int $userId): bool
    {
        if (!PluginHelper::isEnabled('system', 'privacyconsent')) {
            return false;
        }

        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $subject = 'PLG_SYSTEM_PRIVACYCONSENT_SUBJECT';
        $query   = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__privacy_consents'))
            ->where($db->quoteName('user_id') . ' = :userId')
            ->where($db->quoteName('subject') . ' = :subject')
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':subject', $subject);

        return (int) $db->setQuery($query)->loadResult() === 0;
    }

    /**
     * Reuse the configured core privacy redirect message.
     *
     * @return  string
     *
     * @since   0.0.17
     */
    private function getPrivacyConsentRedirectMessage(): string
    {
        $plugin  = PluginHelper::getPlugin('system', 'privacyconsent');
        $params  = new Registry((string) ($plugin->params ?? ''));
        $message = trim((string) $params->get('messageOnRedirect', ''));

        if ($message !== '') {
            return $message;
        }

        $this->getApplication()->getLanguage()->load(
            'plg_system_privacyconsent',
            JPATH_ADMINISTRATOR,
            null,
            true
        );

        return Text::_('PLG_SYSTEM_PRIVACYCONSENT_REDIRECT_MESSAGE_DEFAULT');
    }

    /**
     * Register CopyMyPage helper services in the root DI container.
     *
     * @param   Container  $container  The root DI container.
     *
     * @return  void
     *
     * @since   0.0.15
     */
    private function registerHelperServices(Container $container): void
    {
        $app = $this->getApplication();

        if (!$container->has(ImageHelper::class)) {
            $container->share(
                ImageHelper::class,
                static fn(Container $container): ImageHelper => new ImageHelper(),
                true
            );
        }

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

        if ($app instanceof CMSWebApplicationInterface) {
            if (!$container->has(ProfileAddressRepository::class)) {
                $container->share(
                    ProfileAddressRepository::class,
                    static fn(Container $container): ProfileAddressRepository
                        => new ProfileAddressRepository($container->get(DatabaseInterface::class)),
                    true
                );
            }

            if (!$container->has(CountryCodeResolver::class)) {
                $container->share(
                    CountryCodeResolver::class,
                    static fn(Container $container): CountryCodeResolver
                        => new CountryCodeResolver(
                            $app->getLanguage() ?? (string) $app->get('language', 'en-GB')
                        ),
                    true
                );
            }

            if (!$container->has(AddressCatalogService::class)) {
                $container->share(
                    AddressCatalogService::class,
                    static fn(Container $container): AddressCatalogService
                        => new AddressCatalogService(
                            $app->getLanguage() ?? (string) $app->get('language', 'en-GB'),
                            $container->get(CountryCodeResolver::class)
                        ),
                    true
                );
            }

            if (!$container->has(ContactClaimService::class)) {
                $container->share(
                    ContactClaimService::class,
                    static fn(Container $container): ContactClaimService => new ContactClaimService(
                        $app,
                        $container->get(DatabaseInterface::class),
                        $container->get(ProfileAddressRepository::class),
                        $container->get(AddressCatalogService::class)
                    ),
                    true
                );
            }

            if (!$container->has(UserFormProjectionService::class)) {
                $container->share(
                    UserFormProjectionService::class,
                    static fn(Container $container): UserFormProjectionService
                        => new UserFormProjectionService($app),
                    true
                );
            }

            if (!$container->has(AvatarService::class)) {
                $container->share(
                    AvatarService::class,
                    static fn(Container $container): AvatarService => new AvatarService(
                        $app,
                        $container->get(DatabaseInterface::class),
                        $container->get(FormFactoryInterface::class)
                    ),
                    true
                );
            }

            if (!$container->has(ProfileAddressService::class)) {
                $container->share(
                    ProfileAddressService::class,
                    static fn(Container $container): ProfileAddressService => new ProfileAddressService(
                        $app,
                        $container->get(DatabaseInterface::class),
                        $container->get(FormFactoryInterface::class),
                        $container->get(ProfileAddressRepository::class),
                        $container->get(AddressCatalogService::class)
                    ),
                    true
                );
            }

            if (!$container->has(UserHelper::class)) {
                $container->share(
                    UserHelper::class,
                    static fn(Container $container): UserHelper => new UserHelper(
                        $container->get(UserFormProjectionService::class)
                    ),
                    true
                );
            }

            if (!$container->has(ProfileHelper::class)) {
                $container->share(
                    ProfileHelper::class,
                    static fn(Container $container): ProfileHelper => new ProfileHelper(
                        $container->get(UserFormProjectionService::class),
                        $container->get(ProfileAddressService::class),
                        $container->get(AvatarService::class)
                    ),
                    true
                );
            }

            if (!$container->has(SecurityHelper::class)) {
                $container->share(
                    SecurityHelper::class,
                    static fn(Container $container): SecurityHelper => new SecurityHelper(
                        $container->get(UserFormProjectionService::class)
                    ),
                    true
                );
            }
        }

        if (!$container->has(AccountMenuProvider::class)) {
            $container->share(
                AccountMenuProvider::class,
                static fn(Container $container): AccountMenuProvider => new AccountMenuProvider(),
                true
            );
        }

        $container->alias('copymypage.helper.image', ImageHelper::class);
        $container->alias('copymypage.helper.sigplus', SigplusHelper::class);
        $container->alias('copymypage.helper.preloader', PreloaderHelper::class);
        $container->alias('copymypage.helper.templateTokens', TemplateTokenHelper::class);
        if ($app instanceof CMSWebApplicationInterface) {
            $container->alias('copymypage.helper.default', UserHelper::class);
            $container->alias('copymypage.helper.user', UserHelper::class);
            $container->alias('copymypage.helper.profile', ProfileHelper::class);
            $container->alias('copymypage.helper.profile.address', ProfileHelper::class);
            $container->alias('copymypage.helper.profile.edit', ProfileHelper::class);
            $container->alias('copymypage.helper.security', SecurityHelper::class);
            $container->alias('copymypage.helper.security.edit', SecurityHelper::class);
        }

        $container->alias('copymypage.navigation.account', AccountMenuProvider::class);
    }

    /**
     * Add CopyMyPage app settings to supported administrator forms.
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
        $name = $form->getName();

        if ($name === 'com_contact.contact') {
            $this->loadLanguage();
            $form->loadFile(JPATH_PLUGINS . '/system/copymypage/forms/contact.xml', false);

            return true;
        }

        if ($name !== 'com_modules.module' || !$this->isSigplusModule($event->getData())) {
            return true;
        }

        $this->loadLanguage();
        $form->loadFile(JPATH_PLUGINS . '/system/copymypage/forms/sigplus.xml', false);

        return true;
    }

    /**
     * Checks whether the prepared module form belongs to sigplus.
     *
     * @param   mixed  $data  The form data payload.
     *
     * @return  bool
     *
     * @since   0.0.14
     */
    private function isSigplusModule(mixed $data): bool
    {
        $module = '';

        if (\is_array($data)) {
            $module = (string) ($data['module'] ?? '');
        } elseif (\is_object($data)) {
            $module = (string) ($data->module ?? '');
        }

        if ($module === '') {
            $jform = $this->getApplication()->getInput()->get('jform', [], 'array');
            $module = \is_array($jform) ? (string) ($jform['module'] ?? '') : '';
        }

        return $module === 'mod_sigplus';
    }
}
