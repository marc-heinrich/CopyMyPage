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
 * One immutable physical table and its individually bookable seats.
 */
final readonly class LayoutTableDefinition
{
    /**
     * @param   list<SeatDefinition>  $seats  Seats ordered around this table.
     */
    public function __construct(
        public string $code,
        public string $number,
        public string $label,
        public string $shape,
        public int $x,
        public int $y,
        public int $width,
        public int $height,
        public int $rotation,
        public int $sortOrder,
        public array $seats
    ) {
    }

    /**
     * Return the canonical JSON representation used for hashing.
     *
     * @return array<string, int|string|list<array<string, int|string>>>
     */
    public function toArray(): array
    {
        return [
            'code'      => $this->code,
            'height'    => $this->height,
            'label'     => $this->label,
            'number'    => $this->number,
            'rotation'  => $this->rotation,
            'seats'     => array_map(
                static fn(SeatDefinition $seat): array => $seat->toArray(),
                $this->seats
            ),
            'shape'     => $this->shape,
            'sortOrder' => $this->sortOrder,
            'width'     => $this->width,
            'x'         => $this->x,
            'y'         => $this->y,
        ];
    }
}
