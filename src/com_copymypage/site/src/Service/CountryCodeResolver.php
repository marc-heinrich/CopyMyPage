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
 * Resolves localised country names to stable ISO 3166-1 alpha-2 codes.
 */
final class CountryCodeResolver
{
    /**
     * Non-ISO region identifiers exposed by CLDR's region data.
     */
    private const EXCLUDED_CODES = [
        'AC', 'CP', 'CQ', 'DG', 'EA', 'EU', 'EZ', 'IC', 'QO', 'TA', 'UN', 'XA', 'XB', 'XK', 'ZZ',
    ];

    /**
     * Resolved country maps by locale.
     *
     * @var array<string, array<string, string>>
     */
    private array $countries = [];

    private readonly string $languageTag;

    public function __construct(Language|string|null $language)
    {
        $languageTag = $language instanceof Language ? $language->getTag() : (string) $language;
        $this->languageTag = trim($languageTag) !== '' ? trim($languageTag) : 'en-GB';
    }

    /**
     * Resolve a country name or alpha-2 code to canonical data.
     *
     * @return array{code: string, name: string}|null
     */
    public function resolve(string $value): ?array
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $locales = array_values(
            array_unique(
                [
                    $this->languageTag,
                    'en-GB',
                    'de-DE',
                    'es-ES',
                    'fr-FR',
                    'it-IT',
                ]
            )
        );
        $currentCountries = $this->getCountries($locales[0]);
        $candidateCode    = strtoupper($value);

        if (
            preg_match('/^[A-Z]{2}$/', $candidateCode) === 1
            && isset($currentCountries[$candidateCode])
        ) {
            return [
                'code' => $candidateCode,
                'name' => $currentCountries[$candidateCode],
            ];
        }

        $needle = $this->normaliseName($value);

        foreach ($locales as $locale) {
            foreach ($this->getCountries($locale) as $code => $name) {
                if ($this->normaliseName($name) !== $needle) {
                    continue;
                }

                return [
                    'code' => $code,
                    'name' => $currentCountries[$code] ?? $name,
                ];
            }
        }

        return null;
    }

    /**
     * Return a localised ISO country map from PHP's ICU data.
     *
     * @return array<string, string>
     */
    public function getCountries(?string $locale = null): array
    {
        $locale = str_replace('-', '_', trim($locale ?? $this->languageTag));

        if (isset($this->countries[$locale])) {
            return $this->countries[$locale];
        }

        $language = strtolower((string) strtok($locale, '_')) ?: 'en';
        $result   = [];

        foreach (array_values(array_unique([$language, $locale])) as $bundleLocale) {
            $bundle  = \ResourceBundle::create($bundleLocale, 'ICUDATA-region');
            $regions = $bundle instanceof \ResourceBundle ? $bundle->get('Countries') : null;

            if (!$regions instanceof \Traversable) {
                continue;
            }

            foreach ($regions as $code => $name) {
                $code = strtoupper((string) $code);
                $name = trim((string) $name);

                if (
                    preg_match('/^[A-Z]{2}$/', $code) !== 1
                    || in_array($code, self::EXCLUDED_CODES, true)
                    || $name === ''
                ) {
                    continue;
                }

                $result[$code] = $name;
            }
        }

        return $this->countries[$locale] = $result;
    }

    /**
     * Normalise a country label for case-insensitive comparison.
     */
    private function normaliseName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }
}
