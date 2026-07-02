<?php
/**
 * @package     Joomla.Site
 * @subpackage  Modules.CopyMyPage
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 3 or later
 * @since       0.0.16
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;

return new class () implements ServiceProviderInterface
{
    /**
     * Register the module installer lifecycle.
     *
     * @param   Container  $container  DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                private const CATEGORY_ALIAS = 'copymypage-privacy';
                private const ARTICLE_ALIAS = 'datenschutzerklaerung-copymypage';
                private const ARTICLE_NOTE = 'system: mod_copymypage_contact privacy-policy';

                /**
                 * Runs before install or update.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on fresh installation.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on update.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Preserve the generated privacy article during uninstall.
                 *
                 * Administrators may have edited or linked the article after installation,
                 * so removing module files must not delete site content.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Ensure that a reusable privacy-policy article exists after install/update.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'discover_install', 'update'], true)) {
                        return true;
                    }

                    $app = Factory::getApplication();

                    try {
                        $articleId = $this->ensurePrivacyArticle($adapter);

                        if ($articleId <= 0) {
                            throw new \RuntimeException(Text::_('MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_ARTICLE_FAILED'));
                        }

                        $consentConfigured = $this->configureConfirmConsentPlugin($articleId);
                        $articleUrl = 'index.php?option=com_content&task=article.edit&id=' . $articleId;

                        $app->enqueueMessage(
                            Text::sprintf(
                                'MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_ARTICLE_READY',
                                $articleUrl
                            ),
                            'success'
                        );

                        if (!$consentConfigured) {
                            $app->enqueueMessage(
                                Text::_('MOD_COPYMYPAGE_CONTACT_INSTALL_CONFIRMCONSENT_REVIEW'),
                                'warning'
                            );
                        }
                    } catch (\Throwable $exception) {
                        Log::add(
                            $exception->getMessage(),
                            Log::WARNING,
                            'jerror'
                        );

                        $app->enqueueMessage(
                            Text::sprintf(
                                'MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_ARTICLE_ERROR',
                                $exception->getMessage()
                            ),
                            'warning'
                        );
                    }

                    // Keep the extension installed even if optional starter content fails.
                    return true;
                }

                /**
                 * Create the dedicated content category and privacy article when absent.
                 *
                 * @param   InstallerAdapter  $adapter  Module installer adapter.
                 *
                 * @return  int  Article ID.
                 */
                private function ensurePrivacyArticle(InstallerAdapter $adapter): int
                {
                    $app        = Factory::getApplication();
                    $db         = Factory::getContainer()->get(DatabaseInterface::class);
                    $categoryId = $this->findCategoryId($db);

                    if ($categoryId <= 0) {
                        $categoryModel = $app->bootComponent('com_categories')
                            ->getMVCFactory()
                            ->createModel('Category', 'Administrator', ['ignore_request' => true]);

                        $categoryData = [
                            'id'          => 0,
                            'parent_id'   => 1,
                            'title'       => 'CopyMyPage',
                            'alias'       => self::CATEGORY_ALIAS,
                            'description' => '',
                            'extension'   => 'com_content',
                            'published'   => 1,
                            'access'      => 1,
                            'language'    => '*',
                            'params'      => [
                                'category_layout' => '',
                                'image'           => '',
                            ],
                            'metadata'    => [
                                'author' => '',
                                'robots' => '',
                            ],
                            'note'        => Text::_('MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_CATEGORY_NOTE'),
                        ];

                        if (!$categoryModel || !$categoryModel->save($categoryData)) {
                            $error = $categoryModel ? $categoryModel->getError() : '';

                            throw new \RuntimeException(
                                Text::sprintf(
                                    'MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_CATEGORY_ERROR',
                                    (string) $error
                                )
                            );
                        }

                        $categoryId = (int) $categoryModel->getState('category.id');
                    }

                    $articleId = $this->findArticleId($db, $categoryId);

                    if ($articleId > 0) {
                        return $articleId;
                    }

                    $sourceRoot = (string) $adapter->getParent()->getPath('source');
                    $sourceFile = rtrim($sourceRoot, '/\\') . '/privacy/privacy-policy.de-DE.html';

                    if (!is_file($sourceFile)) {
                        $sourceFile = __DIR__ . '/privacy/privacy-policy.de-DE.html';
                    }

                    $privacyHtml = is_file($sourceFile)
                        ? (string) file_get_contents($sourceFile)
                        : '';

                    if (trim($privacyHtml) === '') {
                        throw new \RuntimeException(
                            Text::_('MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_SOURCE_MISSING')
                        );
                    }

                    $siteName = trim((string) $app->get('sitename', ''));
                    $mailFrom = trim((string) $app->get('mailfrom', ''));
                    $updated  = Factory::getDate()->format('d.m.Y');
                    $escape   = static fn(string $value): string => htmlspecialchars(
                        $value,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    );

                    $privacyHtml = strtr(
                        $privacyHtml,
                        [
                            '{{SITE_NAME}}'     => $escape($siteName !== '' ? $siteName : '[Name der Website]'),
                            '{{CONTACT_EMAIL}}' => $escape($mailFrom !== '' ? $mailFrom : 'datenschutz@example.invalid'),
                            '{{UPDATED_AT}}'    => $escape($updated),
                        ]
                    );

                    $articleModel = $app->bootComponent('com_content')
                        ->getMVCFactory()
                        ->createModel('Article', 'Administrator', ['ignore_request' => true]);

                    $createdBy = (int) ($app->getIdentity()->id ?? 0);
                    $now       = Factory::getDate()->toSql();
                    $articleData = [
                        'id'               => 0,
                        'title'            => Text::_('MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_ARTICLE_TITLE'),
                        'alias'            => self::ARTICLE_ALIAS,
                        'introtext'        => $privacyHtml,
                        'fulltext'         => '',
                        'state'            => 1,
                        'catid'            => $categoryId,
                        'created'          => $now,
                        'created_by'       => $createdBy,
                        'created_by_alias' => 'CopyMyPage',
                        'publish_up'       => $now,
                        'access'           => 1,
                        'language'         => 'de-DE',
                        'note'             => self::ARTICLE_NOTE,
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
                        'urls'             => [
                            'urla'    => '',
                            'urlatext' => '',
                            'targeta' => '',
                            'urlb'    => '',
                            'urlbtext' => '',
                            'targetb' => '',
                            'urlc'    => '',
                            'urlctext' => '',
                            'targetc' => '',
                        ],
                        'attribs'           => json_encode(
                            [
                                'show_title'            => 1,
                                'link_titles'           => 0,
                                'show_intro'            => 1,
                                'show_category'         => 0,
                                'show_author'           => 0,
                                'show_create_date'      => 0,
                                'show_modify_date'      => 1,
                                'show_publish_date'     => 0,
                                'show_hits'             => 0,
                                'show_item_navigation'  => 0,
                            ],
                            JSON_UNESCAPED_SLASHES
                        ),
                        'metadata'          => json_encode(
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
                            Text::sprintf(
                                'MOD_COPYMYPAGE_CONTACT_INSTALL_PRIVACY_ARTICLE_SAVE_ERROR',
                                (string) $error
                            )
                        );
                    }

                    $articleId = (int) $articleModel->getState('article.id');

                    return $articleId > 0
                        ? $articleId
                        : $this->findArticleId($db, $categoryId);
                }

                /**
                 * Find the CopyMyPage privacy category.
                 */
                private function findCategoryId(DatabaseInterface $db): int
                {
                    $extension = 'com_content';
                    $alias     = self::CATEGORY_ALIAS;
                    $query     = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__categories'))
                        ->where($db->quoteName('extension') . ' = :extension')
                        ->where($db->quoteName('alias') . ' = :alias')
                        ->bind(':extension', $extension, ParameterType::STRING)
                        ->bind(':alias', $alias, ParameterType::STRING);

                    return (int) $db->setQuery($query)->loadResult();
                }

                /**
                 * Find an existing generated privacy article.
                 */
                private function findArticleId(DatabaseInterface $db, int $categoryId): int
                {
                    $note  = self::ARTICLE_NOTE;
                    $alias = self::ARTICLE_ALIAS;
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__content'))
                        ->where(
                            '('
                            . $db->quoteName('note') . ' = :note'
                            . ' OR ('
                            . $db->quoteName('alias') . ' = :alias'
                            . ' AND ' . $db->quoteName('catid') . ' = :catid'
                            . '))'
                        )
                        ->bind(':note', $note, ParameterType::STRING)
                        ->bind(':alias', $alias, ParameterType::STRING)
                        ->bind(':catid', $categoryId, ParameterType::INTEGER)
                        ->setLimit(1);

                    return (int) $db->setQuery($query)->loadResult();
                }

                /**
                 * Select the generated article in confirm-consent when no source exists yet.
                 *
                 * @return  bool  True when the plugin already had a source or was configured.
                 */
                private function configureConfirmConsentPlugin(int $articleId): bool
                {
                    $db      = Factory::getContainer()->get(DatabaseInterface::class);
                    $type    = 'plugin';
                    $folder  = 'content';
                    $element = 'confirmconsent';
                    $query   = $db->getQuery(true)
                        ->select(
                            $db->quoteName(
                                ['extension_id', 'enabled', 'params']
                            )
                        )
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = :type')
                        ->where($db->quoteName('folder') . ' = :folder')
                        ->where($db->quoteName('element') . ' = :element')
                        ->bind(':type', $type, ParameterType::STRING)
                        ->bind(':folder', $folder, ParameterType::STRING)
                        ->bind(':element', $element, ParameterType::STRING);

                    $plugin = $db->setQuery($query)->loadObject();

                    if (!$plugin) {
                        return false;
                    }

                    $params      = new Registry((string) $plugin->params);
                    $privacyType = (string) $params->get('privacy_type', 'article');
                    $article     = (int) $params->get('privacy_article', 0);
                    $menuItem    = (int) $params->get('privacy_menu_item', 0);

                    if (($privacyType === 'article' && $article > 0)
                        || ($privacyType === 'menu_item' && $menuItem > 0)
                    ) {
                        return (int) $plugin->enabled === 1;
                    }

                    $params->set('privacy_type', 'article');
                    $params->set('privacy_article', $articleId);

                    $record = (object) [
                        'extension_id' => (int) $plugin->extension_id,
                        'params'       => $params->toString(),
                    ];

                    $db->updateObject('#__extensions', $record, 'extension_id');

                    return (int) $plugin->enabled === 1;
                }
            }
        );
    }
};
