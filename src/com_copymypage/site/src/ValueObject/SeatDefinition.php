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
 * One immutable, individually bookable seat within a physical layout table.
 */
final readonly class SeatDefinition
{
    public function __construct(
        public string $code,
        public string $number,
        public int $x,
        public int $y,
        public int $sortOrder
    ) {
    }

    /**
     * Return the canonical JSON representation used for hashing.
     *
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'code'      => $this->code,
            'number'    => $this->number,
            'sortOrder' => $this->sortOrder,
            'x'         => $this->x,
            'y'         => $this->y,
        ];
    }
}
