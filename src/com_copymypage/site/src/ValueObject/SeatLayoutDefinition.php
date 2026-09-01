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
     * @param   list<array<string, int|string>>  $areas   Non-interactive stage and aisle geometry.
     * @param   list<LayoutTableDefinition>      $tables  Physical tables in display order.
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
        return [
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
    }
}
