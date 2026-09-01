<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Repository\ProfileAddressRepository;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Owns the guarded Step-3 customer draft and optional Joomla registration.
 */
final class CustomerDataService
{
    private const STATE_PREFIX = 'com_copymypage.customerdata.data.';

    private const ROOT_ATTRIBUTE = 'data-cmp-customer-data';

    private const ACCOUNT_FIELDS_ATTRIBUTE = 'data-cmp-customer-account-fields';

    private const CONTINUE_ATTRIBUTE = 'data-cmp-customer-continue';

    private const CUSTOMER_FORM_ATTRIBUTE = 'data-cmp-customer-form';

    private const LOGIN_MODE_ATTRIBUTE = 'data-cmp-customer-login-mode';

    private const LOGIN_MODE_STATE_PREFIX = 'com_copymypage.customerdata.login-mode.';

    private const MODE_SWITCHER_ATTRIBUTE = 'data-cmp-customer-mode-switcher';

    private const EXPECTED_REVISION_FIELD = 'expectedCartRevision';

    private const CUSTOMER_FIELDS = [
        'first_name'   => 255,
        'last_name'    => 255,
        'email'        => 320,
        'street'       => 500,
        'house_number' => 100,
        'postcode'     => 100,
        'city'         => 100,
        'country_code' => 2,
        'region_code'  => 16,
        'telephone'    => 100,
    ];

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly FormFactoryInterface $formFactory,
        private readonly ProfileAddressRepository $profileAddresses,
        private readonly AddressCatalogService $catalogue,
        private readonly TicketCartContextService $cartContext,
        private readonly SeatSelectionService $seatSelection
    ) {
    }

    /**
     * Browser configuration without customer or account values.
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'rootSelector' => '[' . self::ROOT_ATTRIBUTE . ']',
            'selectors'    => [
                'accountFields'   => '[' . self::ACCOUNT_FIELDS_ATTRIBUTE . ']',
                'accountRequired' => '#jform_username, #jform_password1, #jform_password2, '
                    . '[name="jform[privacyconsent][privacy]"], #jform_captcha',
                'accountToggle'   => '#jform_create_account',
                'continueButton'  => '[' . self::CONTINUE_ATTRIBUTE . ']',
                'customerForm'    => '[' . self::CUSTOMER_FORM_ATTRIBUTE . ']',
                'email'           => '#jform_email',
                'loginMode'       => '[' . self::LOGIN_MODE_ATTRIBUTE . ']',
                'modeSwitcher'    => '[' . self::MODE_SWITCHER_ATTRIBUTE . ']',
                'username'        => '#jform_username',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getMarkupAttributes(): array
    {
        return [
            'accountFields' => self::ACCOUNT_FIELDS_ATTRIBUTE,
            'continue'      => self::CONTINUE_ATTRIBUTE,
            'customerForm'  => self::CUSTOMER_FORM_ATTRIBUTE,
            'loginMode'     => self::LOGIN_MODE_ATTRIBUTE,
            'modeSwitcher'  => self::MODE_SWITCHER_ATTRIBUTE,
            'root'          => self::ROOT_ATTRIBUTE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getFormFieldNames(): array
    {
        return ['expectedCartRevision' => self::EXPECTED_REVISION_FIELD];
    }

    public function canEnter(): bool
    {
        $cart = $this->cartContext->getActiveCart();

        return $cart !== null && $this->seatSelection->isCheckoutReady($cart);
    }

    public function getCustomerDataUrl(bool $xhtml = false): string
    {
        return Route::_('index.php?option=com_copymypage&view=customerdata', $xhtml);
    }

    public function getReviewUrl(bool $xhtml = false): string
    {
        return Route::_('index.php?option=com_copymypage&view=orderreview', $xhtml);
    }

    public function canReview(): bool
    {
        return $this->getReviewCustomerData() !== [];
    }

    /**
     * Return the validated billing data required by the read-only review step.
     *
     * @return array<string, string>
     */
    public function getReviewCustomerData(): array
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart === null || !$this->seatSelection->isCheckoutReady($cart)) {
            return [];
        }

        $draft = $this->findDraft((int) $cart->id);

        if ($draft === null) {
            return [];
        }

        $validation = $this->validateCustomerData($this->draftToFormData($draft));

        if ($validation['errors'] !== []) {
            return [];
        }

        $data = $validation['data'];

        return [
            'city'         => (string) $data['city'],
            'countryCode'  => (string) $data['country_code'],
            'countryName'  => (string) ($data['country_name'] ?? ''),
            'email'        => (string) $data['email'],
            'firstName'    => (string) $data['first_name'],
            'houseNumber'  => (string) $data['house_number'],
            'lastName'     => (string) $data['last_name'],
            'postcode'     => (string) $data['postcode'],
            'regionCode'   => (string) $data['region_code'],
            'regionName'   => (string) ($data['region_name'] ?? ''),
            'street'       => (string) $data['street'],
            'telephone'    => (string) $data['telephone'],
        ];
    }

    /**
     * Prepare the non-mutating Step-3 view state.
     *
     * @return array<string, mixed>
     */
    public function getViewState(): array
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart === null || !$this->seatSelection->isCheckoutReady($cart)) {
            return [
                'accountCreated'   => false,
                'accountExpanded'  => false,
                'accountForm'      => null,
                'blocked'          => true,
                'captchaEnabled'   => false,
                'cartRevision'     => 0,
                'form'             => null,
                'guest'            => true,
                'loginForm'        => null,
                'loginModeActive'  => false,
                'showAccountOption' => false,
            ];
        }

        $cartId  = (int) $cart->id;
        $draft   = $this->findDraft($cartId);
        $data    = $draft !== null ? $this->draftToFormData($draft) : $this->getPrefillData();
        $pending = $this->app->getUserState(self::STATE_PREFIX . $cartId, []);

        if (\is_array($pending) && $pending !== []) {
            $data = array_replace($data, $this->getSafeStateData($pending));
        }

        $accountUserId    = max(0, (int) ($draft->account_user_id ?? 0));
        $identity         = $this->app->getIdentity();
        $guest            = (bool) ($identity->guest ?? true) || (int) ($identity->id ?? 0) === 0;
        $showAccount      = $guest && $accountUserId === 0 && $this->isRegistrationAllowed();
        $accountExpanded  = $showAccount && !empty($data['create_account']);
        $registrationForm = null;
        $captchaEnabled   = false;
        $loginForm        = $guest ? $this->prepareLoginForm() : null;
        $loginModeActive  = $guest
            && (bool) $this->app->getUserState(self::LOGIN_MODE_STATE_PREFIX . $cartId, false);
        $this->app->setUserState(self::LOGIN_MODE_STATE_PREFIX . $cartId, null);

        if ($showAccount) {
            [$registrationForm, $captchaEnabled] = $this->prepareRegistrationForm(
                $data,
                $accountExpanded
            );
        }

        return [
            'accountCreated'    => $accountUserId > 0,
            'accountExpanded'   => $accountExpanded,
            'accountForm'       => $registrationForm,
            'blocked'           => false,
            'captchaEnabled'    => $captchaEnabled,
            'cartRevision'      => $this->cartContext->getRevision($cart),
            'form'              => $this->prepareCustomerForm($data),
            'guest'             => $guest,
            'loginForm'         => $loginForm,
            'loginModeActive'   => $loginModeActive,
            'showAccountOption' => $showAccount,
        ];
    }

    /**
     * Validate and atomically save one cart-owned customer draft.
     *
     * @param   array<string, mixed>  $rawData
     *
     * @return array{errors: array<int, \Throwable|string>, accountCreated: bool}
     */
    public function save(array $rawData, int $expectedRevision): array
    {
        $activeCart = $this->cartContext->getActiveCart();

        if ($activeCart === null || !$this->seatSelection->isCheckoutReady($activeCart)) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_MESSAGE'));
        }

        $validation = $this->validateCustomerData($rawData);

        if ($validation['errors'] !== []) {
            return ['errors' => $validation['errors'], 'accountCreated' => false];
        }

        $customerData  = $validation['data'];
        $createAccount = !empty($customerData['create_account']);
        $account       = null;

        if ($createAccount) {
            if (!$this->canCreateAccount($this->findDraft((int) $activeCart->id))) {
                return [
                    'errors' => [Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_UNAVAILABLE')],
                    'accountCreated' => false,
                ];
            }

            $account = $this->validateAccountData($rawData, $customerData);

            if ($account['errors'] !== []) {
                return ['errors' => $account['errors'], 'accountCreated' => false];
            }
        }

        $this->cartContext->beginTransaction();

        try {
            $cart = $this->cartContext->getActiveCartForUpdate();

            if ($cart === null || !$this->seatSelection->isCheckoutReady($cart)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_BLOCKED_MESSAGE'));
            }

            $this->cartContext->assertExpectedRevision($cart, $expectedRevision);
            $draft = $this->findDraft((int) $cart->id, true);

            if ($createAccount && !$this->canCreateAccount($draft)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_UNAVAILABLE'));
            }

            $accountUserId = max(0, (int) ($draft->account_user_id ?? 0));
            $draftId       = $this->saveDraft((int) $cart->id, $draft, $customerData, $accountUserId);

            if ($createAccount && \is_array($account)) {
                $accountUserId = $this->registerAccount(
                    $account['model'],
                    $account['data'],
                    $this->getSafeStateData($rawData)
                );
                $this->linkAccount($draftId, $accountUserId);
            }

            $this->cartContext->advanceCart((int) $cart->id);
            $this->cartContext->commitTransaction();

            return ['errors' => [], 'accountCreated' => $accountUserId > 0];
        } catch (\Throwable $exception) {
            $this->cartContext->rollbackTransaction();

            throw $exception;
        }
    }

    /**
     * Preserve only non-secret form values across PRG validation redirects.
     *
     * @param   array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    public function getSafeStateData(array $data): array
    {
        $safe = $this->projectCustomerData($data);
        $safe['username'] = $this->scalar($data['username'] ?? '', 150);

        $privacy = $data['privacyconsent'] ?? [];
        $safe['privacyconsent'] = [
            'privacy' => \is_array($privacy) && !empty($privacy['privacy']) ? 1 : 0,
        ];

        return $safe;
    }

    /**
     * @param   array<string, mixed>  $data
     */
    public function rememberValidationData(array $data): void
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart !== null) {
            $this->app->setUserState(
                self::STATE_PREFIX . (int) $cart->id,
                $this->getSafeStateData($data)
            );
        }
    }

    public function rememberLoginMode(): void
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart !== null) {
            $this->app->setUserState(
                self::LOGIN_MODE_STATE_PREFIX . (int) $cart->id,
                true
            );
        }
    }

    public function clearValidationData(): void
    {
        $cart = $this->cartContext->getActiveCart();

        if ($cart !== null) {
            $this->app->setUserState(self::STATE_PREFIX . (int) $cart->id, null);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getRegions(string $countryCode): array
    {
        return $this->catalogue->getRegions($countryCode);
    }

    /**
     * @param   array<string, mixed>  $data
     */
    private function prepareCustomerForm(array $data): Form
    {
        $this->loadFormLanguages();
        Form::addFormPath(JPATH_SITE . '/components/com_copymypage/forms');
        $form = $this->formFactory->createForm(
            'com_copymypage.customerdata',
            ['control' => 'jform']
        );

        if (!$form->loadFile('customerdata')) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_FORM'), 500);
        }

        $form->bind($data);
        $form->setFieldAttribute(
            'create_account',
            'aria-expanded',
            !empty($data['create_account']) ? 'true' : 'false'
        );

        return $form;
    }

    private function prepareLoginForm(): Form
    {
        $this->app->getLanguage()->load('com_users', JPATH_SITE, null, true);
        Form::addFormPath(JPATH_SITE . '/components/com_users/forms');
        $component = $this->app->bootComponent('com_users');
        $model     = $component->getMVCFactory()->createModel(
            'Login',
            'Site',
            ['ignore_request' => true]
        );
        $form = $model->getForm([], false);

        if (!$form instanceof Form) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_FORM'), 500);
        }

        foreach (['username', 'password', 'secretkey'] as $fieldName) {
            if ($form->getField($fieldName) === false) {
                continue;
            }

            $existingClass = trim((string) $form->getFieldAttribute($fieldName, 'class', ''));
            $form->setFieldAttribute(
                $fieldName,
                'class',
                trim('uk-input ' . $existingClass)
            );
        }

        return $form;
    }

    /**
     * @param   array<string, mixed>  $data
     *
     * @return array{0: Form, 1: bool}
     */
    private function prepareRegistrationForm(array $data, bool $expanded): array
    {
        [$model, $form, $captchaEnabled] = $this->getRegistrationResources();
        unset($model);

        $form->bind([
            'username'       => $this->scalar($data['username'] ?? '', 150),
            'privacyconsent' => $data['privacyconsent'] ?? ['privacy' => 0],
        ]);

        $requiredFields = [
            ['username', null],
            ['password1', null],
            ['password2', null],
            ['privacy', 'privacyconsent'],
            ['captcha', null],
        ];

        foreach ($requiredFields as [$field, $group]) {
            if ($form->getField($field, $group) !== false) {
                $form->setFieldAttribute($field, 'required', $expanded ? 'true' : 'false', $group);
            }
        }

        return [$form, $captchaEnabled];
    }

    /**
     * @param   array<string, mixed>  $rawData
     *
     * @return array{data: array<string, mixed>, errors: array<int, \Throwable|string>}
     */
    private function validateCustomerData(array $rawData): array
    {
        $projected = $this->projectCustomerData($rawData);
        $form      = $this->prepareCustomerForm($projected);
        $filtered  = $form->filter($projected);
        $data      = $this->projectCustomerData(\is_array($filtered) ? $filtered : []);
        $valid     = $form->validate($data);
        $errors    = $form->getErrors();

        foreach (self::CUSTOMER_FIELDS as $fieldName => $maximumLength) {
            if (mb_strlen((string) $data[$fieldName], 'UTF-8') <= $maximumLength) {
                continue;
            }

            $field = $form->getField($fieldName);
            $label = $field !== false ? Text::_((string) $field->title) : $fieldName;
            $errors[] = Text::sprintf(
                'COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_TOO_LONG',
                $label,
                $maximumLength
            );
            $valid = false;
        }

        if ($data['email'] === '' || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_EMAIL');
            $valid = false;
        }

        $country = $this->catalogue->resolveCountry($data['country_code']);

        if ($country === null) {
            $errors[] = Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_COUNTRY');
            $valid = false;
        } else {
            $data['country_code'] = $country['code'];
            $data['country_name'] = $country['name'];
        }

        $data['region_name'] = '';

        if ($data['region_code'] !== '' && $country !== null) {
            $region = $this->catalogue->resolveRegion($country['code'], $data['region_code']);

            if ($region === null) {
                $errors[] = Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_REGION');
                $valid = false;
            } else {
                $data['region_code'] = $region['code'];
                $data['region_name'] = $region['name'];
            }
        }

        if (!$valid && $errors === []) {
            $errors[] = Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_FORM');
        }

        return ['data' => $data, 'errors' => $valid ? [] : $errors];
    }

    /**
     * @param   array<string, mixed>  $rawData
     * @param   array<string, mixed>  $customerData
     *
     * @return array{model: object|null, data: array<string, mixed>, errors: array<int, \Throwable|string>}
     */
    private function validateAccountData(array $rawData, array $customerData): array
    {
        [$model, $form] = $this->getRegistrationResources();
        $privacy = $rawData['privacyconsent'] ?? [];
        $data = [
            'name'           => trim($customerData['first_name'] . ' ' . $customerData['last_name']),
            'username'       => $this->scalar($rawData['username'] ?? '', 150),
            'password1'      => $this->scalar($rawData['password1'] ?? '', 99, false),
            'password2'      => $this->scalar($rawData['password2'] ?? '', 99, false),
            'email1'         => (string) $customerData['email'],
            'privacyconsent' => [
                'privacy' => \is_array($privacy) && !empty($privacy['privacy']) ? 1 : 0,
            ],
            'captcha' => $this->scalar($rawData['captcha'] ?? '', 16384, false),
        ];
        $validated = $model->validate($form, $data);

        if ($validated === false) {
            $errors = $model->getErrors();

            if (!\is_array($errors) || $errors === []) {
                $errors = [Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_ERROR')];
            }

            return ['model' => null, 'data' => [], 'errors' => $errors];
        }

        return [
            'model'  => $model,
            'data'   => \is_array($validated) ? $validated : [],
            'errors' => [],
        ];
    }

    /**
     * @return array{0: object, 1: Form, 2: bool}
     */
    private function getRegistrationResources(): array
    {
        $this->app->getLanguage()->load('com_users', JPATH_SITE, null, true);
        Form::addFormPath(JPATH_SITE . '/components/com_users/forms');
        $component = $this->app->bootComponent('com_users');
        $model     = $component->getMVCFactory()->createModel(
            'Registration',
            'Site',
            ['ignore_request' => true]
        );
        $form = $model->getForm([], false);

        if (!$form instanceof Form) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ERROR_FORM'), 500);
        }

        $captchaName    = ComponentHelper::getParams('com_users')->get(
            'captcha',
            $this->app->get('captcha', '0')
        );
        $captchaEnabled = false;

        foreach (PluginHelper::getPlugin('captcha') as $plugin) {
            if ($captchaName === $plugin->name) {
                $captchaEnabled = true;
                break;
            }
        }

        return [$model, $form, $captchaEnabled];
    }

    /**
     * Execute the already validated registration through Joomla's core model.
     *
     * @param   array<string, mixed>  $registrationData
     * @param   array<string, mixed>  $safeRequestData
     */
    private function registerAccount(
        object $model,
        array $registrationData,
        array $safeRequestData
    ): int {
        $input          = $this->app->getInput();
        $originalOption = $input->get('option', 'com_copymypage', 'cmd');
        $originalTask   = $input->post->get('task', 'customerdata.save', 'cmd');
        $state          = $this->app->getUserState('com_users.registration.data', []);

        if (\is_array($state)) {
            unset($state['password1'], $state['password2'], $state['captcha']);
        } else {
            $state = [];
        }

        $this->app->setUserState('com_users.registration.data', null);
        $input->set('option', 'com_users');
        $input->post->set('task', 'registration.register');
        $input->post->set('jform', $registrationData);

        try {
            $result = $model->register($registrationData);
        } finally {
            $input->set('option', $originalOption);
            $input->post->set('task', $originalTask);
            $input->post->set('jform', $safeRequestData);
            $this->app->setUserState(
                'com_users.registration.data',
                $state !== [] ? $state : null
            );
        }

        if ($result === false) {
            throw new \DomainException(
                (string) ($model->getError() ?: Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_ERROR'))
            );
        }

        if (\is_int($result) || (\is_string($result) && ctype_digit($result))) {
            $userId = (int) $result;
        } else {
            $username = (string) ($registrationData['username'] ?? '');
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__users'))
                ->where($this->db->quoteName('username') . ' = :username')
                ->bind(':username', $username, ParameterType::STRING)
                ->setLimit(1);
            $userId = (int) $this->db->setQuery($query)->loadResult();
        }

        if ($userId < 1) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_CUSTOMER_DATA_ACCOUNT_ERROR'));
        }

        return $userId;
    }

    /**
     * @param   array<string, mixed>  $data
     *
     * @return array<string, mixed>
     */
    private function projectCustomerData(array $data): array
    {
        $result = [];

        foreach (self::CUSTOMER_FIELDS as $fieldName => $maximumLength) {
            $result[$fieldName] = $this->scalar($data[$fieldName] ?? '', $maximumLength + 1);
        }

        $result['create_account'] = !empty($data['create_account']) ? 1 : 0;

        return $result;
    }

    private function scalar(mixed $value, int $maximumLength, bool $trim = true): string
    {
        if (!\is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        $value = $trim ? trim($value) : $value;

        return mb_substr($value, 0, $maximumLength, 'UTF-8');
    }

    /**
     * @return array<string, mixed>
     */
    private function getPrefillData(): array
    {
        $data = array_fill_keys(array_keys(self::CUSTOMER_FIELDS), '');
        $data['create_account'] = 0;
        $data['username']       = '';
        $identity = $this->app->getIdentity();

        if (!$identity instanceof User || (bool) $identity->guest || (int) $identity->id < 1) {
            return $data;
        }

        $nameParts = preg_split('/\s+/u', trim((string) $identity->name)) ?: [];
        $data['first_name'] = (string) array_shift($nameParts);
        $data['last_name']  = implode(' ', $nameParts);
        $data['email']      = trim((string) $identity->email);
        $address            = $this->profileAddresses->findForUser((int) $identity->id);

        if ($address === null) {
            return $data;
        }

        $data['first_name']   = $address->recipientFirstName ?: $data['first_name'];
        $data['last_name']    = $address->recipientLastName ?: $data['last_name'];
        $data['street']       = $address->addressLine1;
        $data['house_number'] = $address->houseNumber;
        $data['postcode']     = $address->postalCode;
        $data['city']         = $address->city;
        $data['country_code'] = $address->countryCode;
        $data['region_code']  = $address->regionCode;
        $data['telephone']    = $address->telephone;

        return $data;
    }

    private function findDraft(int $cartId, bool $forUpdate = false): ?object
    {
        if ($cartId < 1) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__copymypage_ticket_customers'))
            ->where($this->db->quoteName('cart_id') . ' = ' . $cartId)
            ->setLimit(1);
        $sql = (string) $query . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->setQuery($sql)->loadObject();

        return \is_object($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function draftToFormData(object $draft): array
    {
        $data = [];

        foreach (array_keys(self::CUSTOMER_FIELDS) as $fieldName) {
            $data[$fieldName] = trim((string) ($draft->$fieldName ?? ''));
        }

        $data['create_account'] = 0;
        $data['username']       = '';

        return $data;
    }

    /**
     * @param   array<string, mixed>  $data
     */
    private function saveDraft(
        int $cartId,
        ?object $existing,
        array $data,
        int $accountUserId
    ): int {
        $identity = $this->app->getIdentity();
        $now      = $this->cartContext->now();
        $record   = (object) [
            'cart_id'         => $cartId,
            'user_id'         => max(0, (int) ($identity->id ?? 0)),
            'account_user_id' => $accountUserId > 0 ? $accountUserId : null,
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'email'           => $data['email'],
            'street'          => $data['street'],
            'house_number'    => $data['house_number'],
            'postcode'        => $data['postcode'],
            'city'            => $data['city'],
            'country_code'    => $data['country_code'],
            'country_name'    => $data['country_name'],
            'region_code'     => $data['region_code'],
            'region_name'     => $data['region_name'],
            'telephone'       => $data['telephone'],
            'modified'        => $now,
        ];

        if ($existing !== null) {
            $record->id = (int) $existing->id;
            $this->db->updateObject('#__copymypage_ticket_customers', $record, 'id', true);

            return (int) $existing->id;
        }

        $record->id      = 0;
        $record->created = $now;
        $this->db->insertObject('#__copymypage_ticket_customers', $record, 'id');

        return (int) $record->id;
    }

    private function linkAccount(int $draftId, int $userId): void
    {
        $record = (object) [
            'id'              => $draftId,
            'account_user_id' => $userId,
            'modified'        => $this->cartContext->now(),
        ];
        $this->db->updateObject('#__copymypage_ticket_customers', $record, 'id');
    }

    private function canCreateAccount(?object $draft): bool
    {
        $identity = $this->app->getIdentity();

        return ((bool) ($identity->guest ?? true) || (int) ($identity->id ?? 0) === 0)
            && max(0, (int) ($draft->account_user_id ?? 0)) === 0
            && $this->isRegistrationAllowed();
    }

    private function isRegistrationAllowed(): bool
    {
        return (int) ComponentHelper::getParams('com_users')->get('allowUserRegistration', 0) === 1;
    }

    private function loadFormLanguages(): void
    {
        $language = $this->app->getLanguage();
        $language->load('com_contact', JPATH_SITE, null, true);
        $language->load('com_users', JPATH_SITE, null, true);
    }
}
