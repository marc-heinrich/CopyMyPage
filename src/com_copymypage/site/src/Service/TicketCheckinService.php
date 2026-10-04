<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @license     GNU General Public License version 3 or later
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\BeforeChangeStateEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;

/**
 * The bounded staff operation: one managed, seated ticket from state 1 to 9.
 *
 * No customer/administrator DPCalendar model ACL, booking token or seat write.
 * Each call owns its transaction; callers must not wrap it in a transaction.
 */
final class TicketCheckinService
{
    public const ACTION = 'copymypage.ticket.checkin';

    private bool $running = false;

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly SeatLayoutService $layouts
    ) {
    }

    /** Installed ticket.uid layout generates UUIDs; reject rather than filter input. */
    public static function normaliseUid(mixed $uid): ?string
    {
        return \is_string($uid) && strlen($uid) === 36
            && preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/i', $uid) === 1
            ? strtoupper($uid)
            : null;
    }

    public function isAuthorised(): bool
    {
        $user = $this->app->getIdentity();

        return $this->app->isClient('site') && $user !== null
            && !$user->guest && (int) $user->id > 0 && !$user->block
            && $user->authorise(self::ACTION, 'com_copymypage');
    }

    /** Read-only protected context for the later staff view. */
    public function getContext(mixed $uid): array
    {
        return $this->run($uid, false);
    }

    /** @return array{status: string, reason: string} */
    public function checkin(mixed $uid): array
    {
        return $this->run($uid, true);
    }

    private function run(mixed $rawUid, bool $mutate): array
    {
        if (!$this->isAuthorised()) {
            return $this->result('rejected', 'unauthorised');
        }

        $uid = self::normaliseUid($rawUid);

        if ($uid === null) {
            return $this->result('rejected', 'invalid_uid');
        }

        if ($this->running) {
            return $this->result('failed', 'unavailable');
        }

        $transactionOpen = false;
        $this->running  = true;

        try {
            $this->assertTransactionalSetup();
            // Hints only. All relationships and UID cardinality are re-read under locks.
            $hints = $this->ticketsByUid($uid, false);
            if (!$mutate && \count($hints) !== 1) {
                return $this->result('rejected', 'invalid_uid');
            }
            $this->require(\count($hints) === 1);
            $hint = $hints[0];
            $this->require((int) $hint['booking_id'] > 0 && (int) $hint['event_id'] > 0);

            $this->db->transactionStart();
            $transactionOpen = true;

            $snapshot = $this->lockContext($uid, $hint);
            // Clear Joomla's ACL cache so revocation while waiting is observed.
            Access::clearStatics();
            $this->app->getIdentity()->clearAccessRights();
            $this->require($this->isAuthorised());
            $this->validate($snapshot);

            if ((int) $snapshot['ticket']['state'] === 9 || !$mutate) {
                $presentation = !$mutate ? $this->presentation($snapshot) : [];
                // No hooks or writes, including on the losing concurrent request.
                $this->db->transactionRollback();
                $transactionOpen = false;

                return $this->result((int) $snapshot['ticket']['state'] === 9
                    ? 'already_checked_in' : 'ready') + $presentation;
            }

            $factory = $this->app->bootComponent('com_dpcalendar')->getMVCFactory();
            $ticketTable = $factory->createTable('Ticket', 'Administrator', ['dbo' => $this->db]);
            $eventTable  = $factory->createTable('Event', 'Administrator', ['dbo' => $this->db]);
            $this->require($ticketTable !== null && $eventTable !== null);
            $ticketId = (int) $snapshot['ticket']['id'];
            $eventId  = (int) $snapshot['ticket']['event_id'];
            $user     = $this->app->getIdentity();
            $ticketTable->setCurrentUser($user);
            $eventTable->setCurrentUser($user);
            $this->require($ticketTable->load($ticketId) && $eventTable->load($eventId));

            $dispatcher = $this->app->getDispatcher();
            PluginHelper::importPlugin('content', null, true, $dispatcher);
            $arguments = ['context' => 'com_dpcalendar.ticket', 'subject' => [$ticketId], 'value' => 9];
            $before = new BeforeChangeStateEvent('onContentBeforeChangeState', $arguments);
            $dispatcher->dispatch('onContentBeforeChangeState', $before);
            $this->require(!\in_array(false, $before->getArgument('result', []), true));
            $this->require($before->getContext() === $arguments['context']
                && $before->getPks() === [$ticketId] && $before->getValue() === 9);
            $this->verify($uid, $hint, $snapshot, false);

            // Inherited Table::publish preserves onTableBefore/AfterPublish.
            $this->require($ticketTable->publish([$ticketId], 9, (int) $user->id));
            $after = new AfterChangeStateEvent('onContentChangeState', $arguments);
            $dispatcher->dispatch('onContentChangeState', $after);
            $this->require(!\in_array(false, $after->getArgument('result', []), true));
            $this->require($after->getContext() === $arguments['context']
                && $after->getPks() === [$ticketId] && $after->getValue() === 9);
            // A hook must not expand book()'s affected rows or change eligibility.
            // Verify the published ticket while capacity is still unchanged.
            $this->verify($uid, $hint, $snapshot, true);
            // This order matches TicketModel::publish: model hooks, then capacity.
            $this->require($eventTable->book(false, $eventId));
            $this->verify($uid, $hint, $snapshot, true, true);

            $this->db->transactionCommit();
            $transactionOpen = false;

            return $this->result('checked_in');
        } catch (\Throwable $exception) {
            if ($transactionOpen) {
                try {
                    $this->db->transactionRollback();
                } catch (\Throwable) {
                    // An uncertain outcome is always failed, never success.
                    return $this->result('failed', 'unavailable');
                }
            }

            if ($exception instanceof \DomainException) {
                return $this->result('rejected', 'ineligible');
            }

            Log::add('CopyMyPage ticket check-in failed (' . $exception::class . ') at '
                . $exception->getFile() . ':' . $exception->getLine() . '.',
                Log::ERROR, 'com_copymypage');

            // No automatic retries: hooks may have non-database side effects.
            return $this->result('failed', 'unavailable');
        } finally {
            $this->running = false;
        }
    }

    /** Require MySQL next-key locks for non-unique UID/association ranges. */
    private function assertTransactionalSetup(): void
    {
        $this->require($this->db->getServerType() === 'mysql'
            && Factory::getContainer()->get(DatabaseInterface::class) === $this->db);
        $isolation = $this->sessionIsolation();
        $this->require(\in_array($isolation, ['REPEATABLE-READ', 'SERIALIZABLE'], true));
        $tables = ['dpcalendar_tickets', 'dpcalendar_bookings', 'dpcalendar_events',
            'copymypage_ticket_carts', 'copymypage_event_seating', 'copymypage_event_seats',
            'copymypage_seat_layouts', 'copymypage_layout_tables', 'copymypage_seats',
            'associations', 'categories', 'ucm_content', 'ucm_base', 'action_logs'];
        $names = array_map(fn(string $name): string => $this->db->quote(
            $this->db->replacePrefix('#__' . $name)), $tables);
        $engines = $this->db->setQuery('SELECT ENGINE FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(',', $names) . ')')
            ->loadColumn();
        $this->require(\count($engines) === \count($tables)
            && array_filter($engines, static fn($engine): bool => $engine !== 'InnoDB') === []);
    }

    /** Read the effective session setting without changing isolation or retrying SQL errors. */
    private function sessionIsolation(): mixed
    {
        // Both Joomla MySQL drivers expose this metadata (including MariaDB prefix removal).
        $this->require(is_callable([$this->db, 'isMariaDb']));
        $variable = 'transaction_isolation';
        if ($this->db->isMariaDb()) {
            $version = $this->db->getVersion();
            $this->require(\is_string($version)
                && preg_match('/\A(\d+\.\d+\.\d+)(?:-|\z)/', $version, $matches) === 1);
            if (version_compare($matches[1], '11.1.1', '<')) {
                $variable = 'tx_isolation';
            }
        }

        return $this->db->setQuery('SELECT @@SESSION.' . $variable)->loadResult();
    }

    private function ticketsByUid(string $uid, bool $lock): array
    {
        $query = $this->db->getQuery(true)->select('*')
            ->from($this->db->quoteName('#__dpcalendar_tickets'))
            ->where($this->db->quoteName('uid') . ' = :uid')->order('id ASC')
            ->bind(':uid', $uid, ParameterType::STRING);

        return $this->rows($query, $lock);
    }

    /** Cart -> booking -> association range/event IDs ascending -> ticket -> seating. */
    private function lockContext(string $uid, array $hint): array
    {
        $bookingId = (int) $hint['booking_id'];
        $eventId   = (int) $hint['event_id'];
        $carts = $this->select('#__copymypage_ticket_carts', 'booking_id', $bookingId);
        $this->require(\count($carts) === 1);
        $bookings = $this->select('#__dpcalendar_bookings', 'id', $bookingId);
        $this->require(\count($bookings) === 1);
        [$associationRows, $eventIds] = $this->lockAssociations($eventId);
        $query = $this->db->getQuery(true)->select('*')
            ->from($this->db->quoteName('#__dpcalendar_events'))
            ->where('(id IN (' . implode(',', $eventIds) . ')'
                . ' OR (original_id = ' . $eventId . ' AND booking_series = 1))')
            ->order('id ASC');
        $events = $this->rows($query, true);
        // A recurrence child would also be changed by book(); unsupported, reject.
        $this->require(array_map(static fn($row): int => (int) $row['id'], $events) === $eventIds);
        $this->verifyNativeAssociationIds($eventId, $eventIds);
        $tickets = $this->ticketsByUid($uid, true);
        $this->require(\count($tickets) === 1 && (int) $tickets[0]['id'] === (int) $hint['id']
            && self::normaliseUid($tickets[0]['uid']) === $uid
            && (int) $tickets[0]['booking_id'] === $bookingId
            && (int) $tickets[0]['event_id'] === $eventId);
        $assignments = $this->select('#__copymypage_event_seating', 'event_id', $eventId);
        $this->require(\count($assignments) === 1);
        $layoutId = (int) $assignments[0]['layout_id'];
        $layoutRows = $this->select('#__copymypage_seat_layouts', 'id', $layoutId);
        $this->require(\count($layoutRows) === 1);
        $query = $this->db->getQuery(true)->select('s.id')
            ->from($this->db->quoteName('#__copymypage_seats', 's'))
            ->innerJoin($this->db->quoteName('#__copymypage_layout_tables', 't')
                . ' ON t.id = s.layout_table_id')->where('t.layout_id = ' . $layoutId)
            ->order('s.id ASC');
        $layoutSeats = $this->rows($query, true);
        $query = $this->db->getQuery(true)->select('*')
            ->from($this->db->quoteName('#__copymypage_event_seats'))
            ->where('(event_id = ' . $eventId . ' OR ticket_id = ' . (int) $hint['id'] . ')')
            ->order('event_id ASC, seat_id ASC, id ASC');

        return ['cart' => $carts[0], 'booking' => $bookings[0], 'events' => $events,
            'ticket' => $tickets[0], 'assignment' => $assignments[0],
            'layout' => $layoutRows[0], 'layoutSeats' => $layoutSeats,
            'seats' => $this->rows($query, true), 'associations' => $associationRows];
    }

    /** Stabilise both the source membership and its entire key range before events. */
    private function lockAssociations(int $eventId): array
    {
        if (!Associations::isEnabled()) {
            return [[], [$eventId]];
        }

        $query = $this->db->getQuery(true)->select('*')
            ->from($this->db->quoteName('#__associations'))
            ->where('context = ' . $this->db->quote('com_dpcalendar.item'))
            ->where('id = ' . $eventId)->order('id ASC');
        $source = $this->rows($query, true);
        $this->require(\count($source) <= 1);

        if ($source === []) {
            return [[], [$eventId]];
        }

        $query = $this->db->getQuery(true)->select('*')
            ->from($this->db->quoteName('#__associations'))
            ->where($this->db->quoteName('key') . ' = :key')->order('id ASC, context ASC')
            ->bind(':key', $source[0]['key']);
        $rows = $this->rows($query, true);
        $ids  = [$eventId];

        foreach ($rows as $row) {
            $this->require($row['context'] === 'com_dpcalendar.item' && (int) $row['id'] > 0);
            $ids[] = (int) $row['id'];
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return [$rows, $ids];
    }

    /** Reject stale helper cache, dangling associations or ambiguous language membership. */
    private function verifyNativeAssociationIds(int $eventId, array $expected): void
    {
        $ids = [$eventId];

        if (Associations::isEnabled()) {
            $native = Associations::getAssociations('com_dpcalendar', '#__dpcalendar_events',
                'com_dpcalendar.item', $eventId);
            foreach ($native as $association) {
                $ids[] = (int) $association->id;
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        $this->require($ids === $expected);
    }

    private function validate(array $snapshot): void
    {
        $ticket = $snapshot['ticket'];
        $this->require(\in_array((int) $ticket['state'], [1, 9], true)
            && (int) $snapshot['booking']['state'] === 1
            && (int) $snapshot['cart']['status'] === TicketCartContextService::STATUS_CONVERTED
            && (int) $snapshot['cart']['booking_id'] === (int) $ticket['booking_id']);
        $this->require((int) $snapshot['assignment']['status'] === EventSeatInventoryService::EVENT_STATUS_READY
            && (int) $snapshot['layout']['status'] === SeatLayoutService::STATUS_PUBLISHED);
        $layout = $this->layouts->getPublishedLayout((int) $snapshot['layout']['id']);
        $count = \count($snapshot['layoutSeats']);
        $this->require($layout !== null && $count > 0 && $count <= SeatLayoutService::MAX_SEATS
            && $count === (int) $layout['seatCount'] && $count === \count($snapshot['seats']));
        $seatIds = [];
        $linked  = [];
        $sellable = 0;

        foreach ($snapshot['seats'] as $seat) {
            $this->require((int) $seat['event_id'] === (int) $ticket['event_id']
                && EventSeatInventoryService::isValidSeatState((int) $seat['status'], (int) $seat['allocation_type']));
            $seatIds[] = (int) $seat['seat_id'];
            $sellable += (int) $seat['status'] !== EventSeatInventoryService::SEAT_STATUS_BLOCKED ? 1 : 0;
            if ((int) $seat['ticket_id'] === (int) $ticket['id']) {
                $linked[] = $seat;
            }
        }

        sort($seatIds, SORT_NUMERIC);
        $this->require($seatIds === array_map(static fn($seat): int => (int) $seat['id'], $snapshot['layoutSeats'])
            && \count($linked) === 1);
        $seat = $linked[0];
        $this->require((int) $seat['status'] === EventSeatInventoryService::SEAT_STATUS_BOOKED
            && (int) $seat['allocation_type'] === EventSeatInventoryService::SEAT_ALLOCATION_STANDARD
            && (int) $seat['cart_id'] === (int) $snapshot['cart']['id']
            && $seat['price_index'] !== null && (int) $seat['price_index'] === (int) $ticket['type']);

        foreach ($snapshot['events'] as $event) {
            $this->require((int) $event['state'] === 1 && $event['original_id'] !== null
                && (int) $event['original_id'] === 0
                && trim((string) $event['rrule']) === '' && trim((string) $event['recurrence_id']) === ''
                && $event['capacity_used'] !== null && (int) $event['capacity_used'] >= 0
                && ($event['capacity'] === null || (int) $event['capacity'] >= $sellable));
        }
    }

    private function verify(
        string $uid,
        array $hint,
        array $before,
        bool $written,
        bool $capacityChanged = false
    ): void
    {
        $after = $this->lockContext($uid, $hint);
        $this->validate($after);
        $expected = $before;

        if ($written) {
            $expected['ticket']['state'] = 9;
        }
        if ($capacityChanged) {
            foreach ($expected['events'] as &$event) {
                $event['capacity_used'] = max(0, (int) $event['capacity_used'] - 1);
            }
            unset($event);
        }

        // Drivers can return numeric fields as strings; compare their data values.
        $this->require($after == $expected);
    }

    private function select(string $table, string $column, int $id): array
    {
        $query = $this->db->getQuery(true)->select('*')->from($this->db->quoteName($table))
            ->where($this->db->quoteName($column) . ' = :id')->order($this->db->quoteName($column) . ' ASC')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $this->rows($query, true);
    }

    private function rows(DatabaseQuery $query, bool $lock): array
    {
        if ($lock) {
            // Retain the query object and its bound parameters (Live DB contract).
            $query->setQuery((string) $query . ' FOR UPDATE');
        }

        return (array) $this->db->setQuery($query)->loadAssocList();
    }

    private function require(bool $condition): void
    {
        if (!$condition) {
            throw new \DomainException('Ineligible check-in context.');
        }
    }

    /** Read-only, allowlisted door information from an already validated snapshot. */
    private function presentation(array $snapshot): array
    {
        $ticket = $snapshot['ticket'];
        $event = array_values(array_filter($snapshot['events'], static fn($row): bool =>
            (int) $row['id'] === (int) $ticket['event_id']))[0];
        $seats = (new TicketSeatProjectionService($this->db))->getForTickets([(int) $ticket['id']]);
        $prices = json_decode((string) $event['prices'], true);
        $prices = is_array($prices) ? array_values($prices) : [];
        $typeLabel = trim((string) ($prices[(int) $ticket['type']]['label'] ?? ''));
        // Use DPCalendar's installed date/timezone contract, including all-day dates.
        $this->app->bootComponent('com_dpcalendar');
        $date = \DigitalPeak\Component\DPCalendar\Administrator\Helper\DPCalendarHelper::getDate(
            $event['start_date'], (bool) $event['all_day']
        );
        return ['ticket' => [
            'eventTitle' => (string) $event['title'],
            'eventDate' => $date->format((bool) $event['all_day'] ? 'd.m.Y' : 'd.m.Y H:i', true),
            'holderName' => trim((string) $ticket['first_name'] . ' ' . (string) $ticket['name']),
            'typeLabel' => $typeLabel !== '' ? $typeLabel
                : Text::_('COM_COPYMYPAGE_TICKET_SELECTION_TICKET_TYPE_DEFAULT'),
            'seatLabel' => (string) ($seats[(int) $ticket['id']]['label'] ?? ''),
        ]];
    }

    private function result(string $status, string $reason = ''): array
    {
        return ['status' => $status, 'reason' => $reason];
    }
}
