<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Contract;

\defined('_JEXEC') or die;

use Joomla\Component\CopyMyPage\Site\ValueObject\AddressData;

/**
 * Maps canonical CopyMyPage address data to one component workflow.
 *
 * Persisting a projection remains the responsibility of the target component's
 * supported model, service, event, or API. Implementations must not write to a
 * third-party table directly.
 */
interface AddressProjectionAdapterInterface
{
    /**
     * Return the stable target workflow name handled by this adapter.
     */
    public function getTarget(): string;

    /**
     * Build target input without changing either component's state.
     *
     * @param   array<string, mixed>  $context  Target-specific, server-controlled context.
     *
     * @return array<string, mixed>
     */
    public function project(AddressData $address, array $context = []): array;
}
