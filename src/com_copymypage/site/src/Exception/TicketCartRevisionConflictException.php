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
 * Signals that a ticket-cart mutation was based on an outdated cart state.
 */
final class TicketCartRevisionConflictException extends \DomainException
{
}
