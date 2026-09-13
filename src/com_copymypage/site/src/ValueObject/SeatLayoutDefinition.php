<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\ValueObject;

\defined('_JEXEC') or die;

/**
 * Validated immutable seating-layout definition imported from bundled JSON.
 */
final readonly class SeatLayoutDefinition
{
    /**
     * @param   list<array<string, int|string>>                          $areas              Non-interactive stage and aisle geometry.
     * @param   list<LayoutTableDefinition>                              $tables             Physical tables in display order.
     * @param   array{seatCodes: list<string>, tableCodes: list<string>} $hotlineAllocation  Validated Hotline references.
     */
    public function __construct(
        public int $schemaVersion,
        public string $alias,
        public int $version,
        public string $title,
        public int $width,
        public int $height,
        public array $areas,
        public array $tables,
        public array $hotlineAllocation,
        public string $hash
    ) {
    }

    /**
     * Return the number of individually bookable seats in this version.
     */
    public function getSeatCount(): int
    {
        return array_sum(
            array_map(
                static fn(LayoutTableDefinition $table): int => \count($table->seats),
                $this->tables
            )
        );
    }

    /**
     * Return the canonical JSON representation used for hashing.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = [
            'alias'         => $this->alias,
            'areas'         => $this->areas,
            'canvas'        => [
                'height' => $this->height,
                'width'  => $this->width,
            ],
            'schemaVersion' => $this->schemaVersion,
            'tables'        => array_map(
                static fn(LayoutTableDefinition $table): array => $table->toArray(),
                $this->tables
            ),
            'title'         => $this->title,
            'version'       => $this->version,
        ];

        if (
            $this->hotlineAllocation['tableCodes'] !== []
            || $this->hotlineAllocation['seatCodes'] !== []
        ) {
            $definition['allocations'] = [
                'hotline' => $this->buildHotlineEntries(),
            ];
        }

        return $definition;
    }

    /**
     * Return the canonical, input-compatible Hotline allocation entries.
     *
     * @return list<array{all?: true, seats?: list<string>, table: string}>
     */
    private function buildHotlineEntries(): array
    {
        $tableCodes = array_fill_keys($this->hotlineAllocation['tableCodes'], true);
        $seatCodes  = array_fill_keys($this->hotlineAllocation['seatCodes'], true);
        $entries    = [];

        foreach ($this->tables as $table) {
            if (isset($tableCodes[$table->code])) {
                $entries[] = [
                    'all'   => true,
                    'table' => $table->code,
                ];

                continue;
            }

            $seatNumbers = [];

            foreach ($table->seats as $seat) {
                if (isset($seatCodes[$seat->code])) {
                    $seatNumbers[] = $seat->number;
                }
            }

            if ($seatNumbers !== []) {
                $entries[] = [
                    'seats' => $seatNumbers,
                    'table' => $table->code,
                ];
            }
        }

        return $entries;
    }
}
