<?php
namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\Session\SessionInterface;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;

/** Short-lived PRG receipts only; never a current ticket or a mutation authority. */
final class TicketCheckinResultStore
{
    private const TTL = 3600;
    private readonly Cache $cache;

    public function __construct(private readonly SessionInterface $session)
    {
        // Joomla's database session handler replaces the whole row without a lock.
        // Independent session-bound receipts avoid that lost-update race entirely.
        $base = Factory::getApplication()->get('cache_path', JPATH_CACHE) . '/copymypage-checkin-results';
        Folder::create($base);
        $this->cache = new Cache(['storage' => 'file', 'cachebase' => $base,
            'application' => 'com_copymypage.ticketcheckin',
            'defaultgroup' => 'receipts', 'language' => '*', 'caching' => true,
            'lifetime' => self::TTL / 60, 'locking' => true]);
        // Only this dedicated receipt directory; never evict an unexpired result.
        $this->cache->gc();
    }

    public function store(int $userId, ?string $uid, array $result): string
    {
        // Separate receipts keep overlapping POSTs of even the same UID distinct.
        $receipt = bin2hex(random_bytes(16));
        $entry = [
            'expires' => time() + self::TTL,
            'result' => array_intersect_key($result, array_flip(['status', 'reason'])),
        ];
        if (!$this->cache->store(json_encode($entry, JSON_THROW_ON_ERROR),
            $this->key($userId, $uid, $receipt))) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_TICKET_CHECKIN_FAILED'));
        }
        return $receipt;
    }

    public function consume(int $userId, ?string $uid, string $receipt): ?array
    {
        if (preg_match('/\A[0-9a-f]{32}\z/', $receipt) !== 1) {
            return null;
        }
        $key = $this->key($userId, $uid, $receipt);
        $lockKey = $key . ':consume';
        if (!$this->cache->lock($lockKey)->locked) {
            return null;
        }
        try {
            $entry = json_decode((string) $this->cache->get($key), true);
            // Removal is under a separate lock, compatible with Windows files.
            if (!$this->cache->remove($key)) {
                return null;
            }
            return is_array($entry) && ($entry['expires'] ?? 0) > time()
                && is_array($entry['result'] ?? null) ? $entry['result'] : null;
        } finally {
            $this->cache->unlock($lockKey);
        }
    }

    private function key(int $userId, ?string $uid, string $receipt): string
    {
        return hash('sha256', $this->session->getId() . ':' . $userId . ':'
            . hash('sha256', $uid ?? '') . ':' . $receipt);
    }
}
