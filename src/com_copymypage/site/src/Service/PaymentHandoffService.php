<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.19
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;

/**
 * Issues one-use, session-bound capabilities for starting a payment provider.
 *
 * @since  0.0.19
 */
final class PaymentHandoffService
{
    private const SESSION_KEY = 'com_copymypage.payment_handoffs';
    private const TOKEN_TTL   = 600;

    public function __construct(private readonly CMSWebApplicationInterface $app)
    {
    }

    public function issue(int $bookingId): string
    {
        if ($bookingId < 1) {
            throw new \InvalidArgumentException('A valid booking ID is required.');
        }

        $token    = bin2hex(random_bytes(32));
        $now      = time();
        $handoffs = $this->getCurrentHandoffs($now);
        $handoffs[$bookingId] = [
            'expiresAt' => $now + self::TOKEN_TTL,
            'tokenHash' => hash('sha256', $token),
        ];

        $this->app->getSession()->set(self::SESSION_KEY, $handoffs);

        return $token;
    }

    public function consume(int $bookingId, string $token): bool
    {
        if (
            $bookingId < 1
            || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
        ) {
            return false;
        }

        $now      = time();
        $handoffs = $this->getCurrentHandoffs($now);
        $handoff  = $handoffs[$bookingId] ?? null;

        // Consume before the external provider is invoked so a refresh cannot reuse it.
        unset($handoffs[$bookingId]);
        $this->app->getSession()->set(self::SESSION_KEY, $handoffs);

        return \is_array($handoff)
            && (int) ($handoff['expiresAt'] ?? 0) >= $now
            && hash_equals((string) ($handoff['tokenHash'] ?? ''), hash('sha256', $token));
    }

    /** @return array<int, array{expiresAt: int, tokenHash: string}> */
    private function getCurrentHandoffs(int $now): array
    {
        $stored   = $this->app->getSession()->get(self::SESSION_KEY, []);
        $handoffs = [];

        foreach (\is_array($stored) ? $stored : [] as $bookingId => $handoff) {
            $bookingId = (int) $bookingId;

            if (
                $bookingId < 1
                || !\is_array($handoff)
                || (int) ($handoff['expiresAt'] ?? 0) < $now
                || preg_match('/^[a-f0-9]{64}$/D', (string) ($handoff['tokenHash'] ?? '')) !== 1
            ) {
                continue;
            }

            $handoffs[$bookingId] = [
                'expiresAt' => (int) $handoff['expiresAt'],
                'tokenHash' => (string) $handoff['tokenHash'],
            ];
        }

        return $handoffs;
    }
}
