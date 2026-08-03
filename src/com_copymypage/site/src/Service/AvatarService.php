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
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\Utility\Utility;
use Joomla\Component\Fields\Administrator\Model\FieldModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * Owns the current user's private Dashboard avatar.
 */
final class AvatarService
{
    /**
     * Stable Joomla Custom User Field contract.
     */
    public const FIELD_CONTEXT = 'com_users.user';

    public const FIELD_NAME = 'copymypage-avatar';

    /**
     * CopyMyPage-owned media root below Joomla's configured images directory.
     */
    private const MEDIA_ROOT = 'images/copymypage/avatars';

    /**
     * User-state prefix for files uploaded but not saved yet.
     */
    private const PENDING_STATE_KEY = 'com_copymypage.avatar.pending';

    /**
     * User-state prefix for a selection retained across profile validation.
     */
    private const SELECTION_STATE_KEY = 'com_copymypage.avatar.selection';

    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    private const MAX_IMAGE_DIMENSION = 4096;

    /**
     * Raster formats intentionally accepted for profile images.
     *
     * @var array<string, string>
     */
    private const ALLOWED_IMAGE_TYPES = [
        'avif' => 'image/avif',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly CMSWebApplicationInterface $app,
        private readonly DatabaseInterface $db,
        private readonly FormFactoryInterface $formFactory
    ) {
    }

    /**
     * Prepare the isolated Joomla Media field used by the avatar Drawer.
     */
    public function prepareForm(User $user): Form
    {
        $this->assertCurrentUser($user);
        Form::addFormPath(JPATH_SITE . '/components/com_copymypage/forms');

        $form = $this->formFactory->createForm(
            'com_copymypage.avatar',
            ['control' => 'jform']
        );

        if (!$form->loadFile('avatar')) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FORM'), 500);
        }

        $form->setFieldAttribute(
            'avatar',
            'directory',
            'copymypage/avatars/' . (int) $user->id
        );
        $form->setFieldAttribute(
            'avatar',
            'link',
            'index.php?option=com_copymypage&view=avatar&layout=picker&tmpl=component'
                . $this->getItemIdSuffix()
        );

        $avatar = $this->getAvatar($user);

        if (!$avatar['available']) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FIELD'), 500);
        }

        $retainedValue = $this->getRetainedSelection($user);

        $form->bind([
            'avatar' => $retainedValue ?? $avatar['value'],
        ]);

        return $form;
    }

    /**
     * Validate and retain the profile form's avatar selection across redirects.
     */
    public function retainSelection(User $user, string $submittedValue): string
    {
        $this->assertCurrentUser($user);

        try {
            $value = $this->normalizeSelection($user, $submittedValue);
        } catch (\Throwable $exception) {
            $this->app->setUserState($this->getSelectionStateKey($user), null);

            throw $exception;
        }

        $this->app->setUserState(
            $this->getSelectionStateKey($user),
            ['value' => $value]
        );

        return $value;
    }

    /**
     * Return a normalized presentation value without exposing field ids.
     *
     * @return array{available: bool, exists: bool, path: string, url: string, value: string}
     */
    public function getAvatar(User $user): array
    {
        $this->assertCurrentUser($user);

        $fieldId = $this->resolveFieldId();
        $result  = [
            'available' => $fieldId > 0,
            'exists'    => false,
            'path'      => '',
            'url'       => '',
            'value'     => '',
        ];

        if ($fieldId === 0) {
            return $result;
        }

        $rawValue = $this->getFieldModel()->getFieldValue($fieldId, (string) (int) $user->id);
        $media    = $this->extractMediaValue($rawValue);
        $details  = $this->getOwnedImageDetails($user, $media);

        if ($details === null) {
            return $result;
        }

        return [
            'available' => true,
            'exists'    => true,
            'path'      => $details['path'],
            'url'       => Uri::root() . $details['path'],
            'value'     => $this->buildMediaValue($details),
        ];
    }

    /**
     * Return the effective upload ceiling, including the active PHP limits.
     */
    public function getMaximumUploadSize(): int
    {
        $maximum = (int) Utility::getMaxUploadSize(self::MAX_FILE_SIZE);

        return $maximum > 0 ? min(self::MAX_FILE_SIZE, $maximum) : self::MAX_FILE_SIZE;
    }

    /**
     * Return a compact label suitable for localized form copy.
     */
    public function getMaximumUploadSizeLabel(): string
    {
        $bytes     = $this->getMaximumUploadSize();
        $megabyte  = 1024 * 1024;
        $precision = $bytes >= $megabyte && $bytes % $megabyte !== 0 ? 1 : 0;

        return HTMLHelper::_('number.bytes', $bytes, 'auto', $precision);
    }

    /**
     * Return the current and pending images exposed by the private picker.
     *
     * @return array<int, array<string, bool|int|string>>
     */
    public function getPickerItems(User $user): array
    {
        $this->assertCurrentUser($user);

        $items   = [];
        $current = $this->getAvatar($user);

        if ($current['exists']) {
            $details = $this->getOwnedImageDetails($user, $current['path']);

            if ($details !== null) {
                $items[$details['path']] = $this->buildPickerItem($details, false, true);
            }
        }

        foreach ($this->getPendingEntries($user) as $entry) {
            $details = $this->getOwnedImageDetails($user, (string) ($entry['path'] ?? ''));

            if ($details === null) {
                continue;
            }

            $items[$details['path']] = $this->buildPickerItem($details, true, false);
        }

        return array_values($items);
    }

    /**
     * Validate and move one upload into the current user's private avatar area.
     *
     * @param array<string, mixed> $file Joomla upload input.
     *
     * @return array<string, bool|int|string>
     */
    public function upload(User $user, array $file): array
    {
        $this->assertCurrentUser($user);

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(
                $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? Text::sprintf(
                        'COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FILE_SIZE',
                        $this->getMaximumUploadSizeLabel()
                    )
                    : Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_UPLOAD')
            );
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $temporary    = (string) ($file['tmp_name'] ?? '');
        $size         = (int) ($file['size'] ?? 0);
        $extension    = strtolower(File::getExt($originalName));

        if (
            $originalName === ''
            || $temporary === ''
            || !is_uploaded_file($temporary)
            || !isset(self::ALLOWED_IMAGE_TYPES[$extension])
        ) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FILE_TYPE'));
        }

        if ($size <= 0 || $size > $this->getMaximumUploadSize()) {
            throw new \RuntimeException(
                Text::sprintf(
                    'COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FILE_SIZE',
                    $this->getMaximumUploadSizeLabel()
                )
            );
        }

        $image = @getimagesize($temporary);
        $mime  = MediaHelper::getMimeType($temporary, true);

        if (
            !\is_array($image)
            || empty($image[0])
            || empty($image[1])
            || !\is_string($mime)
            || $mime !== self::ALLOWED_IMAGE_TYPES[$extension]
            || ($image['mime'] ?? '') !== $mime
        ) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FILE_TYPE'));
        }

        if (
            (int) $image[0] > self::MAX_IMAGE_DIMENSION
            || (int) $image[1] > self::MAX_IMAGE_DIMENSION
        ) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_DIMENSIONS'));
        }

        $directory = $this->getUserDirectory($user);

        if (!is_dir($directory) && !Folder::create($directory)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_UPLOAD'));
        }

        $this->clearPending($user);

        $fileName    = 'avatar-' . bin2hex(random_bytes(12)) . '.' . $extension;
        $destination = Path::clean($directory . DIRECTORY_SEPARATOR . $fileName);

        if (!File::upload($temporary, $destination, false, false)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_UPLOAD'));
        }

        $path = $this->getUserRelativeDirectory($user) . '/' . $fileName;
        $this->app->setUserState(
            $this->getPendingStateKey($user),
            [
                [
                    'created' => time(),
                    'path'    => $path,
                ],
            ]
        );

        $details = $this->getOwnedImageDetails($user, $path);

        if ($details === null) {
            File::delete($destination);
            $this->app->setUserState($this->getPendingStateKey($user), null);

            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_UPLOAD'));
        }

        return $this->buildPickerItem($details, true, false);
    }

    /**
     * Persist a selected image or clear the current avatar.
     */
    public function save(User $user, string $submittedValue): void
    {
        $this->assertCurrentUser($user);

        $fieldId = $this->resolveFieldId();

        if ($fieldId === 0) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FIELD'));
        }

        $current      = $this->getAvatar($user);
        $storedValue  = $this->normalizeSelection($user, $submittedValue);
        $fieldModel   = $this->getFieldModel();
        $currentPath  = $current['exists'] ? $current['path'] : '';
        $selectedPath = $this->normalizeOwnedPath($user, $storedValue) ?? '';

        if ($storedValue !== '') {

            if (!$fieldModel->setFieldValue($fieldId, (string) (int) $user->id, $storedValue)) {
                throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SAVE_FAILED'));
            }
        } elseif (!$fieldModel->setFieldValue($fieldId, (string) (int) $user->id, null)) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_SAVE_FAILED'));
        }

        $this->removeUnreferencedFiles($user, $selectedPath);

        if ($currentPath !== '' && $currentPath !== $selectedPath) {
            $this->deleteOwnedFile($user, $currentPath);
        }

        $this->app->setUserState($this->getPendingStateKey($user), null);
        $this->app->setUserState($this->getSelectionStateKey($user), null);
    }

    /**
     * Discard uploads which were not committed by the avatar form.
     */
    public function cancel(User $user): void
    {
        $this->assertCurrentUser($user);
        $this->clearPending($user);
        $this->app->setUserState($this->getSelectionStateKey($user), null);
    }

    /**
     * Resolve metadata for Joomla's Media field API contract.
     *
     * @return array<string, bool|int|string>
     */
    public function getMediaMetadata(User $user, string $adapterPath): array
    {
        $this->assertCurrentUser($user);

        $relativePath = $this->adapterPathToRelativePath($user, $adapterPath);
        $details      = $this->getOwnedImageDetails($user, $relativePath);

        if ($details === null) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_SELECTION'), 404);
        }

        return [
            'extension'  => $details['extension'],
            'height'     => $details['height'],
            'mime_type'  => $details['mime'],
            'name'       => basename($details['path']),
            'path'       => $this->relativePathToAdapterPath($user, $details['path']),
            'thumb_path' => false,
            'type'       => 'file',
            'url'        => Uri::root() . $details['path'],
            'width'      => $details['width'],
        ];
    }

    /**
     * Resolve the system field without relying on environment-specific ids.
     */
    private function resolveFieldId(): int
    {
        $context = self::FIELD_CONTEXT;
        $name    = self::FIELD_NAME;
        $type    = 'media';
        $state   = 1;
        $query   = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__fields'))
            ->where($this->db->quoteName('context') . ' = :context')
            ->where($this->db->quoteName('name') . ' = :name')
            ->where($this->db->quoteName('type') . ' = :type')
            ->where($this->db->quoteName('state') . ' = :state')
            ->bind(':context', $context)
            ->bind(':name', $name)
            ->bind(':type', $type)
            ->bind(':state', $state, ParameterType::INTEGER);

        return (int) $this->db->setQuery($query)->loadResult();
    }

    /**
     * Resolve Joomla's supported Custom Fields model.
     */
    private function getFieldModel(): FieldModel
    {
        $model = $this->app->bootComponent('com_fields')
            ->getMVCFactory()
            ->createModel('Field', 'Administrator', ['ignore_request' => true]);

        if (!$model instanceof FieldModel) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FIELD'), 500);
        }

        return $model;
    }

    /**
     * Extract the imagefile part of Joomla's accessible-media value.
     */
    private function extractMediaValue(mixed $value): string
    {
        if (!\is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $decoded = json_decode($value, true);

        if (\is_array($decoded)) {
            $value = \is_scalar($decoded['imagefile'] ?? null)
                ? trim((string) $decoded['imagefile'])
                : '';
        }

        return $value;
    }

    /**
     * Return a canonical value after enforcing the current user's media root.
     */
    private function normalizeSelection(User $user, string $submittedValue): string
    {
        if ($this->resolveFieldId() === 0) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_FIELD'));
        }

        $submitted = $this->extractMediaValue($submittedValue);

        if ($submitted === '') {
            return '';
        }

        $details = $this->getOwnedImageDetails($user, $submitted);

        if ($details === null) {
            throw new \RuntimeException(Text::_('COM_COPYMYPAGE_PROFILE_AVATAR_ERROR_SELECTION'));
        }

        return $this->buildMediaValue($details);
    }

    /**
     * Return a still-valid selection retained after a failed profile save.
     */
    private function getRetainedSelection(User $user): ?string
    {
        $state = $this->app->getUserState($this->getSelectionStateKey($user));

        if (!\is_array($state) || !\array_key_exists('value', $state) || !\is_scalar($state['value'])) {
            return null;
        }

        try {
            return $this->normalizeSelection($user, (string) $state['value']);
        } catch (\Throwable) {
            $this->app->setUserState($this->getSelectionStateKey($user), null);

            return null;
        }
    }

    /**
     * Return verified raster metadata for an owned path.
     *
     * @return array{extension: string, height: int, mime: string, path: string, width: int}|null
     */
    private function getOwnedImageDetails(User $user, string $mediaValue): ?array
    {
        $path = $this->normalizeOwnedPath($user, $mediaValue);

        if ($path === null) {
            return null;
        }

        $absolute  = Path::clean(JPATH_ROOT . '/' . $path);
        $extension = strtolower(File::getExt($path));

        if (!isset(self::ALLOWED_IMAGE_TYPES[$extension]) || !is_file($absolute)) {
            return null;
        }

        $size = filesize($absolute);

        if ($size === false || $size <= 0 || $size > self::MAX_FILE_SIZE) {
            return null;
        }

        $image = @getimagesize($absolute);
        $mime  = MediaHelper::getMimeType($absolute, true);

        if (
            !\is_array($image)
            || empty($image[0])
            || empty($image[1])
            || (int) $image[0] > self::MAX_IMAGE_DIMENSION
            || (int) $image[1] > self::MAX_IMAGE_DIMENSION
            || !\is_string($mime)
            || $mime !== self::ALLOWED_IMAGE_TYPES[$extension]
            || ($image['mime'] ?? '') !== $mime
        ) {
            return null;
        }

        return [
            'extension' => $extension,
            'height'    => (int) $image[1],
            'mime'      => $mime,
            'path'      => $path,
            'width'     => (int) $image[0],
        ];
    }

    /**
     * Normalize a local Media field value and enforce the per-user root.
     */
    private function normalizeOwnedPath(User $user, string $mediaValue): ?string
    {
        $mediaValue = trim($mediaValue);

        if ($mediaValue === '' || str_contains($mediaValue, "\0")) {
            return null;
        }

        $path = MediaHelper::getCleanMediaFieldValue($mediaValue);
        $path = rawurldecode(str_replace('\\', '/', trim((string) $path)));
        $path = ltrim($path, '/');

        if (
            $path === ''
            || str_contains($path, '../')
            || str_contains($path, '/..')
            || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) === 1
        ) {
            return null;
        }

        $path           = str_replace('\\', '/', Path::clean($path, '/'));
        $expectedPrefix = $this->getUserRelativeDirectory($user) . '/';

        if (!str_starts_with($path, $expectedPrefix)) {
            return null;
        }

        $fileName = substr($path, \strlen($expectedPrefix));

        if ($fileName === '' || str_contains($fileName, '/')) {
            return null;
        }

        return $expectedPrefix . $fileName;
    }

    /**
     * Build Joomla's canonical media value including intrinsic dimensions.
     *
     * @param array{extension: string, height: int, mime: string, path: string, width: int} $details
     */
    private function buildMediaValue(array $details): string
    {
        $adapterPath = substr($details['path'], \strlen('images/'));

        return $details['path']
            . '#joomlaImage://local-images/'
            . $adapterPath
            . '?width=' . $details['width']
            . '&height=' . $details['height'];
    }

    /**
     * Build one safe item consumed by the private picker template.
     *
     * @param array{extension: string, height: int, mime: string, path: string, width: int} $details
     *
     * @return array<string, bool|int|string>
     */
    private function buildPickerItem(array $details, bool $pending, bool $current): array
    {
        $user = $this->app->getIdentity();

        if (!$user instanceof User) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return [
            'current'   => $current,
            'extension' => $details['extension'],
            'height'    => $details['height'],
            'mime'      => $details['mime'],
            'name'      => basename($details['path']),
            'path'      => $this->relativePathToAdapterPath($user, $details['path']),
            'pending'   => $pending,
            'url'       => Uri::root() . $details['path'],
            'width'     => $details['width'],
        ];
    }

    /**
     * Convert the Media picker adapter value into a verified relative path.
     */
    private function adapterPathToRelativePath(User $user, string $adapterPath): string
    {
        $prefix = 'local-images:/copymypage/avatars/' . (int) $user->id . '/';

        if (!str_starts_with($adapterPath, $prefix)) {
            return '';
        }

        $fileName = substr($adapterPath, \strlen($prefix));

        if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return '';
        }

        return $this->getUserRelativeDirectory($user) . '/' . $fileName;
    }

    /**
     * Convert an owned relative path into Joomla's local-images adapter path.
     */
    private function relativePathToAdapterPath(User $user, string $path): string
    {
        $prefix   = $this->getUserRelativeDirectory($user) . '/';
        $fileName = str_starts_with($path, $prefix) ? substr($path, \strlen($prefix)) : '';

        return 'local-images:/copymypage/avatars/' . (int) $user->id . '/' . $fileName;
    }

    /**
     * Return still-valid pending entries and discard stale state.
     *
     * @return array<int, array{created: int, path: string}>
     */
    private function getPendingEntries(User $user): array
    {
        $entries = $this->app->getUserState($this->getPendingStateKey($user), []);
        $entries = \is_array($entries) ? $entries : [];
        $valid   = [];

        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $created = (int) ($entry['created'] ?? 0);
            $path    = \is_scalar($entry['path'] ?? null) ? (string) $entry['path'] : '';

            if ($created <= 0 || $created < time() - 3600 || $this->getOwnedImageDetails($user, $path) === null) {
                $this->deleteOwnedFile($user, $path);
                continue;
            }

            $valid[] = [
                'created' => $created,
                'path'    => $path,
            ];
        }

        $this->app->setUserState(
            $this->getPendingStateKey($user),
            $valid !== [] ? $valid : null
        );

        return $valid;
    }

    /**
     * Delete only files explicitly tracked as pending for this user.
     */
    private function clearPending(User $user): void
    {
        foreach ($this->getPendingEntries($user) as $entry) {
            $this->deleteOwnedFile($user, $entry['path']);
        }

        $this->app->setUserState($this->getPendingStateKey($user), null);
    }

    /**
     * Remove other files from the exact per-user directory after a successful save.
     */
    private function removeUnreferencedFiles(User $user, string $keepPath): void
    {
        $directory = $this->getUserDirectory($user);

        if (!is_dir($directory)) {
            return;
        }

        $keepAbsolute = $keepPath !== ''
            ? Path::clean(JPATH_ROOT . '/' . $keepPath)
            : '';

        foreach (Folder::files($directory, '.', false, true) as $file) {
            $file = Path::clean($file);

            if ($keepAbsolute !== '' && $file === $keepAbsolute) {
                continue;
            }

            if (isset(self::ALLOWED_IMAGE_TYPES[strtolower(File::getExt($file))])) {
                File::delete($file);
            }
        }
    }

    /**
     * Delete one path only after the ownership boundary has been verified.
     */
    private function deleteOwnedFile(User $user, string $path): void
    {
        $path = $this->normalizeOwnedPath($user, $path) ?? '';

        if ($path === '') {
            return;
        }

        $absolute = Path::clean(JPATH_ROOT . '/' . $path);

        if (!is_file($absolute)) {
            return;
        }

        try {
            File::delete($absolute);
        } catch (\Throwable $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'com_copymypage.avatar');
        }
    }

    private function getUserRelativeDirectory(User $user): string
    {
        return self::MEDIA_ROOT . '/' . (int) $user->id;
    }

    private function getUserDirectory(User $user): string
    {
        return Path::clean(JPATH_ROOT . '/' . $this->getUserRelativeDirectory($user));
    }

    private function getPendingStateKey(User $user): string
    {
        return self::PENDING_STATE_KEY . '.' . (int) $user->id;
    }

    private function getSelectionStateKey(User $user): string
    {
        return self::SELECTION_STATE_KEY . '.' . (int) $user->id;
    }

    private function getItemIdSuffix(): string
    {
        $itemId = $this->app->getInput()->getInt('Itemid');

        return $itemId > 0 ? '&Itemid=' . $itemId : '';
    }

    /**
     * Ensure callers cannot substitute another Joomla user object.
     */
    private function assertCurrentUser(User $user): void
    {
        $identity = $this->app->getIdentity();

        if (
            !$identity instanceof User
            || (int) $identity->id === 0
            || (bool) $identity->guest
            || (int) $identity->id !== (int) $user->id
        ) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
