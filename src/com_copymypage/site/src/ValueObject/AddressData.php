<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\ValueObject;

\defined('_JEXEC') or die;

/**
 * Component-neutral address data shared by profile and integration boundaries.
 */
final readonly class AddressData
{
    public function __construct(
        public string $addressLine1,
        public string $addressLine2,
        public string $houseNumber,
        public string $postalCode,
        public string $city,
        public string $region,
        public string $regionCode,
        public string $countryCode,
        public string $countryName,
        public string $label = '',
        public string $recipientFirstName = '',
        public string $recipientLastName = '',
        public string $company = '',
        public string $telephone = ''
    ) {
    }

    /**
     * Rebuild the neutral value object from a CopyMyPage address row.
     */
    public static function fromStorage(object $row): self
    {
        return new self(
            trim((string) ($row->address_line_1 ?? '')),
            trim((string) ($row->address_line_2 ?? '')),
            trim((string) ($row->house_number ?? '')),
            trim((string) ($row->postal_code ?? '')),
            trim((string) ($row->city ?? '')),
            trim((string) ($row->region ?? '')),
            strtoupper(trim((string) ($row->region_code ?? ''))),
            strtoupper(trim((string) ($row->country_code ?? ''))),
            trim((string) ($row->country_name ?? '')),
            trim((string) ($row->label ?? '')),
            trim((string) ($row->recipient_first_name ?? '')),
            trim((string) ($row->recipient_last_name ?? '')),
            trim((string) ($row->company ?? '')),
            trim((string) ($row->telephone ?? ''))
        );
    }

    /**
     * Map neutral values to the existing profile-form contract.
     *
     * @return array<string, string>
     */
    public function toProfileFormData(): array
    {
        return [
            'street'       => $this->addressLine1,
            'house_number' => $this->houseNumber,
            'postcode'     => $this->postalCode,
            'city'         => $this->city,
            'region_code'  => $this->regionCode,
            'country_code' => $this->countryCode,
        ];
    }

    /**
     * Map neutral values to CopyMyPage-owned storage columns.
     *
     * @return array<string, string>
     */
    public function toStorageData(): array
    {
        return [
            'label'                => $this->label,
            'recipient_first_name' => $this->recipientFirstName,
            'recipient_last_name'  => $this->recipientLastName,
            'company'              => $this->company,
            'address_line_1'       => $this->addressLine1,
            'address_line_2'       => $this->addressLine2,
            'house_number'         => $this->houseNumber,
            'postal_code'          => $this->postalCode,
            'city'                 => $this->city,
            'region'               => $this->region,
            'region_code'          => $this->regionCode,
            'country_code'         => $this->countryCode,
            'country_name'         => $this->countryName,
            'telephone'            => $this->telephone,
        ];
    }
}
