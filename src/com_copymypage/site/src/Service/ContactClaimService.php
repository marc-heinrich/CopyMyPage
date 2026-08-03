<?php
/**
 * @package     Joomla.Site
 * @subpackage  Components.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.17
 */

namespace Joomla\Component\CopyMyPage\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\User\User;
use Joomla\Component\Contact\Administrator\Table\ContactTable;
use Joomla\Component\CopyMyPage\Site\Repository\ProfileAddressRepository;
use Joomla\Component\CopyMyPage\Site\ValueObject\AddressData;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Provides an explicit administrator boundary for linking a public contact to a user.
 *
 * Self-service claiming remains intentionally absent until a registration workflow
 * can provide a trustworthy, server-side email-verification signal.
 */
final class ContactClaimService
{
    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly ProfileAddressRepository $addresses,
        private readonly AddressCatalogService $catalogue
    ) {
    }

    /**
     * Return unlinked CopyMyPage public contacts matching a target user's email.
     *
     * @return array<int, array<string, int|string>>
     */
    public function findCandidates(User $actor, User $target): array
    {
        $this->assertAdministrator($actor);
        $email = $this->normaliseEmail((string) $target->email);

        if ((int) $target->id === 0 || $email === '') {
            return [];
        }

        $extension = 'com_contact';
        $alias     = 'copymypage';
        $query = $this->db->getQuery(true)
            ->select(
                $this->db->quoteName(
                    [
                        'contact.id',
                        'contact.name',
                        'contact.email_to',
                        'contact.address',
                        'contact.postcode',
                        'contact.suburb',
                        'contact.state',
                        'contact.country',
                    ]
                )
            )
            ->from($this->db->quoteName('#__contact_details', 'contact'))
            ->join(
                'INNER',
                $this->db->quoteName('#__categories', 'category')
                . ' ON ' . $this->db->quoteName('category.id')
                . ' = ' . $this->db->quoteName('contact.catid')
            )
            ->where($this->db->quoteName('contact.user_id') . ' = 0')
            ->where('LOWER(' . $this->db->quoteName('contact.email_to') . ') = :email')
            ->where($this->db->quoteName('category.extension') . ' = :extension')
            ->where($this->db->quoteName('category.alias') . ' = :alias')
            ->order($this->db->quoteName('contact.id') . ' ASC')
            ->bind(':email', $email, ParameterType::STRING)
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':alias', $alias, ParameterType::STRING);
        $rows = $this->db->setQuery($query)->loadAssocList();

        return is_array($rows) ? $rows : [];
    }

    /**
     * Link one explicitly selected contact and optionally import its reviewed address.
     *
     * @return array{contact_id: int, address_imported: bool}
     */
    public function claimByAdministrator(
        User $actor,
        User $target,
        int $contactId,
        bool $importAddress = false
    ): array {
        $candidates = $this->findCandidates($actor, $target);
        $candidate  = null;

        foreach ($candidates as $item) {
            if ((int) ($item['id'] ?? 0) === $contactId) {
                $candidate = $item;
                break;
            }
        }

        if ($candidate === null) {
            throw new \RuntimeException('The selected CopyMyPage contact is not eligible for this user.');
        }

        $this->db->transactionStart();

        try {
            $this->lockTargetUser($target);
            $this->lockContact($contactId);

            $table = $this->createContactTable($actor);

            if (
                !$table->load($contactId)
                || (int) $table->user_id !== 0
                || !$this->isCopyMyPageContactCategory((int) $table->catid)
            ) {
                throw new \RuntimeException('The selected CopyMyPage contact is no longer available.');
            }

            if ($this->normaliseEmail((string) $table->email_to) !== $this->normaliseEmail((string) $target->email)) {
                throw new \RuntimeException('The selected contact email no longer matches the user.');
            }

            $table->user_id = (int) $target->id;

            if (!$table->check() || !$table->store(true)) {
                throw new \RuntimeException((string) $table->getError());
            }

            $addressImported = $importAddress
                ? $this->importReviewedAddress($target, $candidate)
                : false;

            $this->db->transactionCommit();

            return [
                'contact_id'       => (int) $table->id,
                'address_imported' => $addressImported,
            ];
        } catch (\Throwable $exception) {
            $this->db->transactionRollback();

            throw $exception;
        }
    }

    /**
     * Import an explicitly reviewed legacy address without overwriting a profile row.
     *
     * @param   array<string, int|string>  $candidate  Selected contact data.
     */
    private function importReviewedAddress(User $target, array $candidate): bool
    {
        if ($this->addresses->findForUser((int) $target->id) !== null) {
            return false;
        }

        $country = $this->catalogue->resolveCountry((string) ($candidate['country'] ?? ''));
        $required = [
            trim((string) ($candidate['address'] ?? '')),
            trim((string) ($candidate['postcode'] ?? '')),
            trim((string) ($candidate['suburb'] ?? '')),
        ];

        if ($country === null || in_array('', $required, true)) {
            throw new \RuntimeException('The selected contact does not contain a complete importable address.');
        }

        $street = $this->splitStreetAddress($required[0]);

        if ($street === null) {
            throw new \RuntimeException('The selected contact address does not contain a separable house number.');
        }

        $legacyRegion = trim((string) ($candidate['state'] ?? ''));
        $region       = $legacyRegion !== ''
            ? $this->catalogue->resolveRegion($country['code'], $legacyRegion)
            : null;

        if ($legacyRegion !== '' && $region === null) {
            throw new \RuntimeException('The selected contact region is not part of the address catalogue.');
        }

        $address = new AddressData(
            $street['street'],
            '',
            $street['house_number'],
            $required[1],
            $required[2],
            $region['name'] ?? '',
            $region['code'] ?? '',
            $country['code'],
            $country['name']
        );

        $this->addresses->saveForUser((int) $target->id, $address);

        return true;
    }

    /**
     * Split reviewed legacy contact data without guessing ambiguous values.
     *
     * @return array{street: string, house_number: string}|null
     */
    private function splitStreetAddress(string $value): ?array
    {
        $value         = trim($value);
        $numberPattern = '\d+[\p{L}]?(?:\s*[-\/]\s*\d+[\p{L}]?)?';

        if (preg_match('/^(.+?)\s+(' . $numberPattern . ')$/u', $value, $matches) === 1) {
            return [
                'street'       => trim($matches[1]),
                'house_number' => trim($matches[2]),
            ];
        }

        if (preg_match('/^(' . $numberPattern . ')[,\s]+(.+)$/u', $value, $matches) === 1) {
            return [
                'street'       => trim($matches[2]),
                'house_number' => trim($matches[1]),
            ];
        }

        return null;
    }

    /**
     * Resolve Joomla's supported Contact table for the explicit linking operation.
     */
    private function createContactTable(User $actor): ContactTable
    {
        $table = $this->app
            ->bootComponent('com_contact')
            ->getMVCFactory()
            ->createTable('Contact', 'Administrator', ['dbo' => $this->db]);

        if (!$table instanceof ContactTable) {
            throw new \RuntimeException('Joomla ContactTable could not be created.');
        }

        $table->setCurrentUser($actor);

        return $table;
    }

    /**
     * Recheck the CopyMyPage sender category after locking the contact row.
     */
    private function isCopyMyPageContactCategory(int $categoryId): bool
    {
        $extension = 'com_contact';
        $alias     = 'copymypage';
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__categories'))
            ->where($this->db->quoteName('id') . ' = :categoryId')
            ->where($this->db->quoteName('extension') . ' = :extension')
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':categoryId', $categoryId, ParameterType::INTEGER)
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':alias', $alias, ParameterType::STRING);

        return (int) $this->db->setQuery($query)->loadResult() === 1;
    }

    /**
     * Ensure only the current administrator can invoke the claim boundary.
     */
    private function assertAdministrator(User $actor): void
    {
        $identity = $this->app->getIdentity();

        if (
            !$identity instanceof User
            || (int) $identity->id === 0
            || (int) $identity->id !== (int) $actor->id
            || !$actor->authorise('core.manage', 'com_users')
        ) {
            throw new \RuntimeException('The contact claim operation is not authorised.', 403);
        }
    }

    /**
     * Serialize a claim with other writes affecting the target user.
     */
    private function lockTargetUser(User $target): void
    {
        $targetId = (int) $target->id;
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__users'))
            ->where($this->db->quoteName('id') . ' = ' . $targetId);
        $lockedId = (int) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult();

        if ($lockedId !== $targetId || $targetId === 0) {
            throw new \RuntimeException('The target Joomla user could not be locked.');
        }
    }

    /**
     * Serialize the selected contact before assigning its user id.
     */
    private function lockContact(int $contactId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__contact_details'))
            ->where($this->db->quoteName('id') . ' = ' . $contactId);
        $lockedId = (int) $this->db->setQuery((string) $query . ' FOR UPDATE')->loadResult();

        if ($lockedId !== $contactId || $contactId === 0) {
            throw new \RuntimeException('The selected Joomla contact could not be locked.');
        }
    }

    /**
     * Match emails without leaking Unicode-domain representation differences.
     */
    private function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim(PunycodeHelper::emailToPunycode($email)), 'UTF-8');
    }
}
