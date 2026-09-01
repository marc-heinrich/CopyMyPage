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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\CopyMyPage\Site\Exception\TicketCartRevisionConflictException;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Owns the current session cart, its lifecycle, revision and transaction boundary.
 */
final class TicketCartContextService
{
    public const STATUS_ACTIVE = 0;

    public const STATUS_CONVERTED = 1;

    public const STATUS_EXPIRED = 2;

    public const STATUS_RELEASED = 3;

    private const DEFAULT_RESERVATION_MINUTES = 15;

    private const SESSION_TOKEN_KEY = 'com_copymypage.ticket_cart_token';

    private const SESSION_SEAT_SELECTION_CART_ID_KEY = 'com_copymypage.ticket_cart_seat_selection_id';

    private bool $expiredCartsPurged = false;

    private bool $transactionActive = false;

    private bool $sessionTokenCreatedInTransaction = false;

    private bool $removeSessionTokenOnCommit = false;

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db
    ) {
    }

    /**
     * Return the active, unexpired cart associated with the current Joomla session.
     */
    public function getActiveCart(): ?object
    {
        $this->purgeExpiredCarts();

        return $this->loadActiveCart(false);
    }

    /**
     * Lock the active, unexpired current cart inside an existing transaction.
     */
    public function getActiveCartForUpdate(): ?object
    {
        $this->assertTransactionActive();

        return $this->loadActiveCart(true);
    }

    /**
     * Lock or create the current session cart inside an existing transaction.
     */
    public function ensureActiveCartForUpdate(): object
    {
        $this->assertTransactionActive();
        $cart = $this->getActiveCartForUpdate();

        if ($cart !== null) {
            return $cart;
        }

        $session = $this->app->getSession();
        $now     = $this->now();
        $userId  = max(0, (int) ($this->app->getIdentity()->id ?? 0));

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $row       = (object) [
                'token_hash' => $tokenHash,
                'user_id'    => $userId,
                'status'     => self::STATUS_ACTIVE,
                'booking_id' => null,
                'revision'   => 0,
                'expires_at' => $this->getNewExpiry(),
                'created'    => $now,
                'modified'   => $now,
            ];

            try {
                $this->db->insertObject('#__copymypage_ticket_carts', $row);
                $row->id = (int) $this->db->insertid();
                $session->set(self::SESSION_TOKEN_KEY, $token);
                $this->sessionTokenCreatedInTransaction = true;

                return $row;
            } catch (\Throwable $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException(Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_SAVE'));
    }

    public function assertExpectedRevision(object $cart, int $expectedRevision): void
    {
        if ($expectedRevision < 0 || $expectedRevision !== $this->getRevision($cart)) {
            throw new TicketCartRevisionConflictException(
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_CONFLICT')
            );
        }
    }

    public function assertInitialRevision(int $expectedRevision): void
    {
        if ($expectedRevision !== 0) {
            throw new TicketCartRevisionConflictException(
                Text::_('COM_COPYMYPAGE_TICKET_SELECTION_ERROR_CONFLICT')
            );
        }
    }

    public function getRevision(?object $cart): int
    {
        return $cart === null ? 0 : max(0, (int) ($cart->revision ?? 0));
    }

    /**
     * Keep the ticket-selection return path available after the seat-selection step.
     */
    public function markSeatSelectionStarted(?object $cart): void
    {
        $cartId = max(0, (int) ($cart->id ?? 0));

        if ($cartId > 0) {
            $this->app->getSession()->set(self::SESSION_SEAT_SELECTION_CART_ID_KEY, $cartId);
        }
    }

    /**
     * Return whether the current cart has entered the seat-selection step.
     */
    public function hasSeatSelectionStarted(?object $cart): bool
    {
        $cartId = max(0, (int) ($cart->id ?? 0));

        return $cartId > 0
            && (int) $this->app->getSession()->get(self::SESSION_SEAT_SELECTION_CART_ID_KEY, 0) === $cartId;
    }

    /**
     * Extend a changed active cart and advance its revision exactly once.
     */
    public function advanceCart(int $cartId): void
    {
        $this->assertTransactionActive();
        $userId    = max(0, (int) ($this->app->getIdentity()->id ?? 0));
        $expiresAt = $this->getNewExpiry();
        $modified  = $this->now();
        $query     = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('user_id') . ' = :userId')
            ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
            ->set($this->db->quoteName('expires_at') . ' = :expiresAt')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':expiresAt', $expiresAt, ParameterType::STRING)
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Release a changed cart and remove its session token only after commit.
     */
    public function releaseCart(int $cartId): void
    {
        $this->assertTransactionActive();
        $this->deleteCustomerDraft($cartId);
        $modified = $this->now();
        $query    = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('status') . ' = ' . self::STATUS_RELEASED)
            ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
        $this->removeSessionTokenOnCommit = true;
    }

    /**
     * Convert the active cart into a DPCalendar booking and retain the Step-4 acceptance audit.
     *
     * The session token is removed only after the surrounding transaction commits.
     */
    public function convertCart(
        int $cartId,
        int $bookingId,
        string $paymentProvider,
        string $termsAcceptedAt,
        string $termsSnapshot
    ): void {
        $this->assertTransactionActive();

        if ($cartId < 1 || $bookingId < 1 || $termsAcceptedAt === '' || $termsSnapshot === '') {
            throw new \InvalidArgumentException('A complete converted ticket cart is required.');
        }

        $this->deleteCustomerDraft($cartId);
        $modified = $this->now();
        $query    = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('status') . ' = ' . self::STATUS_CONVERTED)
            ->set($this->db->quoteName('booking_id') . ' = :bookingId')
            ->set($this->db->quoteName('payment_provider') . ' = :paymentProvider')
            ->set($this->db->quoteName('terms_accepted_at') . ' = :termsAcceptedAt')
            ->set($this->db->quoteName('terms_snapshot') . ' = :termsSnapshot')
            ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('id') . ' = :cartId')
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->bind(':bookingId', $bookingId, ParameterType::INTEGER)
            ->bind(':paymentProvider', $paymentProvider, ParameterType::STRING)
            ->bind(':termsAcceptedAt', $termsAcceptedAt, ParameterType::STRING)
            ->bind(':termsSnapshot', $termsSnapshot, ParameterType::STRING)
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':cartId', $cartId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();

        if ($this->db->getAffectedRows() !== 1) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_ORDER_REVIEW_ERROR_CONFLICT'));
        }

        $this->removeSessionTokenOnCommit = true;
    }

    public function beginTransaction(): void
    {
        if ($this->transactionActive) {
            throw new \LogicException('A ticket-cart transaction is already active.');
        }

        $this->db->transactionStart();
        $this->transactionActive                = true;
        $this->sessionTokenCreatedInTransaction = false;
        $this->removeSessionTokenOnCommit       = false;
    }

    public function commitTransaction(): void
    {
        $this->assertTransactionActive();
        $removeSessionToken = $this->removeSessionTokenOnCommit;

        $this->db->transactionCommit();
        $this->resetTransactionState();

        if ($removeSessionToken) {
            $this->removeSessionToken();
        }
    }

    public function rollbackTransaction(): void
    {
        if (!$this->transactionActive) {
            return;
        }

        $removeCreatedToken = $this->sessionTokenCreatedInTransaction;

        try {
            $this->db->transactionRollback();
        } finally {
            $this->resetTransactionState();
        }

        if ($removeCreatedToken) {
            $this->removeSessionToken();
        }
    }

    /**
     * Mark all elapsed carts expired once per request without extending them.
     */
    public function purgeExpiredCarts(): void
    {
        if ($this->expiredCartsPurged) {
            return;
        }

        $now      = $this->now();
        $modified = $now;
        $expiredCarts = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->where($this->db->quoteName('expires_at') . ' <= ' . $this->db->quote($now));
        $delete = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__copymypage_ticket_customers'))
            ->where($this->db->quoteName('cart_id') . ' IN (' . $expiredCarts . ')');
        $query    = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_ticket_carts'))
            ->set($this->db->quoteName('status') . ' = ' . self::STATUS_EXPIRED)
            ->set($this->db->quoteName('revision') . ' = ' . $this->db->quoteName('revision') . ' + 1')
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->where($this->db->quoteName('expires_at') . ' <= :now')
            ->bind(':modified', $modified, ParameterType::STRING)
            ->bind(':now', $now, ParameterType::STRING);

        $this->expiredCartsPurged = true;

        try {
            $this->db->setQuery($delete)->execute();
            $this->db->setQuery($query)->execute();
        } catch (\Throwable) {
            // Expiry is enforced again by every active-cart and hold query. The
            // status update is therefore housekeeping and must not abort a live
            // cart mutation when its status/expiry index is concurrently locked.
        }
    }

    public function getReservationMinutes(): int
    {
        $minutes = (int) ComponentHelper::getParams('com_copymypage')->get(
            'ticket_reservation_minutes',
            self::DEFAULT_RESERVATION_MINUTES
        );

        return min(60, max(5, $minutes));
    }

    public function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function loadActiveCart(bool $forUpdate): ?object
    {
        $tokenHash = $this->getSessionTokenHash();

        if ($tokenHash === '') {
            return null;
        }

        $now   = $this->now();
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__copymypage_ticket_carts'))
            ->where($this->db->quoteName('token_hash') . ' = ' . $this->db->quote($tokenHash))
            ->where($this->db->quoteName('status') . ' = ' . self::STATUS_ACTIVE)
            ->where($this->db->quoteName('expires_at') . ' > ' . $this->db->quote($now));

        $sql  = (string) $query . ($forUpdate ? ' FOR UPDATE' : '');
        $cart = $this->db->setQuery($sql)->loadObject();

        if (!\is_object($cart)) {
            $this->removeSessionToken();

            return null;
        }

        return $cart;
    }

    private function getSessionTokenHash(): string
    {
        $token = trim((string) $this->app->getSession()->get(self::SESSION_TOKEN_KEY, ''));

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return '';
        }

        return hash('sha256', $token);
    }

    private function getNewExpiry(): string
    {
        return gmdate('Y-m-d H:i:s', time() + ($this->getReservationMinutes() * 60));
    }

    private function deleteCustomerDraft(int $cartId): void
    {
        if ($cartId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__copymypage_ticket_customers'))
            ->where($this->db->quoteName('cart_id') . ' = :cartId')
            ->bind(':cartId', $cartId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
    }

    private function removeSessionToken(): void
    {
        $session = $this->app->getSession();
        $session->remove(self::SESSION_TOKEN_KEY);
        $session->remove(self::SESSION_SEAT_SELECTION_CART_ID_KEY);
    }

    private function assertTransactionActive(): void
    {
        if (!$this->transactionActive) {
            throw new \LogicException('A ticket-cart transaction is required.');
        }
    }

    private function resetTransactionState(): void
    {
        $this->transactionActive                = false;
        $this->sessionTokenCreatedInTransaction = false;
        $this->removeSessionTokenOnCommit       = false;
    }
}
