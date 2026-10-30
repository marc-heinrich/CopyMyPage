<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/** Resolves the installed Digital Peak PayPal plugin; never invokes a provider. */
final class NativePayPalProviderRegistry
{
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /** The suffix is the configured instance ID, not a fixed numeric constant. */
    public static function isReference(string $provider): bool
    {
        return str_starts_with($provider, 'paypal-');
    }

    public function isAvailable(string $provider): bool
    {
        return $this->getInstance($provider) !== null;
    }

    /** Internal configuration, including credentials. Never log or serialise the result. */
    public function getInstance(string $provider): ?array
    {
        if (!self::isReference($provider)) {
            return null;
        }

        $plugins = $this->db->setQuery("SELECT enabled, params FROM #__extensions"
            . " WHERE type = 'plugin' AND folder = 'dpcalendarpay' AND element = 'paypal'")->loadObjectList();
        if (\count($plugins) !== 1 || (int) $plugins[0]->enabled !== 1) {
            return null;
        }
        try {
            $params = json_decode((string) $plugins[0]->params, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $matches = [];
        foreach ((array) ($params['providers'] ?? []) as $instance) {
            if (!\is_array($instance) || !isset($instance['id']) || !\is_scalar($instance['id'])) {
                continue;
            }
            if ('paypal-' . (string) $instance['id'] === $provider) {
                $matches[] = $instance;
            }
        }

        // Duplicate or disabled IDs cannot resolve a historical association safely.
        return \count($matches) === 1
            && (!isset($matches[0]['state']) || (\is_scalar($matches[0]['state']) && $matches[0]['state'] != '0'))
            ? $matches[0] : null;
    }

    /** Also protects unresolved historical instance IDs and mismatched native associations. */
    public static function hasManagedBooking(DatabaseInterface $db, int $bookingId): bool
    {
        return $bookingId > 0 && (int) $db->setQuery('SELECT COUNT(*) FROM #__copymypage_ticket_carts c'
            . ' LEFT JOIN #__dpcalendar_bookings b ON b.id = c.booking_id'
            . ' WHERE c.booking_id = ' . $bookingId
            . " AND (c.payment_provider LIKE 'paypal-%' OR b.payment_provider LIKE 'paypal-%')")->loadResult() > 0;
    }
}
