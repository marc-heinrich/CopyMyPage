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

use DigitalPeak\Component\DPCalendar\Site\Helper\RouteHelper as DPCalendarRouteHelper;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Event\Application\AfterRouteEvent;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Event\Model;
use Joomla\CMS\Event\User\AfterLogoutEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
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
use Joomla\Component\CopyMyPage\Site\Service\BookingCompletionService;
use Joomla\Component\CopyMyPage\Site\Service\ContactClaimService;
use Joomla\Component\CopyMyPage\Site\Service\CountryCodeResolver;
use Joomla\Component\CopyMyPage\Site\Service\CustomerDataService;
use Joomla\Component\CopyMyPage\Site\Service\EventSeatInventoryService;
use Joomla\Component\CopyMyPage\Site\Service\OrderCheckoutService;
use Joomla\Component\CopyMyPage\Site\Service\OrderReviewService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentHandoffService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationService;
use Joomla\Component\CopyMyPage\Site\Service\PaymentReconciliationServiceProvider;
use Joomla\Component\CopyMyPage\Site\Service\ProfileAddressService;
use Joomla\Component\CopyMyPage\Site\Service\SeatLayoutService;
use Joomla\Component\CopyMyPage\Site\Service\SeatSelectionService;
use Joomla\Component\CopyMyPage\Site\Service\TicketCartContextService;
use Joomla\Component\CopyMyPage\Site\Service\TicketCatalogService;
use Joomla\Component\CopyMyPage\Site\Service\TicketReservationService;
use Joomla\Component\CopyMyPage\Site\Service\TicketSeatProjectionService;
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
     * @return  array<string, string|array{0: string, 1: int}>
     *
     * @since   0.0.3
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterInitialise'    => 'onAfterInitialise',
            'onAfterRoute'         => ['guardDPCalendarPaymentCallback', Priority::MAX],
            'onContentAfterDelete' => 'onContentAfterDelete',
            'onContentAfterSave'   => 'onContentAfterSave',
            'onContentChangeState' => 'onContentChangeState',
            'onContentPrepare'     => ['onContentPrepare', Priority::MIN],
            'onContentPrepareForm' => 'onContentPrepareForm',
            'onUserAfterLogout'    => 'onUserAfterLogout',
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
     * Stop late or repeated payment callbacks before a provider can mutate a terminal booking.
     *
     * @param   AfterRouteEvent  $event  The after-route event.
     *
     * @return  void
     *
     * @since   0.0.19
     */
    public function guardDPCalendarPaymentCallback(AfterRouteEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSWebApplicationInterface || !$app->isClient('site')) {
            return;
        }

        $input = $app->getInput();
        $task  = $input->getCmd('task', '');

        if (
            $input->getCmd('option', '') !== 'com_dpcalendar'
            || !\in_array($task, ['booking.pay', 'booking.paycancel'], true)
        ) {
            return;
        }

        $bookingId = max(0, $input->getInt('b_id', 0));

        if ($bookingId < 1) {
            return;
        }

        $requestToken = trim($input->getString('dptoken', $input->getString('token', '')));
        $identity     = $app->getIdentity();

        try {
            $decision = Factory::getContainer()
                ->get(PaymentReconciliationService::class)
                ->getCallbackDecision(
                    $bookingId,
                    $requestToken,
                    (int) $app->getSession()->get('com_dpcalendar.booking_id', 0),
                    max(0, (int) ($identity->id ?? 0)),
                    $identity->authorise('dpcalendar.admin.book', 'com_dpcalendar')
                );
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage payment callback guard failed for booking ID '
                    . $bookingId . ' (' . $exception::class . ').',
                Log::ERROR,
                'com_copymypage'
            );

            return;
        }

        if (
            empty($decision['managed'])
            || empty($decision['authorized'])
            || empty($decision['block'])
            || trim((string) ($decision['bookingUid'] ?? '')) === ''
        ) {
            return;
        }

        $app->getLanguage()->load(
            'com_copymypage',
            JPATH_SITE . '/components/com_copymypage',
            null,
            true
        );
        $app->getSession()->set('com_dpcalendar.booking_id', $bookingId);
        $app->enqueueMessage(
            Text::_('COM_COPYMYPAGE_BOOKING_COMPLETION_PAYMENT_CALLBACK_BLOCKED'),
            'notice'
        );
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $app->setHeader('Pragma', 'no-cache', true);
        $app->redirect(
            DPCalendarRouteHelper::getBookingRoute(
                (object) [
                    'id'    => $bookingId,
                    'token' => (string) ($decision['bookingToken'] ?? ''),
                    'uid'   => (string) $decision['bookingUid'],
                ]
            ) . '&layout=order'
        );
    }

    /**
     * Add the CopyMyPage seat assignment to DPCalendar ticket field output.
     *
     * @param   ContentPrepareEvent  $event  The content preparation event.
     *
     * @return  void
     *
     * @since   0.0.19
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        if ($event->getContext() !== 'com_dpcalendar.ticket') {
            return;
        }

        $ticket    = $event->getItem();
        $ticketId  = \is_object($ticket) ? max(0, (int) ($ticket->id ?? 0)) : 0;
        $bookingId = \is_object($ticket) ? max(0, (int) ($ticket->booking_id ?? 0)) : 0;

        if ($ticketId < 1 || $bookingId < 1) {
            return;
        }

        try {
            $this->getApplication()->getLanguage()->load(
                'com_copymypage',
                JPATH_SITE . '/components/com_copymypage',
                null,
                true
            );
            $seat = Factory::getContainer()
                ->get(TicketSeatProjectionService::class)
                ->getForTicket($ticketId, $bookingId);

            if (!\is_array($seat) || trim((string) ($seat['label'] ?? '')) === '') {
                return;
            }

            if (isset($ticket->jcfields) && !\is_array($ticket->jcfields)) {
                return;
            }

            $fields = $ticket->jcfields ?? [];

            foreach ($fields as $field) {
                if (
                    \is_object($field)
                    && (string) ($field->name ?? '') === 'copymypage_seat'
                ) {
                    return;
                }
            }

            $fields['copymypage_seat'] = (object) [
                'id'    => 'copymypage_seat',
                'label' => 'COM_COPYMYPAGE_BOOKING_COMPLETION_SEAT_LABEL',
                'name'  => 'copymypage_seat',
                'value' => htmlspecialchars(
                    (string) $seat['label'],
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ),
            ];
            $ticket->jcfields = $fields;
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage ticket seat projection failed for ticket ID '
                    . $ticketId . ' (' . $exception::class . ').',
                Log::WARNING,
                'com_copymypage'
            );
        }
    }

    /**
     * Revoke persistent login tokens after an administrator remotely logs out a site user.
     *
     * @param   AfterLogoutEvent  $event  The successful logout event.
     *
     * @return  void
     *
     * @since   0.0.17
     */
    public function onUserAfterLogout(AfterLogoutEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $identity     = $app->getIdentity();
        $parameters   = $event->getParameters();
        $options      = $event->getOptions();
        $targetUserId = (int) ($parameters['id'] ?? 0);
        $username     = trim((string) ($parameters['username'] ?? $options['username'] ?? ''));
        $clientId     = (int) ($options['clientid'] ?? -1);
        $shared       = (bool) $app->get('shared_session', '0');

        if (
            !$identity->authorise('core.manage', 'com_users')
            || $targetUserId <= 0
            || $targetUserId === (int) $identity->id
            || $username === ''
            || (!$shared && $clientId !== 0)
        ) {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__user_keys'))
            ->where($db->quoteName('user_id') . ' = :username')
            ->bind(':username', $username);

        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $exception) {
            Log::add(
                \sprintf(
                    'Failed to revoke Remember Me tokens for user ID %d: %s',
                    $targetUserId,
                    $exception->getMessage()
                ),
                Log::WARNING,
                'security'
            );
        }
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
        $dispatcher->addListener(
            'onAfterRoute',
            [$this, 'redirectCoreAccountRoutes'],
            Priority::MIN
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
     * Redirect safe core account display routes to their CopyMyPage dashboard equivalents.
     *
     * Joomla keeps control of guests, form submissions, controller tasks, remembered-login
     * sessions, forced password resets and the first-time or mandatory MFA setup flows.
     *
     * @param   AfterRouteEvent  $event  The after-route event.
     *
     * @return  void
     *
     * @since   0.0.18
     */
    public function redirectCoreAccountRoutes(AfterRouteEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSWebApplicationInterface) {
            return;
        }

        $dashboardLayout = $this->getCoreAccountRedirectLayout($app);

        if ($dashboardLayout === null) {
            return;
        }

        $app->redirect(
            Factory::getContainer()
                ->get(AccountMenuProvider::class)
                ->getDashboardUrl($app, $dashboardLayout)
        );
    }

    /**
     * Resolve the CopyMyPage dashboard layout for an unambiguous core display request.
     *
     * @param   CMSWebApplicationInterface  $app  The active site application.
     *
     * @return  string|null
     *
     * @since   0.0.18
     */
    private function getCoreAccountRedirectLayout(CMSWebApplicationInterface $app): ?string
    {
        $input    = $app->getInput();
        $identity = $app->getIdentity();

        if (
            $input->getMethod() !== 'GET'
            || $input->getCmd('option', '') !== 'com_users'
            || (int) $identity->id <= 0
            || (bool) $identity->guest
            || !empty($identity->cookieLogin)
            || (bool) $identity->requireReset
            || $input->getCmd('format', 'html') !== 'html'
            || $input->getCmd('tmpl', '') !== ''
            || $input->getCmd('action', '') !== ''
            || $input->getString('returnurl', '') !== ''
            || $input->getString('return', '') !== ''
            || !$this->requestTargetsCurrentUser($app)
        ) {
            return null;
        }

        $view   = $input->getCmd('view', '');
        $task   = $input->getCmd('task', '');
        $layout = $input->getCmd('layout', '');

        if ($view === 'profile' && $task === '') {
            return match ($layout) {
                '', 'default', 'default_core', 'default_custom', 'default_params' => 'profile',
                'edit' => 'profile.edit',
                default => null,
            };
        }

        $isMethodsDisplay = ($view === 'methods' && $task === '')
            || $task === 'methods.display';

        if (!$isMethodsDisplay || !\in_array($layout, ['', 'default', 'list'], true)) {
            return null;
        }

        $session = $app->getSession();

        if (
            (int) $session->get('com_users.mfa_checked', 0) !== 1
            || (int) $session->get('com_users.mandatory_mfa_setup', 0) !== 0
        ) {
            return null;
        }

        return 'security';
    }

    /**
     * Ensure an optional user_id does not turn an own-account redirect into another-user access.
     *
     * @param   CMSWebApplicationInterface  $app  The active site application.
     *
     * @return  bool
     *
     * @since   0.0.18
     */
    private function requestTargetsCurrentUser(CMSWebApplicationInterface $app): bool
    {
        $inputUserId = $app->getInput()->get('user_id', null, 'raw');

        return $inputUserId === null
            || ($inputUserId !== '' && $app->getInput()->getInt('user_id', 0) === (int) $app->getIdentity()->id);
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

            if (!$container->has(TicketCatalogService::class)) {
                $container->share(
                    TicketCatalogService::class,
                    static fn(Container $container): TicketCatalogService => new TicketCatalogService(
                        $app,
                        $container->get(DatabaseInterface::class)
                    ),
                    true
                );
            }

            if (!$container->has(TicketCartContextService::class)) {
                $container->share(
                    TicketCartContextService::class,
                    static fn(Container $container): TicketCartContextService
                        => new TicketCartContextService(
                            $app,
                            $container->get(DatabaseInterface::class)
                        ),
                    true
                );
            }

            if (!$container->has(TicketReservationService::class)) {
                $container->share(
                    TicketReservationService::class,
                    static fn(Container $container): TicketReservationService => new TicketReservationService(
                        $container->get(DatabaseInterface::class),
                        $container->get(TicketCatalogService::class),
                        $container->get(TicketCartContextService::class),
                        $container->get(SeatSelectionService::class)
                    ),
                    true
                );
            }

            if (!$container->has(SeatLayoutService::class)) {
                $container->share(
                    SeatLayoutService::class,
                    static fn(Container $container): SeatLayoutService => new SeatLayoutService(
                        $container->get(DatabaseInterface::class),
                        JPATH_SITE . '/components/com_copymypage/data/seat-layouts'
                    ),
                    true
                );
            }

            if (!$container->has(EventSeatInventoryService::class)) {
                $container->share(
                    EventSeatInventoryService::class,
                    static fn(Container $container): EventSeatInventoryService
                        => new EventSeatInventoryService(
                            $container->get(DatabaseInterface::class),
                            $container->get(SeatLayoutService::class)
                        ),
                    true
                );
            }

            if (!$container->has(SeatSelectionService::class)) {
                $container->share(
                    SeatSelectionService::class,
                    static fn(Container $container): SeatSelectionService => new SeatSelectionService(
                        $container->get(DatabaseInterface::class),
                        $container->get(TicketCatalogService::class),
                        $container->get(TicketCartContextService::class)
                    ),
                    true
                );
            }

            if (!$container->has(TicketSeatProjectionService::class)) {
                $container->share(
                    TicketSeatProjectionService::class,
                    static fn(Container $container): TicketSeatProjectionService
                        => new TicketSeatProjectionService(
                            $container->get(DatabaseInterface::class)
                        ),
                    true
                );
            }

            if (!$container->has(PaymentHandoffService::class)) {
                $container->share(
                    PaymentHandoffService::class,
                    static fn(Container $container): PaymentHandoffService
                        => new PaymentHandoffService($app),
                    true
                );
            }

            $container->registerServiceProvider(new PaymentReconciliationServiceProvider());

            if (!$container->has(BookingCompletionService::class)) {
                $container->share(
                    BookingCompletionService::class,
                    static fn(Container $container): BookingCompletionService
                        => new BookingCompletionService(
                            $container->get(DatabaseInterface::class),
                            $container->get(TicketSeatProjectionService::class)
                        ),
                    true
                );
            }

            if (!$container->has(CustomerDataService::class)) {
                $container->share(
                    CustomerDataService::class,
                    static fn(Container $container): CustomerDataService => new CustomerDataService(
                        $app,
                        $container->get(DatabaseInterface::class),
                        $container->get(FormFactoryInterface::class),
                        $container->get(ProfileAddressRepository::class),
                        $container->get(AddressCatalogService::class),
                        $container->get(TicketCartContextService::class),
                        $container->get(SeatSelectionService::class)
                    ),
                    true
                );
            }

            if (!$container->has(OrderReviewService::class)) {
                $container->share(
                    OrderReviewService::class,
                    static fn(Container $container): OrderReviewService => new OrderReviewService(
                        $container->get(CustomerDataService::class),
                        $container->get(SeatSelectionService::class),
                        $container->get(TicketReservationService::class)
                    ),
                    true
                );
            }

            if (!$container->has(OrderCheckoutService::class)) {
                $container->share(
                    OrderCheckoutService::class,
                    static fn(Container $container): OrderCheckoutService => new OrderCheckoutService(
                        $app,
                        $container->get(DatabaseInterface::class),
                        $container->get(OrderReviewService::class),
                        $container->get(TicketCartContextService::class),
                        $container->get(TicketCatalogService::class),
                        $container->get(TicketSeatProjectionService::class)
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

        if ($name === 'com_dpcalendar.event') {
            if (!$app->getIdentity()->authorise('copymypage.seating.configure', 'com_copymypage')) {
                return true;
            }

            $this->loadLanguage();
            FormHelper::addFieldPrefix('Joomla\\Plugin\\System\\CopyMyPage\\Field');
            $form->loadFile(
                JPATH_PLUGINS . '/system/copymypage/forms/dpcalendar_event_seating.xml',
                false
            );

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
     * Release linked seats after a DPCalendar booking has been deleted.
     */
    public function onContentAfterDelete(Model\AfterDeleteEvent $event): void
    {
        if ($event->getContext() !== 'com_dpcalendar.booking') {
            return;
        }

        $item      = $event->getItem();
        $bookingId = \is_object($item) ? max(0, (int) ($item->id ?? 0)) : 0;

        $this->releaseDPCalendarBookingSeats($bookingId);
    }

    /**
     * Release linked seats after cancellation, refund or trashing.
     */
    public function onContentChangeState(Model\AfterChangeStateEvent $event): void
    {
        if (
            $event->getContext() !== 'com_dpcalendar.booking'
            || !\in_array((int) $event->getValue(), [6, 7, -2], true)
        ) {
            return;
        }

        foreach ((array) $event->getPks() as $bookingId) {
            $this->releaseDPCalendarBookingSeats(max(0, (int) $bookingId));
        }
    }

    /**
     * Import and activate the JSON layout selected in a DPCalendar event.
     *
     * The event itself has already been saved when this hook runs. Seating
     * failures therefore remain visible administrator messages and never
     * expose internal exception details or roll back DPCalendar data.
     *
     * @param   Model\AfterSaveEvent  $event  The content save event.
     *
     * @since   0.0.19
     */
    public function onContentAfterSave(Model\AfterSaveEvent $event): void
    {
        $app = $this->getApplication();

        if (
            !$app->isClient('administrator')
            || $event->getContext() !== 'com_dpcalendar.event'
            || !$app->getIdentity()->authorise('copymypage.seating.configure', 'com_copymypage')
        ) {
            return;
        }

        $post  = $app->getInput()->post->getArray();
        $jform = \is_array($post['jform'] ?? null) ? $post['jform'] : [];
        $action = trim((string) ($jform['copymypage_seating_action'] ?? ''));
        $item   = $event->getItem();
        $eventId = \is_object($item) ? max(0, (int) ($item->id ?? 0)) : 0;

        if ($action === 'mark_ready') {
            if ($eventId === 0) {
                return;
            }

            $this->loadLanguage();
            $app->getLanguage()->load(
                'com_copymypage',
                JPATH_ADMINISTRATOR . '/components/com_copymypage',
                null,
                true
            );
            $userId = max(0, (int) $app->getIdentity()->id);

            try {
                $ready = Factory::getContainer()
                    ->get(EventSeatInventoryService::class)
                    ->markReady($eventId, $userId);
                $app->enqueueMessage(
                    Text::sprintf(
                        'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_READY_SUCCESS',
                        (int) $ready['seatCount']
                    ),
                    'success'
                );
            } catch (\DomainException $exception) {
                $app->enqueueMessage(
                    Text::sprintf(
                        'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SAVE_DRAFT_WARNING',
                        $exception->getMessage()
                    ),
                    'warning'
                );
            } catch (\Throwable $exception) {
                Log::add(
                    'CopyMyPage DPCalendar seating activation failed: ' . $exception->getMessage(),
                    Log::ERROR,
                    'com_copymypage'
                );
                $app->enqueueMessage(
                    Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SAVE_ERROR'),
                    'error'
                );
            }

            return;
        }

        if (!array_key_exists('copymypage_layout_file', $jform)) {
            return;
        }

        $file = trim((string) $jform['copymypage_layout_file']);

        if ($file === '' || $eventId === 0) {
            return;
        }

        $this->loadLanguage();
        $app->getLanguage()->load(
            'com_copymypage',
            JPATH_ADMINISTRATOR . '/components/com_copymypage',
            null,
            true
        );
        $userId = max(0, (int) $app->getIdentity()->id);

        try {
            $container = Factory::getContainer();
            $layout    = $container->get(SeatLayoutService::class)
                ->importBundledDefinition($file, $userId);
            $inventory = $container->get(EventSeatInventoryService::class);
            $inventory->assignDraft($eventId, (int) $layout['id'], $userId);
            $ready = $inventory->markReady($eventId, $userId);

            $app->enqueueMessage(
                Text::sprintf(
                    'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SAVE_SUCCESS',
                    (string) $layout['title'],
                    (int) $layout['version'],
                    (int) $ready['seatCount']
                ),
                'success'
            );
        } catch (\DomainException $exception) {
            $app->enqueueMessage(
                Text::sprintf(
                    'PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SAVE_DRAFT_WARNING',
                    $exception->getMessage()
                ),
                'warning'
            );
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage DPCalendar seating activation failed: ' . $exception->getMessage(),
                Log::ERROR,
                'com_copymypage'
            );
            $app->enqueueMessage(
                Text::_('PLG_SYSTEM_COPYMYPAGE_EVENT_SEATING_SAVE_ERROR'),
                'error'
            );
        }
    }

    private function releaseDPCalendarBookingSeats(int $bookingId): void
    {
        if ($bookingId < 1) {
            return;
        }

        try {
            Factory::getContainer()
                ->get(OrderCheckoutService::class)
                ->releaseBookingSeats($bookingId);
        } catch (\Throwable $exception) {
            Log::add(
                'CopyMyPage could not release seats for DPCalendar booking '
                    . $bookingId . ' (' . $exception::class . ').',
                Log::ERROR,
                'com_copymypage'
            );
        }
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
