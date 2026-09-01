<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_copymypage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

/**
 * CopyMyPage Service Provider + Installer Script
 *
 * Registers the installer callbacks, removes legacy shared-library files and
 * provisions the component-owned account menu without overwriting edits.
 *
 * @since 0.0.1
 */
return new class () implements ServiceProviderInterface
{
    /**
     * Registers services to the Joomla DI container.
     *
     * @param   Container  $container
     * @return  void
     *
     * @since   0.0.1
     */
    public function register(Container $container): void
    {
        // Register an anonymous installer script into the container.
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                private const TERMS_ARTICLE_ALIAS = 'allgemeine-geschaeftsbedingungen';

                private const TERMS_ARTICLE_NOTE = 'system: com_copymypage ticket-terms';

                private const TERMS_CATEGORY_ALIAS = 'copymypage-legal';

                private const TERMS_PLACEHOLDER_HTML = '<p>Allgemeine Geschäftsbedingungen Test</p>';

                /**
                 * Runs before install/update/discover_install.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Install callback.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Update callback.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Uninstall callback.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs after install/update/discover_install.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $this->removeLegacyWebAssetItems();

                    if ($type === 'uninstall') {
                        return true;
                    }

                    // Ensure the component-owned account navigation and avatar field.
                    if (\in_array($type, ['install', 'update', 'discover_install'], true)) {
                        try {
                            $this->ensureAccountMenu($adapter);
                        } catch (\Throwable $exception) {
                            Factory::getApplication()->enqueueMessage(
                                'CopyMyPage account menu setup failed: ' . $exception->getMessage(),
                                'warning'
                            );
                        } finally {
                            $this->clearAccountMenuCaches();
                        }

                        try {
                            $this->ensureAvatarField();
                        } catch (\Throwable $exception) {
                            Factory::getApplication()->enqueueMessage(
                                'CopyMyPage avatar field setup failed: ' . $exception->getMessage(),
                                'warning'
                            );
                        }

                        try {
                            $articleId = $this->ensureTermsArticle($adapter);

                            if ($articleId <= 0) {
                                throw new \RuntimeException(
                                    'The CopyMyPage terms article could not be resolved.'
                                );
                            }

                            $termsConfigured = $this->configureDPCalendarTerms($articleId);
                            $articleUrl = 'index.php?option=com_content&task=article.edit&id=' . $articleId;

                            Factory::getApplication()->enqueueMessage(
                                'CopyMyPage terms article is ready. Review it before publication: '
                                    . '<a href="' . $articleUrl . '">Edit article</a>.',
                                'success'
                            );

                            if (!$termsConfigured) {
                                Factory::getApplication()->enqueueMessage(
                                    'DPCalendar has no default terms article yet. '
                                        . 'Select the generated CopyMyPage article in the DPCalendar settings.',
                                    'warning'
                                );
                            }
                        } catch (\Throwable $exception) {
                            Log::add(
                                'CopyMyPage terms article setup failed: ' . $exception->getMessage(),
                                Log::WARNING,
                                'jerror'
                            );
                            Factory::getApplication()->enqueueMessage(
                                'CopyMyPage terms article setup failed: ' . $exception->getMessage(),
                                'warning'
                            );
                        }
                    }

                    return true;
                }

                /**
                 * Ensure that one reusable German terms article exists.
                 *
                 * Existing editorial content is preserved. Only an empty article or the
                 * known local test placeholder is populated from the bundled starter text.
                 *
                 * @since  0.0.20
                 */
                private function ensureTermsArticle(InstallerAdapter $adapter): int
                {
                    $app     = Factory::getApplication();
                    $db      = Factory::getContainer()->get(DatabaseInterface::class);
                    $article = $this->findTermsArticle($db);

                    if ($article) {
                        $this->ensureTermsArticlePresentation($db, $article);
                    }

                    if ($article && !$this->isTermsPlaceholder($article)) {
                        return (int) $article->id;
                    }

                    $termsHtml = $this->loadTermsTemplate($adapter);

                    if ($article) {
                        $record               = new \stdClass();
                        $record->id           = (int) $article->id;
                        $record->introtext    = $termsHtml;
                        $record->fulltext     = '';
                        $record->note         = self::TERMS_ARTICLE_NOTE;
                        $record->modified     = Factory::getDate()->toSql();
                        $record->modified_by  = (int) ($app->getIdentity()->id ?? 0);

                        if (!$db->updateObject('#__content', $record, 'id')) {
                            throw new \RuntimeException(
                                'The existing CopyMyPage terms placeholder could not be populated.'
                            );
                        }

                        return (int) $article->id;
                    }

                    $categoryId  = $this->ensureTermsCategory($db);
                    $articleModel = $app->bootComponent('com_content')
                        ->getMVCFactory()
                        ->createModel('Article', 'Administrator', ['ignore_request' => true]);
                    $createdBy   = (int) ($app->getIdentity()->id ?? 0);
                    $now         = Factory::getDate()->toSql();
                    $articleData = [
                        'id'               => 0,
                        'title'            => 'Allgemeine Geschäftsbedingungen',
                        'alias'            => self::TERMS_ARTICLE_ALIAS,
                        'introtext'        => $termsHtml,
                        'fulltext'         => '',
                        'state'            => 1,
                        'catid'            => $categoryId,
                        'created'          => $now,
                        'created_by'       => $createdBy,
                        'created_by_alias' => 'CopyMyPage',
                        'publish_up'       => $now,
                        'access'           => 1,
                        'language'         => 'de-DE',
                        'note'             => self::TERMS_ARTICLE_NOTE,
                        'featured'         => 0,
                        'images'           => [
                            'image_intro'            => '',
                            'image_intro_alt'        => '',
                            'float_intro'            => '',
                            'image_intro_caption'    => '',
                            'image_fulltext'         => '',
                            'image_fulltext_alt'     => '',
                            'float_fulltext'         => '',
                            'image_fulltext_caption' => '',
                        ],
                        'urls' => [
                            'urla'      => '',
                            'urlatext'  => '',
                            'targeta'   => '',
                            'urlb'      => '',
                            'urlbtext'  => '',
                            'targetb'   => '',
                            'urlc'      => '',
                            'urlctext'  => '',
                            'targetc'   => '',
                        ],
                        'attribs' => json_encode(
                            [
                                'show_title'           => 1,
                                'link_titles'          => 0,
                                'show_intro'           => 1,
                                'show_category'        => 0,
                                'show_author'          => 0,
                                'show_create_date'     => 0,
                                'show_modify_date'     => 1,
                                'show_publish_date'    => 0,
                                'show_hits'            => 0,
                                'show_item_navigation' => 0,
                            ],
                            JSON_UNESCAPED_SLASHES
                        ),
                        'metadata' => json_encode(
                            [
                                'robots' => 'noindex, follow',
                                'author' => '',
                                'rights' => '',
                            ],
                            JSON_UNESCAPED_SLASHES
                        ),
                    ];

                    if (!$articleModel || !$articleModel->save($articleData)) {
                        $error = $articleModel ? $articleModel->getError() : '';

                        throw new \RuntimeException(
                            'The CopyMyPage terms article could not be saved. ' . (string) $error
                        );
                    }

                    $articleId = (int) $articleModel->getState('article.id');

                    if ($articleId > 0) {
                        return $articleId;
                    }

                    $article = $this->findTermsArticle($db);

                    return $article ? (int) $article->id : 0;
                }

                /**
                 * Locate a generated or deliberately pre-existing general terms article.
                 *
                 * @since  0.0.20
                 */
                private function findTermsArticle(DatabaseInterface $db): ?object
                {
                    $note  = self::TERMS_ARTICLE_NOTE;
                    $query = $db->getQuery(true)
                        ->select($db->quoteName(['id', 'attribs', 'introtext', 'note']))
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('note') . ' = :note')
                        ->bind(':note', $note, ParameterType::STRING)
                        ->setLimit(1);
                    $article = $db->setQuery($query)->loadObject();

                    if ($article) {
                        return $article;
                    }

                    $alias = self::TERMS_ARTICLE_ALIAS;
                    $query = $db->getQuery(true)
                        ->select($db->quoteName(['id', 'attribs', 'introtext', 'note']))
                        ->from($db->quoteName('#__content'))
                        ->where($db->quoteName('alias') . ' = :alias')
                        ->bind(':alias', $alias, ParameterType::STRING)
                        ->order($db->quoteName('id') . ' ASC')
                        ->setLimit(1);

                    return $db->setQuery($query)->loadObject() ?: null;
                }

                /**
                 * Prevent inherited article navigation from offering a route out of checkout.
                 *
                 * An explicit editorial choice remains untouched; only Joomla's inherited
                 * empty value is resolved for the terms article.
                 *
                 * @since  0.0.20
                 */
                private function ensureTermsArticlePresentation(DatabaseInterface $db, object $article): void
                {
                    $attribs = new Registry((string) ($article->attribs ?? ''));

                    if ((string) $attribs->get('show_item_navigation', '') !== '') {
                        return;
                    }

                    $attribs->set('show_item_navigation', 0);

                    $record          = new \stdClass();
                    $record->id      = (int) $article->id;
                    $record->attribs = $attribs->toString();

                    if (!$db->updateObject('#__content', $record, 'id')) {
                        throw new \RuntimeException(
                            'The CopyMyPage terms article presentation could not be updated.'
                        );
                    }

                    $article->attribs = $record->attribs;
                }

                /**
                 * Restrict automatic content replacement to the known starter state.
                 *
                 * @since  0.0.20
                 */
                private function isTermsPlaceholder(object $article): bool
                {
                    $content = preg_replace('/\s+/', ' ', trim((string) ($article->introtext ?? '')));

                    return $content === '' || $content === self::TERMS_PLACEHOLDER_HTML;
                }

                /**
                 * Load and personalise the bundled German starter terms.
                 *
                 * @since  0.0.20
                 */
                private function loadTermsTemplate(InstallerAdapter $adapter): string
                {
                    $sourceRoot = rtrim((string) $adapter->getParent()->getPath('source'), '/\\');
                    $sourceFile = '';
                    $candidates = [
                        $sourceRoot . '/admin/terms/terms-and-conditions.de-DE.html',
                        $sourceRoot . '/terms/terms-and-conditions.de-DE.html',
                        __DIR__ . '/terms/terms-and-conditions.de-DE.html',
                    ];

                    foreach ($candidates as $candidate) {
                        if (is_file($candidate)) {
                            $sourceFile = $candidate;
                            break;
                        }
                    }

                    $termsHtml = $sourceFile !== ''
                        ? (string) file_get_contents($sourceFile)
                        : '';

                    if (trim($termsHtml) === '') {
                        throw new \RuntimeException('The CopyMyPage terms source file is missing or empty.');
                    }

                    $app      = Factory::getApplication();
                    $siteName = trim((string) $app->get('sitename', ''));
                    $mailFrom = trim((string) $app->get('mailfrom', ''));
                    $updated  = Factory::getDate()->format('d.m.Y');
                    $escape   = static fn(string $value): string => htmlspecialchars(
                        $value,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    return strtr(
                        $termsHtml,
                        [
                            '{{SITE_NAME}}'     => $escape($siteName !== '' ? $siteName : '[Name der Website]'),
                            '{{CONTACT_EMAIL}}' => $escape($mailFrom !== '' ? $mailFrom : 'kontakt@example.invalid'),
                            '{{UPDATED_AT}}'    => $escape($updated),
                        ]
                    );
                }

                /**
                 * Create the legal-content category when no reusable article exists.
                 *
                 * @since  0.0.20
                 */
                private function ensureTermsCategory(DatabaseInterface $db): int
                {
                    $extension = 'com_content';
                    $alias     = self::TERMS_CATEGORY_ALIAS;
                    $query     = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__categories'))
                        ->where($db->quoteName('extension') . ' = :extension')
                        ->where($db->quoteName('alias') . ' = :alias')
                        ->bind(':extension', $extension, ParameterType::STRING)
                        ->bind(':alias', $alias, ParameterType::STRING);
                    $categoryId = (int) $db->setQuery($query)->loadResult();

                    if ($categoryId > 0) {
                        return $categoryId;
                    }

                    $categoryModel = Factory::getApplication()
                        ->bootComponent('com_categories')
                        ->getMVCFactory()
                        ->createModel('Category', 'Administrator', ['ignore_request' => true]);
                    $categoryData = [
                        'id'          => 0,
                        'parent_id'   => 1,
                        'title'       => 'CopyMyPage Rechtliches',
                        'alias'       => self::TERMS_CATEGORY_ALIAS,
                        'description' => '',
                        'extension'   => 'com_content',
                        'published'   => 1,
                        'access'      => 1,
                        'language'    => '*',
                        'params'      => [
                            'category_layout' => '',
                            'image'           => '',
                        ],
                        'metadata' => [
                            'author' => '',
                            'robots' => '',
                        ],
                        'note' => 'system: com_copymypage legal-content',
                    ];

                    if (!$categoryModel || !$categoryModel->save($categoryData)) {
                        $error = $categoryModel ? $categoryModel->getError() : '';

                        throw new \RuntimeException(
                            'The CopyMyPage legal-content category could not be saved. ' . (string) $error
                        );
                    }

                    $categoryId = (int) $categoryModel->getState('category.id');

                    if ($categoryId <= 0) {
                        throw new \RuntimeException(
                            'The CopyMyPage legal-content category has no valid id.'
                        );
                    }

                    return $categoryId;
                }

                /**
                 * Select the generated article as DPCalendar's default without replacing a choice.
                 *
                 * @since  0.0.20
                 */
                private function configureDPCalendarTerms(int $articleId): bool
                {
                    $db       = Factory::getContainer()->get(DatabaseInterface::class);
                    $type     = 'component';
                    $element  = 'com_dpcalendar';
                    $clientId = 1;
                    $query    = $db->getQuery(true)
                        ->select($db->quoteName(['extension_id', 'params']))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('element') . ' = :element')
                        ->where($db->quoteName('client_id') . ' = :clientId')
                        ->bind(':type', $type, ParameterType::STRING)
                        ->bind(':element', $element, ParameterType::STRING)
                        ->bind(':clientId', $clientId, ParameterType::INTEGER);
                    $component = $db->setQuery($query)->loadObject();

                    if (!$component) {
                        return false;
                    }

                    $params = new Registry((string) $component->params);

                    if ((int) $params->get('event_form_terms', 0) > 0) {
                        return true;
                    }

                    $params->set('event_form_terms', $articleId);
                    $record               = new \stdClass();
                    $record->extension_id = (int) $component->extension_id;
                    $record->params       = $params->toString();

                    return $db->updateObject('#__extensions', $record, 'extension_id');
                }

                /**
                 * Remove CopyMyPage AssetItems formerly installed in Joomla's core namespace.
                 *
                 * A content marker prevents deletion when another extension owns a file with
                 * the same generic name.
                 *
                 * @since  0.0.19
                 */
                private function removeLegacyWebAssetItems(): void
                {
                    $legacyFiles = [
                        'ContentDrawerAssetItem.php' => 'COM_COPYMYPAGE_CONTENT_DRAWER_CLOSE',
                        'CopyMyPageAssetItem.php'    => 'final class CopyMyPageAssetItem',
                        'IsotopeAssetItem.php'       => "Joomla.getOptions('copymypage.params'",
                        'MmenuLightAssetItem.php'    => 'data-cmp-mmenulight-open',
                        'PureCounterAssetItem.php'   => 'cmp-purecounter-pending',
                        'TicketsAssetItem.php'       => 'MOD_COPYMYPAGE_TICKETS_JS_RUNTIME_MISSING',
                    ];

                    $legacyDirectory = Path::clean(JPATH_LIBRARIES . '/src/WebAsset/AssetItem');

                    foreach ($legacyFiles as $fileName => $ownershipMarker) {
                        $path = Path::clean($legacyDirectory . '/' . $fileName);

                        if (!is_file($path)) {
                            continue;
                        }

                        $contents = file_get_contents($path);

                        if (!\is_string($contents) || !str_contains($contents, $ownershipMarker)) {
                            Log::add(
                                'Skipped legacy CopyMyPage AssetItem without the expected ownership marker: ' . $path,
                                Log::WARNING,
                                'jerror'
                            );

                            continue;
                        }

                        if (!File::delete($path) && file_exists($path)) {
                            Log::add(
                                Text::sprintf('JLIB_INSTALLER_ERROR_DELETE_FILE', $path),
                                Log::WARNING,
                                'jerror'
                            );
                        }
                    }
                }

                /**
                 * Provision the system-managed Joomla Custom User Field for avatars.
                 *
                 * @since  0.0.17
                 */
                private function ensureAvatarField(): void
                {
                    $db      = Factory::getContainer()->get(DatabaseInterface::class);
                    $context = 'com_users.user';
                    $name    = 'copymypage-avatar';
                    $query   = $db->getQuery(true)
                        ->select($db->quoteName(['id', 'asset_id', 'type']))
                        ->from($db->quoteName('#__fields'))
                        ->where($db->quoteName('context') . ' = :context')
                        ->where($db->quoteName('name') . ' = :name')
                        ->bind(':context', $context)
                        ->bind(':name', $name);
                    $field = $db->setQuery($query)->loadObject();

                    if ($field) {
                        if ((string) $field->type !== 'media') {
                            throw new \RuntimeException(
                                'The existing copymypage-avatar field is not a Media field.'
                            );
                        }

                        $this->ensureAvatarFieldPermission($db, (int) $field->asset_id);

                        return;
                    }

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__fields'))
                        ->where($db->quoteName('name') . ' = :name')
                        ->bind(':name', $name);

                    if ((int) $db->setQuery($query)->loadResult() > 0) {
                        throw new \RuntimeException(
                            'The stable copymypage-avatar field name is already used in another context.'
                        );
                    }

                    $registeredGroupId = $this->getRegisteredUserGroupId($db);
                    $table             = Factory::getApplication()
                        ->bootComponent('com_fields')
                        ->getMVCFactory()
                        ->createTable('Field', 'Administrator', ['dbo' => $db]);
                    $data = [
                        'access'              => 1,
                        'context'             => $context,
                        'default_value'       => '',
                        'description'         => 'System-managed profile image used by the CopyMyPage Dashboard.',
                        'fieldparams'         => [
                            'directory'   => 'copymypage/avatars',
                            'image_class' => '',
                            'preview'     => 'true',
                            'types'       => 'images',
                        ],
                        'group_id'            => 0,
                        'label'               => 'CopyMyPage profile image',
                        'language'            => '*',
                        'name'                => $name,
                        'note'                => 'copymypage.system.avatar',
                        'only_use_in_subform' => 0,
                        'ordering'            => 0,
                        'params'              => [
                            'class'              => '',
                            'display'            => '0',
                            'display_readonly'   => '0',
                            'hint'               => '',
                            'label_class'        => '',
                            'label_render_class' => '',
                            'layout'             => '',
                            'prefix'             => '',
                            'render_class'       => '',
                            'searchindex'        => '0',
                            'show_on'            => '2',
                            'showlabel'          => '1',
                            'suffix'             => '',
                        ],
                        'required'             => 0,
                        'rules'                => [
                            'core.edit.value' => [(string) $registeredGroupId => true],
                        ],
                        'state'                => 1,
                        'title'                => 'CopyMyPage profile image',
                        'type'                 => 'media',
                    ];

                    if (!$table || !$table->bind($data) || !$table->check() || !$table->store()) {
                        $message = $table && method_exists($table, 'getError')
                            ? (string) $table->getError()
                            : '';

                        throw new \RuntimeException(
                            $message !== '' ? $message : 'The CopyMyPage avatar field could not be stored.'
                        );
                    }

                    $this->ensureAvatarFieldPermission($db, (int) $table->asset_id);
                }

                /**
                 * Ensure ordinary authenticated users may update this field value only.
                 *
                 * @since  0.0.17
                 */
                private function ensureAvatarFieldPermission(
                    DatabaseInterface $db,
                    int $assetId
                ): void {
                    if ($assetId <= 0) {
                        throw new \RuntimeException('The CopyMyPage avatar field has no asset record.');
                    }

                    $query = $db->getQuery(true)
                        ->select($db->quoteName('rules'))
                        ->from($db->quoteName('#__assets'))
                        ->where($db->quoteName('id') . ' = :assetId')
                        ->bind(':assetId', $assetId, ParameterType::INTEGER);
                    $rules = json_decode((string) $db->setQuery($query)->loadResult(), true);
                    $rules = \is_array($rules) ? $rules : [];
                    $rules['core.edit.value'] = \is_array($rules['core.edit.value'] ?? null)
                        ? $rules['core.edit.value']
                        : [];
                    $rules['core.edit.value'][(string) $this->getRegisteredUserGroupId($db)] = 1;
                    $asset        = new \stdClass();
                    $asset->id    = $assetId;
                    $asset->rules = json_encode($rules, JSON_UNESCAPED_SLASHES);

                    if (!\is_string($asset->rules)) {
                        throw new \RuntimeException('The CopyMyPage avatar field rules could not be encoded.');
                    }

                    $db->updateObject('#__assets', $asset, 'id');
                }

                /**
                 * Resolve Joomla's standard Registered group without hard-coding a field id.
                 *
                 * @since  0.0.17
                 */
                private function getRegisteredUserGroupId(DatabaseInterface $db): int
                {
                    $title = 'Registered';
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__usergroups'))
                        ->where($db->quoteName('title') . ' = :title')
                        ->bind(':title', $title);

                    return (int) ($db->setQuery($query)->loadResult() ?: 2);
                }

                /**
                 * Ensure the dedicated account menu and manifest-defined defaults.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenu(InstallerAdapter $adapter): void
                {
                    $manifest = $adapter->getManifest();

                    if (!isset($manifest->accountMenuBootstrap)) {
                        return;
                    }

                    $db          = Factory::getContainer()->get(DatabaseInterface::class);
                    $componentId = $this->getComponentId($db);

                    if ($componentId === 0) {
                        throw new \RuntimeException('com_copymypage extension id was not found.');
                    }

                    $this->ensureAccountMenuType($db);

                    foreach ($manifest->accountMenuBootstrap->item as $item) {
                        $this->ensureAccountMenuItem($db, $componentId, $item);
                    }
                }

                /**
                 * Clear menu caches even when a multi-item bootstrap stopped partway through.
                 *
                 * @since  0.0.17
                 */
                private function clearAccountMenuCaches(): void
                {
                    try {
                        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);

                        foreach (['com_menus', 'com_modules', 'mod_menu'] as $group) {
                            $cacheFactory
                                ->createCacheController('', ['defaultgroup' => $group])
                                ->clean();
                        }
                    } catch (\Throwable $exception) {
                        Log::add(
                            'CopyMyPage account menu cache cleanup failed: ' . $exception->getMessage(),
                            Log::WARNING,
                            'jerror'
                        );
                    }
                }

                /**
                 * Create the account menu type when it does not exist.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenuType(DatabaseInterface $db): void
                {
                    $table = Factory::getApplication()
                        ->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('MenuType', 'Administrator', ['dbo' => $db]);

                    if ($table->load(['menutype' => 'copymypage-account'])) {
                        return;
                    }

                    $data = [
                        'id'          => 0,
                        'menutype'    => 'copymypage-account',
                        'title'       => 'CopyMyPage Account',
                        'description' => 'Navigation for the CopyMyPage personal account area',
                        'client_id'   => 0,
                    ];

                    if (!$table->bind($data) || !$table->check() || !$table->store()) {
                        throw new \RuntimeException('The CopyMyPage account menu type could not be created.');
                    }
                }

                /**
                 * Create one missing account destination without overwriting user changes.
                 *
                 * @since  0.0.17
                 */
                private function ensureAccountMenuItem(
                    DatabaseInterface $db,
                    int $componentId,
                    \SimpleXMLElement $item
                ): void {
                    $key   = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $item['key'])) ?? '';
                    $title = trim((string) $item['title']);
                    $alias = trim((string) $item['alias']);
                    $icon  = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $item['icon'])) ?? '';
                    $link  = html_entity_decode(
                        trim((string) $item['link']),
                        ENT_QUOTES | ENT_XML1,
                        'UTF-8'
                    );

                    if ($key === '' || $title === '' || $alias === '' || $link === '') {
                        throw new \RuntimeException('An account menu manifest item is incomplete.');
                    }

                    $note = 'copymypage.account.' . $key;

                    if ($this->accountMenuItemExists($db, $note, $link, $alias)) {
                        return;
                    }

                    $table = Factory::getApplication()
                        ->bootComponent('com_menus')
                        ->getMVCFactory()
                        ->createTable('Menu', 'Administrator', ['dbo' => $db]);
                    $data = [
                        'id'           => 0,
                        'menutype'     => 'copymypage-account',
                        'title'        => $title,
                        'alias'        => $alias,
                        'note'         => $note,
                        'type'         => 'component',
                        'link'         => $link,
                        'component_id' => $componentId,
                        'published'    => 1,
                        'parent_id'    => 1,
                        'level'        => 1,
                        'access'       => $this->getRegisteredViewLevelId($db),
                        'client_id'    => 0,
                        'home'         => 0,
                        'language'     => '*',
                        'params'       => json_encode(
                            [
                                'copymypage_account_icon' => $icon,
                                'copymypage_account_key'  => $key,
                                'menu_show'               => 1,
                            ],
                            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        ),
                    ];

                    if (!$table->bind($data)) {
                        throw new \RuntimeException('An account menu item could not be bound.');
                    }

                    $table->setLocation(1, 'last-child');

                    if (!$table->check() || !$table->store() || !$table->rebuildPath((int) $table->id)) {
                        throw new \RuntimeException('An account menu item could not be stored.');
                    }
                }

                /**
                 * Check stable note first, then canonical link and alias fallbacks.
                 *
                 * @since  0.0.17
                 */
                private function accountMenuItemExists(
                    DatabaseInterface $db,
                    string $note,
                    string $link,
                    string $alias
                ): bool {
                    $menuType = 'copymypage-account';
                    $query    = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__menu'))
                        ->where($db->quoteName('menutype') . ' = :menutype')
                        ->where(
                            '('
                            . $db->quoteName('note') . ' = :note'
                            . ' OR ' . $db->quoteName('link') . ' = :link'
                            . ' OR ' . $db->quoteName('alias') . ' = :alias'
                            . ')'
                        )
                        ->bind(':menutype', $menuType)
                        ->bind(':note', $note)
                        ->bind(':link', $link)
                        ->bind(':alias', $alias);

                    return (int) $db->setQuery($query)->loadResult() > 0;
                }

                /**
                 * Resolve the installed component extension id.
                 *
                 * @since  0.0.17
                 */
                private function getComponentId(DatabaseInterface $db): int
                {
                    $element  = 'com_copymypage';
                    $type     = 'component';
                    $clientId = 1;
                    $query    = $db->getQuery(true)
                        ->select($db->quoteName('extension_id'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('element') . ' = :element')
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('client_id') . ' = :clientId')
                        ->bind(':element', $element)
                        ->bind(':type', $type)
                        ->bind(':clientId', $clientId, ParameterType::INTEGER);

                    return (int) $db->setQuery($query)->loadResult();
                }

                /**
                 * Resolve the standard Registered view level with a safe fallback.
                 *
                 * @since  0.0.17
                 */
                private function getRegisteredViewLevelId(DatabaseInterface $db): int
                {
                    $title = 'Registered';
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__viewlevels'))
                        ->where($db->quoteName('title') . ' = :title')
                        ->bind(':title', $title);

                    return (int) ($db->setQuery($query)->loadResult() ?: 2);
                }
            }
        );
    }
};
