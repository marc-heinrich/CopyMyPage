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

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Creates and inspects CopyMyPage-owned event seat inventories.
 *
 * DPCalendar events and tickets are read-only integration boundaries. Every
 * mutation performed here is limited to the CopyMyPage seating tables.
 */
final class EventSeatInventoryService
{
    public const EVENT_STATUS_DRAFT = 0;

    public const EVENT_STATUS_READY = 1;

    public const SEAT_STATUS_AVAILABLE = 0;

    public const SEAT_STATUS_HELD = 1;

    public const SEAT_STATUS_BOOKED = 2;

    public const SEAT_STATUS_BLOCKED = 3;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly SeatLayoutService $layoutService
    ) {
    }

    /**
     * Return an operational summary without changing either component.
     *
     * @return array<string, mixed>
     */
    public function getEventSummary(int $eventId): array
    {
        $event       = $eventId > 0 ? $this->loadEvent($eventId, false) : null;
        $assignment  = $event === null ? null : $this->loadAssignment($eventId, false);
        $activeCarts = $event === null ? 0 : $this->getActiveCartQuantity($eventId);
        $tickets     = $event === null ? 0 : $this->getNativeTicketCount($eventId);
        $diagnostics = [];

        if ($event === null) {
            $diagnostics[] = $this->diagnostic(
                'danger',
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_EVENT_MISSING')
            );
        } else {
            $upcoming = $this->isUpcoming($event);

            if (!$upcoming) {
                $diagnostics[] = $this->diagnostic(
                    'danger',
                    Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_EVENT_STARTED')
                );
            }

            if ($assignment === null) {
                $diagnostics[] = $this->diagnostic(
                    'warning',
                    Text::_('COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_NO_ASSIGNMENT')
                );
            } else {
                if ((int) $assignment->materialized_count !== (int) $assignment->seat_count) {
                    $diagnostics[] = $this->diagnostic(
                        'danger',
                        Text::sprintf(
                            'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_INVENTORY_MISMATCH',
                            (int) $assignment->materialized_count,
                            (int) $assignment->seat_count
                        )
                    );
                } else {
                    $diagnostics[] = $this->diagnostic(
                        'success',
                        Text::sprintf(
                            'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_INVENTORY_COMPLETE',
                            (int) $assignment->seat_count
                        )
                    );
                }

                $capacity = $this->nullableInt($event->capacity ?? null);

                if ($capacity !== null && $capacity !== (int) $assignment->seat_count) {
                    $diagnostics[] = $this->diagnostic(
                        'warning',
                        Text::sprintf(
                            'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_CAPACITY_MISMATCH',
                            $capacity,
                            (int) $assignment->seat_count
                        )
                    );
                }
            }

            if ($activeCarts > 0) {
                $diagnostics[] = $this->diagnostic(
                    'warning',
                    Text::sprintf(
                        'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_ACTIVE_CARTS',
                        $activeCarts
                    )
                );
            }

            if ($tickets > 0) {
                $diagnostics[] = $this->diagnostic(
                    'warning',
                    Text::sprintf(
                        'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_NATIVE_TICKETS',
                        $tickets
                    )
                );
            }
        }

        return [
            'activeCartQuantity' => $activeCarts,
            'assignment'         => $this->normaliseAssignment($assignment),
            'diagnostics'        => $diagnostics,
            'event'              => $this->normaliseEvent($eventId, $event),
            'nativeTicketCount'  => $tickets,
        ];
    }

    /**
     * Assign one immutable layout version and materialise its seats as a draft.
     *
     * @return array<string, int|string>
     */
    public function assignDraft(int $eventId, int $layoutId, int $userId): array
    {
        if ($eventId <= 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
        }

        if ($layoutId <= 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_LAYOUT'));
        }

        $transactionOpen = false;
        $userId           = max(0, $userId);

        try {
            $this->db->transactionStart();
            $transactionOpen = true;

            // This must remain the first database read: it serialises seating
            // setup with all CopyMyPage cart mutations for the same event.
            $event = $this->loadEvent($eventId, true);

            if ($event === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
            }

            if (!$this->isUpcoming($event)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT_STARTED'));
            }

            $activeCartQuantity = $this->getActiveCartQuantity($eventId);

            if ($activeCartQuantity > 0) {
                throw new \DomainException(
                    Text::sprintf(
                        'COM_COPYMYPAGE_EVENT_SEATING_ERROR_ACTIVE_CARTS',
                        $activeCartQuantity
                    )
                );
            }

            $layout = $this->layoutService->getPublishedLayout($layoutId);

            if (
                $layout === null
                || (int) $layout['seatCount'] < 1
                || (int) $layout['seatCount'] > SeatLayoutService::MAX_SEATS
            ) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_LAYOUT'));
            }

            $assignment = $this->loadAssignment($eventId, true);

            if ($assignment !== null) {
                if ((int) $assignment->layout_id !== $layoutId) {
                    throw new \DomainException(
                        Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_LAYOUT_CONFLICT')
                    );
                }

                if ((int) $assignment->status !== self::EVENT_STATUS_DRAFT) {
                    throw new \DomainException(
                        Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_STATUS_CONFLICT')
                    );
                }
            }

            $seatIds        = $this->loadLayoutSeatIds($layoutId);
            $existingSeatIds = $this->loadEventSeatIds($eventId, true);

            if (
                \count($seatIds) !== (int) $layout['seatCount']
                || array_diff($existingSeatIds, $seatIds) !== []
            ) {
                throw new \RuntimeException(
                    Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY')
                );
            }

            $now     = gmdate('Y-m-d H:i:s');
            $created = false;

            if ($assignment === null) {
                $row = (object) [
                    'event_id'             => $eventId,
                    'layout_id'            => $layoutId,
                    'status'               => self::EVENT_STATUS_DRAFT,
                    'inventory_version'    => 0,
                    'assignment_locked_at' => null,
                    'created'              => $now,
                    'created_by'           => $userId,
                    'modified'             => $now,
                    'modified_by'          => $userId,
                    'ready_at'             => null,
                    'ready_by'             => null,
                ];
                $this->db->insertObject('#__copymypage_event_seating', $row);
                $created = true;
            }

            $missingSeatIds = array_values(array_diff($seatIds, $existingSeatIds));

            if ($missingSeatIds !== []) {
                $this->insertEventSeats($eventId, $missingSeatIds, $now, $userId);
            }

            if ($created || $missingSeatIds !== []) {
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__copymypage_event_seating'))
                    ->set(
                        $this->db->quoteName('inventory_version') . ' = '
                            . $this->db->quoteName('inventory_version') . ' + 1'
                    )
                    ->set($this->db->quoteName('modified') . ' = :modified')
                    ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
                    ->where($this->db->quoteName('event_id') . ' = :eventId')
                    ->bind(':modified', $now)
                    ->bind(':modifiedBy', $userId, ParameterType::INTEGER)
                    ->bind(':eventId', $eventId, ParameterType::INTEGER);
                $this->db->setQuery($query)->execute();
            }

            $stored = $this->loadAssignment($eventId, false);

            if (
                $stored === null
                || (int) $stored->layout_id !== $layoutId
                || (int) $stored->seat_count !== \count($seatIds)
                || (int) $stored->materialized_count !== \count($seatIds)
            ) {
                throw new \RuntimeException(
                    Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY')
                );
            }

            $this->db->transactionCommit();
            $transactionOpen = false;

            return [
                'eventId'          => $eventId,
                'inventoryVersion' => (int) $stored->inventory_version,
                'layoutId'         => $layoutId,
                'seatCount'        => (int) $stored->seat_count,
                'status'           => (int) $stored->status,
            ];
        } catch (\DomainException $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_SAVE'),
                0,
                $exception
            );
        }
    }

    /**
     * Mark a complete draft inventory ready for public seat reservations.
     *
     * Repeating this operation for an already ready, still valid inventory is
     * deliberately idempotent and does not advance its inventory version.
     *
     * @return array<string, int|string>
     */
    public function markReady(int $eventId, int $userId): array
    {
        if ($eventId <= 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
        }

        $transactionOpen = false;
        $userId           = max(0, $userId);

        try {
            $this->db->transactionStart();
            $transactionOpen = true;

            // This must remain the first database read. All seating mutations
            // serialise through the DPCalendar event without changing it.
            $event = $this->loadEvent($eventId, true);

            if ($event === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
            }

            if (!$this->isUpcoming($event)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT_STARTED'));
            }

            $assignment = $this->loadAssignment($eventId, true);

            if ($assignment === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
            }

            if (!\in_array(
                (int) $assignment->status,
                [self::EVENT_STATUS_DRAFT, self::EVENT_STATUS_READY],
                true
            )) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_STATUS_CONFLICT'));
            }

            [$layout, $seatRows] = $this->loadAndLockExactInventory($eventId, $assignment);
            $seatCount           = \count($seatRows);

            foreach ($seatRows as $seatRow) {
                $seatStatus = (int) $seatRow->status;

                if (!\in_array(
                    $seatStatus,
                    [
                        self::SEAT_STATUS_AVAILABLE,
                        self::SEAT_STATUS_HELD,
                        self::SEAT_STATUS_BOOKED,
                        self::SEAT_STATUS_BLOCKED,
                    ],
                    true
                )) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }

                if (
                    (int) $assignment->status === self::EVENT_STATUS_DRAFT
                    && !\in_array(
                        $seatStatus,
                        [self::SEAT_STATUS_AVAILABLE, self::SEAT_STATUS_BLOCKED],
                        true
                    )
                ) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }
            }

            if ((int) $assignment->status === self::EVENT_STATUS_READY) {
                $this->db->transactionCommit();
                $transactionOpen = false;

                return $this->buildMutationResult($assignment, $seatCount, 0);
            }

            $activeCartQuantity = $this->getActiveCartQuantity($eventId);

            if ($activeCartQuantity > 0) {
                throw new \DomainException(
                    Text::sprintf(
                        'COM_COPYMYPAGE_EVENT_SEATING_ERROR_ACTIVE_CARTS',
                        $activeCartQuantity
                    )
                );
            }

            $nativeTicketCount = $this->getNativeTicketCount($eventId);

            if ($nativeTicketCount > 0) {
                throw new \DomainException(
                    Text::sprintf(
                        'COM_COPYMYPAGE_EVENT_SEATING_DIAGNOSTIC_NATIVE_TICKETS',
                        $nativeTicketCount
                    )
                );
            }

            $capacityUsed = max(0, (int) ($event->capacity_used ?? 0));

            if ($capacityUsed > 0) {
                throw new \DomainException(
                    Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_CAPACITY_USED')
                );
            }

            $now = gmdate('Y-m-d H:i:s');
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__copymypage_event_seating'))
                ->set($this->db->quoteName('status') . ' = ' . self::EVENT_STATUS_READY)
                ->set(
                    $this->db->quoteName('inventory_version') . ' = '
                        . $this->db->quoteName('inventory_version') . ' + 1'
                )
                ->set($this->db->quoteName('assignment_locked_at') . ' = :lockedAt')
                ->set($this->db->quoteName('modified') . ' = :modified')
                ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
                ->set($this->db->quoteName('ready_at') . ' = :readyAt')
                ->set($this->db->quoteName('ready_by') . ' = :readyBy')
                ->where($this->db->quoteName('event_id') . ' = :eventId')
                ->where($this->db->quoteName('layout_id') . ' = :layoutId')
                ->where($this->db->quoteName('status') . ' = ' . self::EVENT_STATUS_DRAFT)
                ->bind(':lockedAt', $now)
                ->bind(':modified', $now)
                ->bind(':modifiedBy', $userId, ParameterType::INTEGER)
                ->bind(':readyAt', $now)
                ->bind(':readyBy', $userId, ParameterType::INTEGER)
                ->bind(':eventId', $eventId, ParameterType::INTEGER)
                ->bind(':layoutId', $layout['id'], ParameterType::INTEGER);
            $this->db->setQuery($query)->execute();

            $assignment->status            = self::EVENT_STATUS_READY;
            $assignment->inventory_version = (int) $assignment->inventory_version + 1;

            $this->db->transactionCommit();
            $transactionOpen = false;

            return $this->buildMutationResult($assignment, $seatCount, 1);
        } catch (\DomainException $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_SAVE'),
                0,
                $exception
            );
        }
    }

    /**
     * Block available seats from online sale without exposing the internal note.
     *
     * @param   array<int|string, mixed>  $seatIds  Layout seat IDs.
     *
     * @return array<string, int|string>
     */
    public function setBlockedSeats(int $eventId, array $seatIds, string $note, int $userId): array
    {
        $note = trim($note);

        if (mb_strlen($note, 'UTF-8') > 500) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
        }

        return $this->setSeatBlockingState(
            $eventId,
            $seatIds,
            self::SEAT_STATUS_BLOCKED,
            $note,
            $userId
        );
    }

    /**
     * Return blocked seats to the online inventory.
     *
     * @param   array<int|string, mixed>  $seatIds  Layout seat IDs.
     *
     * @return array<string, int|string>
     */
    public function setAvailableSeats(int $eventId, array $seatIds, int $userId): array
    {
        return $this->setSeatBlockingState(
            $eventId,
            $seatIds,
            self::SEAT_STATUS_AVAILABLE,
            '',
            $userId
        );
    }

    /**
     * Atomically apply one backend availability target to a complete seat batch.
     *
     * @param   array<int|string, mixed>  $rawSeatIds  Layout seat IDs.
     *
     * @return array<string, int|string>
     */
    private function setSeatBlockingState(
        int $eventId,
        array $rawSeatIds,
        int $targetStatus,
        string $note,
        int $userId
    ): array {
        if ($eventId <= 0) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
        }

        if (!\in_array($targetStatus, [self::SEAT_STATUS_AVAILABLE, self::SEAT_STATUS_BLOCKED], true)) {
            throw new \LogicException('Unsupported backend seat status.');
        }

        $seatIds         = $this->normaliseSeatIds($rawSeatIds);
        $transactionOpen = false;
        $userId           = max(0, $userId);

        try {
            $this->db->transactionStart();
            $transactionOpen = true;

            // Keep the shared lock order: DPCalendar event, event assignment,
            // then every materialised event seat in ascending seat-ID order.
            $event = $this->loadEvent($eventId, true);

            if ($event === null) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT'));
            }

            if (!$this->isUpcoming($event)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_EVENT_STARTED'));
            }

            $assignment = $this->loadAssignment($eventId, true);

            if ($assignment === null || !\in_array(
                (int) $assignment->status,
                [self::EVENT_STATUS_DRAFT, self::EVENT_STATUS_READY],
                true
            )) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_STATUS_CONFLICT'));
            }

            [, $seatRows] = $this->loadAndLockExactInventory($eventId, $assignment);
            $rowsBySeatId = [];

            foreach ($seatRows as $seatRow) {
                $status = (int) $seatRow->status;

                if (!\in_array(
                    $status,
                    [
                        self::SEAT_STATUS_AVAILABLE,
                        self::SEAT_STATUS_HELD,
                        self::SEAT_STATUS_BOOKED,
                        self::SEAT_STATUS_BLOCKED,
                    ],
                    true
                )) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }

                $rowsBySeatId[(int) $seatRow->seat_id] = $seatRow;
            }

            $changedSeatIds      = [];
            $newlyBlockedSeatIds = [];

            foreach ($seatIds as $seatId) {
                $seatRow = $rowsBySeatId[$seatId] ?? null;

                if (!\is_object($seatRow)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }

                $currentStatus = (int) $seatRow->status;

                if ($targetStatus === self::SEAT_STATUS_BLOCKED) {
                    if (!\in_array(
                        $currentStatus,
                        [self::SEAT_STATUS_AVAILABLE, self::SEAT_STATUS_BLOCKED],
                        true
                    )) {
                        throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                    }

                    if ($currentStatus === self::SEAT_STATUS_AVAILABLE) {
                        $newlyBlockedSeatIds[] = $seatId;
                    }

                    if (
                        $currentStatus === self::SEAT_STATUS_AVAILABLE
                        || (string) $seatRow->block_note !== $note
                    ) {
                        $changedSeatIds[] = $seatId;
                    }

                    continue;
                }

                if (!\in_array(
                    $currentStatus,
                    [self::SEAT_STATUS_AVAILABLE, self::SEAT_STATUS_BLOCKED],
                    true
                )) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }

                if ($currentStatus === self::SEAT_STATUS_BLOCKED) {
                    $changedSeatIds[] = $seatId;
                }
            }

            if ($newlyBlockedSeatIds !== []) {
                $onlineUnbookedCount = 0;
                $newlyBlocked        = array_fill_keys($newlyBlockedSeatIds, true);

                foreach ($seatRows as $seatRow) {
                    $seatId = (int) $seatRow->seat_id;
                    $status = isset($newlyBlocked[$seatId])
                        ? self::SEAT_STATUS_BLOCKED
                        : (int) $seatRow->status;

                    if (!\in_array($status, [self::SEAT_STATUS_BLOCKED, self::SEAT_STATUS_BOOKED], true)) {
                        $onlineUnbookedCount++;
                    }
                }

                if ($onlineUnbookedCount < $this->getActiveCartQuantity($eventId)) {
                    throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
                }
            }

            if ($changedSeatIds !== []) {
                $now       = gmdate('Y-m-d H:i:s');
                $blockNote = $targetStatus === self::SEAT_STATUS_BLOCKED ? $note : '';
                $query     = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__copymypage_event_seats'))
                    ->set($this->db->quoteName('status') . ' = ' . $targetStatus)
                    ->set($this->db->quoteName('cart_id') . ' = NULL')
                    ->set($this->db->quoteName('price_index') . ' = NULL')
                    ->set($this->db->quoteName('assignment_order') . ' = NULL')
                    ->set($this->db->quoteName('ticket_id') . ' = NULL')
                    ->set($this->db->quoteName('block_note') . ' = :blockNote')
                    ->set($this->db->quoteName('modified') . ' = :modified')
                    ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
                    ->where($this->db->quoteName('event_id') . ' = :eventId')
                    ->where(
                        $this->db->quoteName('seat_id') . ' IN ('
                            . implode(',', $changedSeatIds) . ')'
                    )
                    ->bind(':blockNote', $blockNote)
                    ->bind(':modified', $now)
                    ->bind(':modifiedBy', $userId, ParameterType::INTEGER)
                    ->bind(':eventId', $eventId, ParameterType::INTEGER);
                $this->db->setQuery($query)->execute();

                $this->advanceInventoryVersion($eventId, $now, $userId);
                $assignment->inventory_version = (int) $assignment->inventory_version + 1;
            }

            $this->db->transactionCommit();
            $transactionOpen = false;

            return $this->buildMutationResult(
                $assignment,
                \count($seatRows),
                \count($changedSeatIds)
            );
        } catch (\DomainException $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($transactionOpen) {
                $this->db->transactionRollback();
            }

            throw new \RuntimeException(
                Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_SAVE'),
                0,
                $exception
            );
        }
    }

    /**
     * Validate the assigned published layout and lock its exact event inventory.
     *
     * @return array{0: array<string, int|string>, 1: list<object>}
     */
    private function loadAndLockExactInventory(int $eventId, object $assignment): array
    {
        $layoutId = (int) ($assignment->layout_id ?? 0);
        $layout   = $this->layoutService->getPublishedLayout($layoutId);

        if (
            $layout === null
            || (int) $layout['seatCount'] < 1
            || (int) $layout['seatCount'] > SeatLayoutService::MAX_SEATS
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_LAYOUT'));
        }

        $layoutSeatIds = $this->loadLayoutSeatIds($layoutId);
        $seatRows      = $this->loadEventSeatRowsForUpdate($eventId);
        $eventSeatIds  = array_map(
            static fn(object $row): int => (int) $row->seat_id,
            $seatRows
        );
        $expectedSeatIds = $layoutSeatIds;
        sort($expectedSeatIds, SORT_NUMERIC);
        sort($eventSeatIds, SORT_NUMERIC);

        if (
            \count($layoutSeatIds) !== (int) $layout['seatCount']
            || \count($seatRows) !== (int) $layout['seatCount']
            || $eventSeatIds !== $expectedSeatIds
        ) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
        }

        return [$layout, $seatRows];
    }

    /**
     * @return list<object>
     */
    private function loadEventSeatRowsForUpdate(int $eventId): array
    {
        $query = $this->db->getQuery(true)
            ->select(
                $this->db->quoteName(
                    [
                        'id',
                        'seat_id',
                        'status',
                        'cart_id',
                        'price_index',
                        'assignment_order',
                        'ticket_id',
                        'block_note',
                    ]
                )
            )
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->order($this->db->quoteName('seat_id') . ' ASC')
            ->order($this->db->quoteName('id') . ' ASC');

        return (array) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObjectList();
    }

    /**
     * @param   array<int|string, mixed>  $rawSeatIds
     *
     * @return list<int>
     */
    private function normaliseSeatIds(array $rawSeatIds): array
    {
        if (\count($rawSeatIds) > SeatLayoutService::MAX_SEATS) {
            throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
        }

        $seatIds = [];
        $seen    = [];

        foreach ($rawSeatIds as $rawSeatId) {
            if (!\is_int($rawSeatId) && !\is_string($rawSeatId)) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
            }

            $seatId = filter_var(
                $rawSeatId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($seatId === false || isset($seen[(int) $seatId])) {
                throw new \DomainException(Text::_('COM_COPYMYPAGE_EVENT_SEATING_ERROR_INVENTORY'));
            }

            $seatId        = (int) $seatId;
            $seen[$seatId] = true;
            $seatIds[]     = $seatId;
        }

        sort($seatIds, SORT_NUMERIC);

        return $seatIds;
    }

    private function advanceInventoryVersion(int $eventId, string $modified, int $userId): void
    {
        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__copymypage_event_seating'))
            ->set(
                $this->db->quoteName('inventory_version') . ' = '
                    . $this->db->quoteName('inventory_version') . ' + 1'
            )
            ->set($this->db->quoteName('modified') . ' = :modified')
            ->set($this->db->quoteName('modified_by') . ' = :modifiedBy')
            ->where($this->db->quoteName('event_id') . ' = :eventId')
            ->bind(':modified', $modified)
            ->bind(':modifiedBy', $userId, ParameterType::INTEGER)
            ->bind(':eventId', $eventId, ParameterType::INTEGER);
        $this->db->setQuery($query)->execute();
    }

    /**
     * @return array<string, int|string>
     */
    private function buildMutationResult(object $assignment, int $seatCount, int $changedCount): array
    {
        return [
            'changedCount'     => max(0, $changedCount),
            'eventId'          => (int) $assignment->event_id,
            'inventoryVersion' => max(0, (int) $assignment->inventory_version),
            'layoutId'         => (int) $assignment->layout_id,
            'seatCount'        => max(0, $seatCount),
            'status'           => (int) $assignment->status,
        ];
    }

    private function loadEvent(int $eventId, bool $forUpdate): ?object
    {
        $query = $this->db->getQuery(true)
            ->select(
                $this->db->quoteName(
                    [
                        'id',
                        'title',
                        'start_date',
                        'capacity',
                        'capacity_used',
                        'max_tickets',
                        'booking_waiting_list',
                    ]
                )
            )
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);
        $sql = (string) $query . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->setQuery($sql)->loadObject();

        return \is_object($row) ? $row : null;
    }

    private function loadAssignment(int $eventId, bool $forUpdate): ?object
    {
        if ($forUpdate) {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__copymypage_event_seating'))
                ->where($this->db->quoteName('event_id') . ' = ' . $eventId);
            $row = $this->db->setQuery((string) $query . ' FOR UPDATE')->loadObject();

            return \is_object($row) ? $row : null;
        }

        $query = $this->db->getQuery(true)
            ->select(
                [
                    $this->db->quoteName('a.event_id'),
                    $this->db->quoteName('a.layout_id'),
                    $this->db->quoteName('a.status'),
                    $this->db->quoteName('a.inventory_version'),
                    $this->db->quoteName('l.title', 'layout_title'),
                    $this->db->quoteName('l.alias', 'layout_alias'),
                    $this->db->quoteName('l.version', 'layout_version'),
                    'COUNT(DISTINCT ' . $this->db->quoteName('s.id') . ') AS '
                        . $this->db->quoteName('seat_count'),
                    '(SELECT COUNT(*) FROM '
                        . $this->db->quoteName('#__copymypage_event_seats', 'inventory')
                        . ' WHERE ' . $this->db->quoteName('inventory.event_id')
                        . ' = ' . $this->db->quoteName('a.event_id') . ') AS '
                        . $this->db->quoteName('materialized_count'),
                ]
            )
            ->from($this->db->quoteName('#__copymypage_event_seating', 'a'))
            ->innerJoin(
                $this->db->quoteName('#__copymypage_seat_layouts', 'l')
                    . ' ON ' . $this->db->quoteName('l.id')
                    . ' = ' . $this->db->quoteName('a.layout_id')
            )
            ->leftJoin(
                $this->db->quoteName('#__copymypage_layout_tables', 't')
                    . ' ON ' . $this->db->quoteName('t.layout_id')
                    . ' = ' . $this->db->quoteName('l.id')
            )
            ->leftJoin(
                $this->db->quoteName('#__copymypage_seats', 's')
                    . ' ON ' . $this->db->quoteName('s.layout_table_id')
                    . ' = ' . $this->db->quoteName('t.id')
            )
            ->where($this->db->quoteName('a.event_id') . ' = :eventId')
            ->group(
                $this->db->quoteName(
                    [
                        'a.event_id',
                        'a.layout_id',
                        'a.status',
                        'a.inventory_version',
                        'l.title',
                        'l.alias',
                        'l.version',
                    ]
                )
            )
            ->bind(':eventId', $eventId, ParameterType::INTEGER);
        $row = $this->db->setQuery($query)->loadObject();

        return \is_object($row) ? $row : null;
    }

    /**
     * @return list<int>
     */
    private function loadLayoutSeatIds(int $layoutId): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('s.id'))
            ->from($this->db->quoteName('#__copymypage_seats', 's'))
            ->innerJoin(
                $this->db->quoteName('#__copymypage_layout_tables', 't')
                    . ' ON ' . $this->db->quoteName('t.id')
                    . ' = ' . $this->db->quoteName('s.layout_table_id')
            )
            ->where($this->db->quoteName('t.layout_id') . ' = :layoutId')
            ->order($this->db->quoteName('t.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.sort_order') . ' ASC')
            ->order($this->db->quoteName('s.id') . ' ASC')
            ->bind(':layoutId', $layoutId, ParameterType::INTEGER);

        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }

    /**
     * @return list<int>
     */
    private function loadEventSeatIds(int $eventId, bool $forUpdate): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('seat_id'))
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->order($this->db->quoteName('seat_id') . ' ASC');
        $sql = (string) $query . ($forUpdate ? ' FOR UPDATE' : '');

        return array_map('intval', $this->db->setQuery($sql)->loadColumn());
    }

    /**
     * @param   list<int>  $seatIds
     */
    private function insertEventSeats(
        int $eventId,
        array $seatIds,
        string $now,
        int $userId
    ): void {
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__copymypage_event_seats'))
            ->columns(
                $this->db->quoteName(
                    ['event_id', 'seat_id', 'status', 'created', 'modified', 'modified_by']
                )
            );

        foreach ($seatIds as $seatId) {
            $query->values(
                implode(
                    ',',
                    [
                        $eventId,
                        $seatId,
                        self::SEAT_STATUS_AVAILABLE,
                        $this->db->quote($now),
                        $this->db->quote($now),
                        $userId,
                    ]
                )
            );
        }

        $this->db->setQuery($query)->execute();
    }

    private function getActiveCartQuantity(int $eventId): int
    {
        $now   = gmdate('Y-m-d H:i:s');
        $query = $this->db->getQuery(true)
            ->select('COALESCE(SUM(' . $this->db->quoteName('i.quantity') . '), 0)')
            ->from($this->db->quoteName('#__copymypage_ticket_cart_items', 'i'))
            ->innerJoin(
                $this->db->quoteName('#__copymypage_ticket_carts', 'c')
                    . ' ON ' . $this->db->quoteName('c.id')
                    . ' = ' . $this->db->quoteName('i.cart_id')
            )
            ->where($this->db->quoteName('i.event_id') . ' = :eventId')
            ->where(
                $this->db->quoteName('c.status') . ' = '
                    . TicketCartContextService::STATUS_ACTIVE
            )
            ->where($this->db->quoteName('c.expires_at') . ' > :now')
            ->bind(':eventId', $eventId, ParameterType::INTEGER)
            ->bind(':now', $now);

        return max(0, (int) $this->db->setQuery($query)->loadResult());
    }

    private function getNativeTicketCount(int $eventId): int
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(' . $this->db->quoteName('id') . ')')
            ->from($this->db->quoteName('#__dpcalendar_tickets'))
            ->where($this->db->quoteName('event_id') . ' = :eventId')
            ->where($this->db->quoteName('state') . ' <> -2')
            ->bind(':eventId', $eventId, ParameterType::INTEGER);

        return max(0, (int) $this->db->setQuery($query)->loadResult());
    }

    private function isUpcoming(object $event): bool
    {
        $startDate = trim((string) ($event->start_date ?? ''));

        return $startDate !== '' && $startDate > gmdate('Y-m-d H:i:s');
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function normaliseEvent(int $eventId, ?object $event): array
    {
        if ($event === null) {
            return [
                'capacity'     => null,
                'capacityUsed' => null,
                'id'           => $eventId,
                'isUpcoming'   => false,
                'maxTickets'   => null,
                'startDate'    => '',
                'title'        => '',
                'waitingList'  => false,
            ];
        }

        return [
            'capacity'     => $this->nullableInt($event->capacity ?? null),
            'capacityUsed' => $this->nullableInt($event->capacity_used ?? null),
            'id'           => (int) $event->id,
            'isUpcoming'   => $this->isUpcoming($event),
            'maxTickets'   => $this->nullableInt($event->max_tickets ?? null),
            'startDate'    => (string) ($event->start_date ?? ''),
            'title'        => (string) ($event->title ?? ''),
            'waitingList'  => (int) ($event->booking_waiting_list ?? 0) === 1,
        ];
    }

    /**
     * @return array<string, int|string>|null
     */
    private function normaliseAssignment(?object $assignment): ?array
    {
        if ($assignment === null) {
            return null;
        }

        return [
            'inventoryVersion' => (int) $assignment->inventory_version,
            'layoutAlias'      => (string) $assignment->layout_alias,
            'layoutId'         => (int) $assignment->layout_id,
            'layoutTitle'      => (string) $assignment->layout_title,
            'layoutVersion'    => (int) $assignment->layout_version,
            'materializedCount' => (int) $assignment->materialized_count,
            'seatCount'         => (int) $assignment->seat_count,
            'status'            => match ((int) $assignment->status) {
                self::EVENT_STATUS_DRAFT => 'draft',
                self::EVENT_STATUS_READY => 'ready',
                default => 'unknown',
            },
        ];
    }

    /**
     * @return array{message: string, status: string}
     */
    private function diagnostic(string $status, string $message): array
    {
        return ['message' => $message, 'status' => $status];
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
