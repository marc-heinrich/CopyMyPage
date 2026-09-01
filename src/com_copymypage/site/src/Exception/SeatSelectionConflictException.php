<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Exception;

\defined('_JEXEC') or die;

/**
 * Signals an authoritative seating conflict without exposing its internal cause.
 */
final class SeatSelectionConflictException extends \DomainException
{
}
