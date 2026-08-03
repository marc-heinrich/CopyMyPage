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
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use Joomla\Component\Users\Site\Model\ProfileModel;
use Joomla\Registry\Registry;

/**
 * Creates narrowly projected com_users forms for CopyMyPage account workflows.
 *
 * Joomla's ProfileModel remains authoritative for form preparation, validation
 * and persistence. CopyMyPage removes every field which is not explicitly
 * assigned to the requested account context before the form reaches a view or
 * controller.
 */
final class UserFormProjectionService
{
    /**
     * Personal account details context.
     */
    public const CONTEXT_PROFILE_EDIT = 'com_copymypage.profile.edit';

    /**
     * Password and account security context.
     */
    public const CONTEXT_SECURITY_EDIT = 'com_copymypage.security.edit';

    /**
     * CopyMyPage-owned validation state for profile details.
     */
    public const PROFILE_STATE_KEY = 'com_copymypage.edit.profile.data';

    /**
     * CopyMyPage-owned validation state for security details.
     */
    public const SECURITY_STATE_KEY = 'com_copymypage.edit.security.data';

    /**
     * The core state must never leak into a projected dashboard form.
     */
    private const CORE_PROFILE_STATE_KEY = 'com_users.edit.profile.data';

    /**
     * Explicitly permitted fields by context and form group.
     *
     * Legal consent fields are retained only when their plugins actually add
     * them to the prepared com_users profile form.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const ALLOWED_FIELDS = [
        self::CONTEXT_PROFILE_EDIT => [
            ''               => ['name', 'email1'],
            'privacyconsent' => ['privacy'],
            'terms'          => ['terms'],
        ],
        self::CONTEXT_SECURITY_EDIT => [
            '' => ['password1', 'password2'],
        ],
    ];

    /**
     * @param   CMSWebApplicationInterface  $app  Active Joomla web application.
     */
    public function __construct(private readonly CMSWebApplicationInterface $app)
    {
    }

    /**
     * Return the authenticated Joomla identity.
     */
    public function getCurrentUser(): ?User
    {
        $user = $this->app->getIdentity();

        return $user instanceof User && (int) $user->id > 0 && !(bool) $user->guest
            ? $user
            : null;
    }

    /**
     * Return a small, stable current-user summary for the dashboard overview.
     *
     * @return array<string, mixed>
     */
    public function getCurrentUserSummary(): array
    {
        $user = $this->getCurrentUser();

        if (!$user instanceof User) {
            return [];
        }

        return [
            'email'         => trim((string) $user->email),
            'id'            => (int) $user->id,
            'lastvisitDate' => (string) $user->lastvisitDate,
            'name'          => trim((string) $user->name),
            'registerDate'  => (string) $user->registerDate,
            'username'      => trim((string) $user->username),
        ];
    }

    /**
     * Prepare and project the current user's authoritative com_users form.
     *
     * @return array{
     *     data: User,
     *     form: Form,
     *     model: ProfileModel,
     *     params: Registry
     * }
     */
    public function prepareForm(string $context): array
    {
        $context = $this->normaliseContext($context);
        $user    = $this->getCurrentUser();

        if (!$user instanceof User) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->createProfileModel($user);
        $input = $this->app->getInput();

        $originalLayout   = $input->get('layout', null, 'raw');
        $originalCoreData = $this->app->getUserState(self::CORE_PROFILE_STATE_KEY);

        $input->set('layout', 'edit');
        $this->app->setUserState(self::CORE_PROFILE_STATE_KEY, null);

        try {
            $data = $model->getData();
            $form = $model->getForm();
        } finally {
            $input->set('layout', $originalLayout);
            $this->app->setUserState(self::CORE_PROFILE_STATE_KEY, $originalCoreData);
        }

        if (!$data instanceof User) {
            throw new \RuntimeException('The com_users profile data is unavailable.', 500);
        }

        if (!$form instanceof Form) {
            throw new \RuntimeException('The com_users profile form is unavailable.', 500);
        }

        $this->projectForm($form, $context);

        // The identity never comes from the browser. Keeping it in the form's
        // internal data registry lets Joomla's uniqueness rules exclude the
        // authenticated user's own database row.
        $form->bind(['id' => (int) $user->id]);

        $pendingData = $this->app->getUserState($this->getStateKey($context), []);

        if (\is_array($pendingData) && $pendingData !== []) {
            $form->bind($this->projectSubmittedData($context, $pendingData));
        }

        $params = $model->getState('params');

        return [
            'data'   => $data,
            'form'   => $form,
            'model'  => $model,
            'params' => $params instanceof Registry ? $params : new Registry(),
        ];
    }

    /**
     * Reduce submitted or normalised data to the context's explicit allowlist.
     *
     * @param   string                $context  Projection context.
     * @param   array<string, mixed>  $data     Untrusted request data.
     *
     * @return array<string, mixed>
     */
    public function projectSubmittedData(string $context, array $data): array
    {
        $context = $this->normaliseContext($context);
        $result  = [];

        foreach (self::ALLOWED_FIELDS[$context] as $group => $fieldNames) {
            if ($group === '') {
                foreach ($fieldNames as $fieldName) {
                    if (\array_key_exists($fieldName, $data)) {
                        $result[$fieldName] = $data[$fieldName];
                    }
                }

                continue;
            }

            $groupData = $data[$group] ?? null;

            if (!\is_array($groupData)) {
                continue;
            }

            foreach ($fieldNames as $fieldName) {
                if (\array_key_exists($fieldName, $groupData)) {
                    $result[$group][$fieldName] = $groupData[$fieldName];
                }
            }
        }

        return $result;
    }

    /**
     * Merge validated projected values with server-controlled core properties.
     *
     * ProfileModel::save() expects the complete core identity fields even
     * though CopyMyPage deliberately exposes only a small subset per form.
     *
     * @param   string                $context    Projection context.
     * @param   User                  $user       Authenticated user.
     * @param   array<string, mixed>  $validated  Validated projected values.
     *
     * @return array<string, mixed>
     */
    public function buildSaveData(string $context, User $user, array $validated): array
    {
        $context   = $this->normaliseContext($context);
        $validated = $this->projectSubmittedData($context, $validated);
        $saveData  = [
            'email1'    => (string) $user->email,
            'id'        => (int) $user->id,
            'name'      => (string) $user->name,
            'password1' => '',
            'password2' => '',
            'username'  => (string) $user->username,
        ];

        if ($context === self::CONTEXT_PROFILE_EDIT) {
            $saveData['name']   = (string) ($validated['name'] ?? '');
            $saveData['email1'] = (string) ($validated['email1'] ?? '');

            foreach (['privacyconsent', 'terms'] as $group) {
                if (isset($validated[$group]) && \is_array($validated[$group])) {
                    $saveData[$group] = $validated[$group];
                }
            }

            return $saveData;
        }

        $saveData['password1'] = (string) ($validated['password1'] ?? '');
        $saveData['password2'] = (string) ($validated['password2'] ?? '');

        return $saveData;
    }

    /**
     * Return non-sensitive values which may survive a validation redirect.
     *
     * @param   string                $context  Projection context.
     * @param   array<string, mixed>  $data     Submitted projected data.
     *
     * @return array<string, mixed>
     */
    public function getFailureState(string $context, array $data): array
    {
        $context = $this->normaliseContext($context);

        return $context === self::CONTEXT_PROFILE_EDIT
            ? $this->projectSubmittedData($context, $data)
            : [];
    }

    /**
     * Return the isolated CopyMyPage state key for a context.
     */
    public function getStateKey(string $context): string
    {
        return match ($this->normaliseContext($context)) {
            self::CONTEXT_PROFILE_EDIT  => self::PROFILE_STATE_KEY,
            self::CONTEXT_SECURITY_EDIT => self::SECURITY_STATE_KEY,
        };
    }

    /**
     * Create a current-user-pinned Joomla ProfileModel.
     */
    private function createProfileModel(User $user): ProfileModel
    {
        Form::addFormPath(JPATH_SITE . '/components/com_users/forms');

        $model = $this->app
            ->bootComponent('com_users')
            ->getMVCFactory()
            ->createModel('Profile', 'Site', ['ignore_request' => true]);

        if (!$model instanceof ProfileModel) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_MODELCLASS_NOT_FOUND'), 500);
        }

        $model->setState('user.id', (int) $user->id);

        return $model;
    }

    /**
     * Remove all prepared form fields not assigned to the selected context.
     */
    private function projectForm(Form $form, string $context): void
    {
        $allowed = self::ALLOWED_FIELDS[$context];
        $fields  = [];
        $xml     = $form->getXml();

        if (!$xml instanceof \SimpleXMLElement) {
            throw new \RuntimeException('The com_users profile form definition is unavailable.', 500);
        }

        foreach ($xml->xpath('//field[@name and not(ancestor::field)]') ?: [] as $field) {
            $groupAttributes = $field->xpath('ancestor::fields[@name]/@name') ?: [];
            $group           = implode('.', array_map('strval', $groupAttributes));

            $fields[] = [
                'group' => $group,
                'name'  => (string) $field['name'],
            ];
        }

        foreach ($fields as $field) {
            $group = $field['group'];
            $name  = $field['name'];

            if (\in_array($name, $allowed[$group] ?? [], true)) {
                continue;
            }

            $form->removeField($name, $group !== '' ? $group : null);
        }

        if ($context === self::CONTEXT_SECURITY_EDIT) {
            $form->setFieldAttribute('password1', 'required', 'true');
            $form->setFieldAttribute('password2', 'required', 'true');
        }
    }

    /**
     * Validate and normalize a projection context.
     */
    private function normaliseContext(string $context): string
    {
        $context = strtolower(trim($context));

        if (!isset(self::ALLOWED_FIELDS[$context])) {
            throw new \InvalidArgumentException('Unknown CopyMyPage user form context.');
        }

        return $context;
    }
}
