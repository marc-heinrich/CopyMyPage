<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\Component\CopyMyPage\Site\Repository\ProfileAddressRepository;
use Joomla\Component\CopyMyPage\Site\ValueObject\AddressData;
use Joomla\Database\DatabaseInterface;

/**
 * Owns the current user's private CopyMyPage profile address.
 */
final class ProfileAddressService
{
    /**
     * CopyMyPage-owned validation state prefix.
     */
    private const STATE_KEY = 'com_copymypage.edit.profile.address.data';

    /**
     * Explicit browser-writable fields and their storage limits.
     *
     * @var array<string, int>
     */
    private const ADDRESS_FIELDS = [
        'street'       => 500,
        'house_number' => 100,
        'postcode'     => 100,
        'city'         => 100,
        'country_code' => 2,
        'region_code'  => 16,
    ];

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly FormFactoryInterface $formFactory,
        private readonly ProfileAddressRepository $repository,
        private readonly AddressCatalogService $catalogue
    ) {
    }

    /**
     * Prepare the isolated profile-address form for the authenticated user.
     */
    public function prepareForm(User $user): Form
    {
        $this->assertCurrentUser($user);

        $this->app->getLanguage()->load('com_contact', JPATH_SITE, null, true);
        Form::addFormPath(JPATH_SITE . '/components/com_copymypage/forms');

        $form = $this->formFactory->createForm(
            'com_copymypage.profile.address',
            ['control' => 'jform']
        );

        if (!$form->loadFile('profile_address')) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_FORM'), 500);
        }

        $address = $this->getAddress($user);
        unset($address['exists']);
        $form->bind($address);

        $pendingData = $this->app->getUserState($this->getStateKey($user), []);

        if (\is_array($pendingData) && $pendingData !== []) {
            $form->bind($this->projectSubmittedData($pendingData));
        }

        return $form;
    }

    /**
     * Return the current user's managed address without exposing storage ids.
     *
     * @return array<string, bool|string>
     */
    public function getAddress(User $user): array
    {
        $this->assertCurrentUser($user);
        $this->app->getLanguage()->load('com_contact', JPATH_SITE, null, true);

        $address = $this->getAddressData($user);
        $result  = ['exists' => $address !== null];
        $values  = $address?->toProfileFormData() ?? [];

        if ($address !== null) {
            $country = $this->catalogue->resolveCountry(
                $address->countryCode !== '' ? $address->countryCode : $address->countryName
            );
            $region = $country !== null
                ? $this->catalogue->resolveRegion(
                    $country['code'],
                    $address->regionCode !== '' ? $address->regionCode : $address->region
                )
                : null;

            $values['country_code'] = $country['code'] ?? $address->countryCode;
            $values['region_code']  = $region['code'] ?? $address->regionCode;

            if (
                $values['house_number'] === ''
                && \in_array($values['country_code'], ['AT', 'CH', 'DE'], true)
                && preg_match(
                    '/^(.+?)\s+(\d+[\p{L}]?(?:\s*[-\/]\s*\d+[\p{L}]?)?)$/u',
                    $values['street'],
                    $matches
                ) === 1
            ) {
                $values['street']       = trim($matches[1]);
                $values['house_number'] = trim($matches[2]);
            }

            $result['country'] = $country['name'] ?? $address->countryName;
            $result['region']  = $region['name'] ?? $address->region;
        }

        foreach (array_keys(self::ADDRESS_FIELDS) as $fieldName) {
            $result[$fieldName] = trim((string) ($values[$fieldName] ?? ''));
        }

        return $result;
    }

    /**
     * Return component-neutral address data for approved integration boundaries.
     */
    public function getAddressData(User $user): ?AddressData
    {
        $this->assertCurrentUser($user);

        return $this->repository->findForUser((int) $user->id);
    }

    /**
     * Reduce untrusted form input to scalar address values only.
     *
     * @param   array<string, mixed>  $data  Untrusted request data.
     *
     * @return array<string, string>
     */
    public function projectSubmittedData(array $data): array
    {
        $result = [];

        foreach (array_keys(self::ADDRESS_FIELDS) as $fieldName) {
            $value = $data[$fieldName] ?? '';

            $result[$fieldName] = \is_scalar($value) ? trim((string) $value) : '';
        }

        return $result;
    }

    /**
     * Filter and validate the isolated address form.
     *
     * @param   array<string, mixed>  $data  Untrusted request data.
     *
     * @return array{data: array<string, string>, errors: array<int, \Throwable|string>}
     */
    public function validate(Form $form, array $data): array
    {
        $projected = $this->projectSubmittedData($data);
        $filtered  = $form->filter($projected);
        $filtered  = $this->projectSubmittedData(\is_array($filtered) ? $filtered : []);
        $isValid   = $form->validate($filtered);
        $errors    = $form->getErrors();

        foreach (self::ADDRESS_FIELDS as $fieldName => $maximumLength) {
            if (mb_strlen($filtered[$fieldName]) <= $maximumLength) {
                continue;
            }

            $field    = $form->getField($fieldName);
            $label    = $field !== false ? Text::_((string) $field->title) : $fieldName;
            $errors[] = new \RuntimeException(
                Text::sprintf(
                    'COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_TOO_LONG',
                    $label,
                    $maximumLength
                )
            );
            $isValid = false;
        }

        $country = null;

        if ($filtered['country_code'] !== '') {
            $country = $this->catalogue->resolveCountry($filtered['country_code']);

            if ($country === null) {
                $errors[] = new \RuntimeException(
                    Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_COUNTRY')
                );
                $isValid = false;
            } else {
                $filtered['country_code'] = $country['code'];
            }
        }

        if ($filtered['region_code'] !== '' && $country !== null) {
            $region = $this->catalogue->resolveRegion(
                $country['code'],
                $filtered['region_code']
            );

            if ($region === null) {
                $errors[] = new \RuntimeException(
                    Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_REGION')
                );
                $isValid = false;
            } else {
                $filtered['region_code'] = $region['code'];
            }
        }

        if (!$isValid && $errors === []) {
            $errors[] = new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_FORM'));
        }

        return [
            'data'   => $filtered,
            'errors' => $isValid ? [] : $errors,
        ];
    }

    /**
     * Persist the user's current address in CopyMyPage-owned storage.
     *
     * @param   array<string, mixed>  $data  Validated address values.
     */
    public function save(User $user, array $data): int
    {
        $this->assertCurrentUser($user);
        $address = $this->projectSubmittedData($data);
        $country = $this->catalogue->resolveCountry($address['country_code']);

        if ($country === null) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_COUNTRY'));
        }

        $region = $address['region_code'] !== ''
            ? $this->catalogue->resolveRegion($country['code'], $address['region_code'])
            : null;

        if ($address['region_code'] !== '' && $region === null) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_ERROR_REGION'));
        }

        $this->db->transactionStart();

        try {
            $this->lockCurrentUser($user);
            $existing   = $this->repository->findForUser((int) $user->id);
            $dataObject = new AddressData(
                $address['street'],
                $existing?->addressLine2 ?? '',
                $address['house_number'],
                $address['postcode'],
                $address['city'],
                $region['name'] ?? '',
                $region['code'] ?? '',
                $country['code'],
                $country['name'],
                $existing?->label ?? '',
                $existing?->recipientFirstName ?? '',
                $existing?->recipientLastName ?? '',
                $existing?->company ?? '',
                $existing?->telephone ?? ''
            );
            $addressId  = $this->repository->saveForUser((int) $user->id, $dataObject);

            $this->db->transactionCommit();

            return $addressId;
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();
            Log::add($exception->getMessage(), Log::WARNING, 'com_copymypage.profile_address');

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_PROFILE_ADDRESS_SAVE_FAILED'),
                0,
                $exception
            );
        }
    }

    /**
     * Return localised region options for the authenticated profile form.
     *
     * @return array<string, string>
     */
    public function getRegions(string $countryCode): array
    {
        return $this->catalogue->getRegions($countryCode);
    }

    /**
     * Return a user-specific state key for validation redirects.
     */
    public function getStateKey(User $user): string
    {
        $this->assertCurrentUser($user);

        return self::STATE_KEY . '.' . (int) $user->id;
    }

    /**
     * Serialize creation for one user so repeated submissions cannot duplicate it.
     */
    private function lockCurrentUser(User $user): void
    {
        $userId = (int) $user->id;
        $query  = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = ' . $userId);

        $lockedUserId = (int) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult();

        if ($lockedUserId !== $userId) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }
    }

    /**
     * Ensure callers cannot substitute another Joomla user object.
     */
    private function assertCurrentUser(User $user): void
    {
        $identity = $this->app->getIdentity();

        if (
            !$identity instanceof User
            || (int) $identity->id === 0
            || (bool) $identity->guest
            || (int) $identity->id !== (int) $user->id
        ) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
