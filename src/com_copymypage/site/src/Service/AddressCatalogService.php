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

use Joomla\CMS\Language\Language;

/**
 * Provides local, versioned country and first-level region catalogues.
 */
final class AddressCatalogService
{
    /**
     * Bundled CLDR locales keyed by Joomla language prefix.
     */
    private const SUPPORTED_LOCALES = [
        'de' => 'de-DE',
        'en' => 'en-GB',
        'es' => 'es-ES',
        'fr' => 'fr-FR',
        'it' => 'it-IT',
    ];

    /**
     * Decoded region maps by bundled locale.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private array $regions = [];

    private readonly string $languageTag;

    public function __construct(
        Language|string|null $language,
        private readonly CountryCodeResolver $countryResolver
    ) {
        $languageTag = $language instanceof Language ? $language->getTag() : (string) $language;
        $this->languageTag = trim($languageTag) !== '' ? trim($languageTag) : 'en-GB';
    }

    /**
     * Return localised ISO 3166-1 alpha-2 country options.
     *
     * @return array<string, string>
     */
    public function getCountries(): array
    {
        return $this->sortByLabel($this->countryResolver->getCountries($this->languageTag));
    }

    /**
     * Resolve a country code or known localised label.
     *
     * @return array{code: string, name: string}|null
     */
    public function resolveCountry(string $value): ?array
    {
        return $this->countryResolver->resolve($value);
    }

    /**
     * Return localised first-level regions for one country.
     *
     * @return array<string, string>
     */
    public function getRegions(string $countryCode): array
    {
        $country = $this->countryResolver->resolve($countryCode);

        if ($country === null) {
            return [];
        }

        $catalogue = $this->loadRegions($this->getBundledLocale($this->languageTag));

        return $this->sortByLabel($catalogue[$country['code']] ?? []);
    }

    /**
     * Resolve a region code or known label within its country.
     *
     * @return array{code: string, name: string}|null
     */
    public function resolveRegion(string $countryCode, string $value): ?array
    {
        $value   = trim($value);
        $country = $this->countryResolver->resolve($countryCode);

        if ($value === '' || $country === null) {
            return null;
        }

        $currentRegions = $this->getRegions($country['code']);
        $candidateCode  = strtoupper($value);

        if (isset($currentRegions[$candidateCode])) {
            return [
                'code' => $candidateCode,
                'name' => $currentRegions[$candidateCode],
            ];
        }

        $needle  = $this->normaliseName($value);
        $locales = array_values(
            array_unique(
                [
                    $this->getBundledLocale($this->languageTag),
                    'en-GB',
                    'de-DE',
                    'es-ES',
                    'fr-FR',
                    'it-IT',
                ]
            )
        );

        foreach ($locales as $locale) {
            foreach ($this->loadRegions($locale)[$country['code']] ?? [] as $code => $name) {
                if ($this->normaliseName($name) !== $needle) {
                    continue;
                }

                return [
                    'code' => $code,
                    'name' => $currentRegions[$code] ?? $name,
                ];
            }
        }

        return null;
    }

    /**
     * Load and validate one bundled CLDR-derived region map.
     *
     * @return array<string, array<string, string>>
     */
    private function loadRegions(string $locale): array
    {
        if (isset($this->regions[$locale])) {
            return $this->regions[$locale];
        }

        $path = JPATH_SITE
            . '/components/com_copymypage/data/address-catalog/regions.'
            . $locale
            . '.json';
        $json = is_file($path) ? file_get_contents($path) : false;

        if (!\is_string($json) || $json === '') {
            return $this->regions[$locale] = [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->regions[$locale] = [];
        }

        $countryGroups = \is_array($decoded) && \is_array($decoded['regions'] ?? null)
            ? $decoded['regions']
            : [];
        $result = [];

        foreach ($countryGroups as $countryCode => $entries) {
            $countryCode = strtoupper(trim((string) $countryCode));

            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1 || !\is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!\is_array($entry)) {
                    continue;
                }

                $code = strtoupper(trim((string) ($entry['code'] ?? '')));
                $name = trim((string) ($entry['name'] ?? ''));

                if (
                    preg_match('/^[A-Z]{2}-[A-Z0-9]{1,6}$/', $code) !== 1
                    || !str_starts_with($code, $countryCode . '-')
                    || $name === ''
                ) {
                    continue;
                }

                $result[$countryCode][$code] = $name;
            }
        }

        return $this->regions[$locale] = $result;
    }

    /**
     * Map any Joomla language tag to one bundled catalogue locale.
     */
    private function getBundledLocale(string $languageTag): string
    {
        $prefix = strtolower(substr(trim($languageTag), 0, 2));

        return self::SUPPORTED_LOCALES[$prefix] ?? 'en-GB';
    }

    /**
     * Sort labels with the current locale while retaining stable codes as values.
     *
     * @param   array<string, string>  $values
     *
     * @return array<string, string>
     */
    private function sortByLabel(array $values): array
    {
        $collator = new \Collator(str_replace('-', '_', $this->languageTag));

        uasort(
            $values,
            static fn(string $left, string $right): int => (int) $collator->compare($left, $right)
        );

        return $values;
    }

    /**
     * Normalise a label for case-insensitive legacy matching.
     */
    private function normaliseName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }
}
