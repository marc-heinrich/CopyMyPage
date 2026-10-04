<?php
namespace Joomla\Component\CopyMyPage\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Component\CopyMyPage\Site\Service\TicketCheckinService;

/** Only rebuild our internal read-only context; never accept a caller's URL. */
final class TicketCheckinReturn
{
    public static function context(mixed $rawUid, string $receipt = ''): string
    {
        $uid = TicketCheckinService::normaliseUid($rawUid);
        return 'index.php?option=com_copymypage&view=ticketcheckin'
            . ($uid !== null ? '&uid=' . rawurlencode($uid) : '')
            . (preg_match('/\A[0-9a-f]{32}\z/', $receipt) === 1 ? '&result=' . $receipt : '');
    }

    public static function fromEncoded(mixed $encoded): ?string
    {
        if (!is_string($encoded) || strlen($encoded) > 1024) {
            return null;
        }
        $url = base64_decode($encoded, true);
        if ($url === false || !str_starts_with($url, 'index.php?')) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ($parts['path'] ?? '') !== 'index.php'
            || array_diff(array_keys($parts), ['path', 'query']) !== []) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        if (($query['option'] ?? '') !== 'com_copymypage'
            || ($query['view'] ?? '') !== 'ticketcheckin'
            || array_diff(array_keys($query), ['option', 'view', 'uid', 'result']) !== []
            || (isset($query['uid']) && TicketCheckinService::normaliseUid($query['uid']) === null)
            || (isset($query['result']) && (!is_string($query['result'])
                || preg_match('/\A[0-9a-f]{32}\z/', $query['result']) !== 1))) {
            return null;
        }
        return self::context($query['uid'] ?? null, $query['result'] ?? '');
    }
}
