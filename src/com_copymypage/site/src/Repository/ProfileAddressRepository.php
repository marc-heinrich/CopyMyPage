<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Repository;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\CopyMyPage\Site\ValueObject\AddressData;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Persists the current profile address only in CopyMyPage-owned storage.
 */
final class ProfileAddressRepository
{
    private const ADDRESS_KEY = 'profile';
    private const PURPOSE = 'profile';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Return the current profile address for one Joomla user.
     */
    public function findForUser(int $userId): ?AddressData
    {
        $addressKey = self::ADDRESS_KEY;
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__copymypage_addresses'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->where($this->db->quoteName('address_key') . ' = :addressKey')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':addressKey', $addressKey, ParameterType::STRING)
            ->setLimit(1);
        $row = $this->db->setQuery($query)->loadObject();

        return is_object($row) ? AddressData::fromStorage($row) : null;
    }

    /**
     * Insert or update the stable profile row for one user.
     */
    public function saveForUser(int $userId, AddressData $address): int
    {
        $addressKey = self::ADDRESS_KEY;
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__copymypage_addresses'))
            ->where($this->db->quoteName('user_id') . ' = :userId')
            ->where($this->db->quoteName('address_key') . ' = :addressKey')
            ->bind(':userId', $userId, ParameterType::INTEGER)
            ->bind(':addressKey', $addressKey, ParameterType::STRING)
            ->setLimit(1);
        $addressId = (int) $this->db->setQuery($query)->loadResult();
        $now       = Factory::getDate()->toSql();
        $record    = (object) array_merge(
            [
                'user_id'    => $userId,
                'address_key' => $addressKey,
                'purpose'     => self::PURPOSE,
                'is_default'  => 1,
                'state'       => 1,
                'params'      => '{}',
                'modified'    => $now,
            ],
            $address->toStorageData()
        );

        if ($addressId > 0) {
            $record->id = $addressId;

            if (!$this->db->updateObject('#__copymypage_addresses', $record, 'id', true)) {
                throw new \RuntimeException('The CopyMyPage profile address could not be updated.');
            }

            return $addressId;
        }

        $record->id      = 0;
        $record->created = $now;

        if (!$this->db->insertObject('#__copymypage_addresses', $record, 'id')) {
            throw new \RuntimeException('The CopyMyPage profile address could not be created.');
        }

        return (int) $record->id;
    }
}
