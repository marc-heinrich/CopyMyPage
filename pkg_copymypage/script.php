<?php
/**
 * @package     Joomla.Site
 * @subpackage  Package.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 3 or later
 * @since       0.0.1
 *
 * @note        Slogan: CopyMyPage – Your website. Just copy it.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Installer\Manifest\PackageManifest;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Helper;

return new class () implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                /** Installed (old) and incoming (new) versions */
                protected ?string $oldVersion = null;
                protected ?string $newVersion = null;

                /** Matches #__extensions.element and manifest filename */
                protected string $element = 'pkg_copymypage';

                /** Minimum requirements */
                protected string $minimum_Joomla = '6.0';
                protected string $minimum_PHP    = JOOMLA_MINIMUM_PHP;

                /** Minimum bytes (e.g. 30 MB) */
                protected int $minimum_Byte = 31457280;

                /** Collected soft issues */
                protected array $issues = [];

                /** Flags for upgrade/downgrade notice */
                protected bool $wasDowngrade = false;
                protected bool $wasUpgrade   = false;

                // ─────────────────────────────────────────────────────────────────────────────
                // Lifecycle
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Runs before install or update starts (soft checks only).
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    // Adopt manifest <name> if it looks like a package key (pkg_*)
                    $incomingElement = $this->readIncomingElement($adapter);
                    if ($incomingElement && \preg_match('/^pkg_/i', $incomingElement)) {
                        $this->element = $incomingElement;
                    }

                    // Buffer versions (installed → incoming)
                    $this->oldVersion = $this->readInstalledVersion();
                    $this->newVersion = $this->readIncomingVersion($adapter);

                    // Mark upgrade/downgrade (soft; no abort)
                    if ($this->oldVersion && $this->newVersion) {
                        if (version_compare($this->newVersion, $this->oldVersion, '<')) {
                            $this->wasDowngrade = true;
                            $this->softLog(sprintf(
                                'CopyMyPage: downgrade detected (%s → %s).',
                                $this->oldVersion,
                                $this->newVersion
                            ));
                        } elseif (version_compare($this->newVersion, $this->oldVersion, '>')) {
                            $this->wasUpgrade = true;
                            $this->softLog(sprintf(
                                'CopyMyPage: upgrade detected (%s → %s).',
                                $this->oldVersion,
                                $this->newVersion
                            ));
                        }
                    }

                    // Run soft environment checks
                    return $this->runPreflightChecks();
                }

                /**
                 * Runs on fresh installation.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Runs on update (log only; visible box in postflight()).
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    if ($this->oldVersion && $this->newVersion) {
                        $this->softLog(sprintf(
                            'CopyMyPage updated from %s to %s.',
                            $this->oldVersion,
                            $this->newVersion
                        ));
                    }
                    return true;
                }

                /**
                 * Runs after install or update (visible messages shown here).
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $app    = Factory::getApplication();
                    $layout = new FileLayout('message');

                    // Success box for upgrades
                    if ($this->wasUpgrade && $this->oldVersion && $this->newVersion) {
                        $headline = Text::_('PKG_COPYMYPAGE_SLOGAN'); // exact project slogan
                        $body     = Text::sprintf(
                            'PKG_COPYMYPAGE_UPGRADE_SUCCESS',
                            $this->oldVersion,
                            $this->newVersion
                        );

                        $html = sprintf(
                            '<div class="alert alert-success" style="margin-top:1rem;">
                                <strong>%s</strong><br>%s
                             </div>',
                            htmlspecialchars($headline),
                            htmlspecialchars($body)
                        );

                        echo $layout->render(['msg' => $html, 'type' => 'success']);
                        $app->enqueueMessage($body, 'message');

                        $this->softLog(sprintf(
                            'CopyMyPage: successful update (%s → %s).',
                            $this->oldVersion,
                            $this->newVersion
                        ));
                    }

                    // Warning box for downgrades (soft; no abort)
                    if ($this->wasDowngrade) {
                        $headline = Text::_('PKG_COPYMYPAGE_SLOGAN');
                        $body     = Text::sprintf(
                            'PKG_COPYMYPAGE_DOWNGRADE_WARNING',
                            $this->oldVersion ?? 'unknown',
                            $this->newVersion ?? 'unknown'
                        );

                        $html = sprintf(
                            '<div class="alert alert-warning" style="margin-top:1rem;">
                                <strong>%s</strong><br>%s
                             </div>',
                            htmlspecialchars($headline),
                            htmlspecialchars($body)
                        );

                        echo $layout->render(['msg' => $html, 'type' => 'warning']);
                        $app->enqueueMessage($body, 'warning');

                        $this->softLog(sprintf(
                            'CopyMyPage: downgrade warning shown (%s → %s).',
                            $this->oldVersion ?? 'unknown',
                            $this->newVersion ?? 'unknown'
                        ));
                    }

                    // Other soft issues (PHP/Joomla/upload limits)
                    if (!empty($this->issues)) {
                        $summary = Text::sprintf(
                            'PKG_COPYMYPAGE_PREFLIGHT_ISSUES_SUMMARY',
                            \count($this->issues)
                        );
                        $app->enqueueMessage($summary, 'warning');

                        foreach ($this->issues as $issue) {
                            $app->enqueueMessage($issue, 'info');
                        }
                    }

                    return true;
                }

                /**
                 * Runs on uninstall.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                // ─────────────────────────────────────────────────────────────────────────────
                // Soft Environment Checks
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Orchestrates soft environment checks (never aborts).
                 */
                protected function runPreflightChecks(): bool
                {
                    $this->checkPhpVersion();
                    $this->checkJoomlaVersion();
                    $this->checkUploadLimits();
                    return true;
                }

                /**
                 * Soft check: recommended PHP version.
                 */
                protected function checkPhpVersion(): void
                {
                    if (version_compare(PHP_VERSION, $this->minimum_PHP, '<')) {
                        $this->issues[] = Text::sprintf(
                            'PKG_COPYMYPAGE_PHP_RECOMMENDED',
                            $this->minimum_PHP,
                            PHP_VERSION
                        );
                    }
                }

                /**
                 * Soft check: recommended Joomla version.
                 */
                protected function checkJoomlaVersion(): void
                {
                    $version = new Version();
                    $current = $version->getShortVersion();

                    if (version_compare($current, $this->minimum_Joomla, '<')) {
                        $this->issues[] = Text::sprintf(
                            'PKG_COPYMYPAGE_JOOMLA_RECOMMENDED',
                            $this->minimum_Joomla,
                            $current
                        );
                    }
                }

                /**
                 * Soft check: recommended upload limit.
                 */
                protected function checkUploadLimits(): void
                {
                    $maxUploadBytes = (int) Helper::getFileUploadMaxSize(false);

                    if ($maxUploadBytes < $this->minimum_Byte) {
                        $this->issues[] = Text::sprintf(
                            'PKG_COPYMYPAGE_UPLOAD_LIMIT_LOW',
                            $maxUploadBytes,
                            $this->minimum_Byte
                        );
                    }
                }

                // ─────────────────────────────────────────────────────────────────────────────
                // Logging helpers
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Writes a line to /administrator/logs/cmp-downgrade.log (soft info/warning).
                 */
                protected function softLog(string $message): void
                {
                    try {
                        Log::addLogger(
                            ['text_file' => 'cmp-downgrade.log', 'extension' => 'com_copymypage'],
                            Log::ALL,
                            ['com_copymypage']
                        );
                        Log::add($message, Log::INFO, 'com_copymypage');
                    } catch (\Throwable $e) {
                        // ignore logging failures
                    }
                }

                // ─────────────────────────────────────────────────────────────────────────────
                // Manifest / Version readers
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Reads incoming manifest <name>.
                 */
                protected function readIncomingElement(InstallerAdapter $adapter): ?string
                {
                    $manifest = $adapter->getManifest();
                    return isset($manifest->name) ? (string) $manifest->name : null;
                }

                /**
                 * Reads currently installed version (no aborts if not found).
                 * Priority:
                 *  1) Installed manifest via PackageManifest
                 *  2) DB lookup (#__extensions)
                 *  3) ExtensionHelper fallback
                 */
                protected function readInstalledVersion(): ?string
                {
                    // 1) Installed manifest (most reliable)
                    $manifestPath = \JPATH_MANIFESTS . '/packages/' . $this->element . '.xml';

                    if (\is_file($manifestPath) && \is_readable($manifestPath)) {
                        try {
                            $pkg = new PackageManifest($manifestPath);
                            if (!empty($pkg->version)) {
                                return (string) $pkg->version;
                            }
                        } catch (\Throwable $e) {
                            // ignore and continue
                        }
                    }

                    // 2) DB lookup by element
                    $db    = Factory::getDbo();
                    $query = $db->getQuery(true)
                        ->select($db->quoteName('manifest_cache'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote($this->element))
                        ->setLimit(1);
                    $db->setQuery($query);
                    $row = $db->loadObject();

                    if ($row && !empty($row->manifest_cache)) {
                        $cache = json_decode((string) $row->manifest_cache, true);
                        if (\is_array($cache) && !empty($cache['version'])) {
                            return (string) $cache['version'];
                        }
                    }

                    // 3) Helper fallback
                    try {
                        $ext = ExtensionHelper::getExtensionRecord($this->element, 'package');
                        if ($ext && !empty($ext->manifest_cache)) {
                            $cache = json_decode((string) $ext->manifest_cache, true);
                            if (\is_array($cache) && !empty($cache['version'])) {
                                return (string) $cache['version'];
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }

                    return null;
                }

                /**
                 * Reads incoming version from the uploaded manifest.
                 */
                protected function readIncomingVersion(InstallerAdapter $adapter): ?string
                {
                    $manifest = $adapter->getManifest();
                    return isset($manifest->version) ? (string) $manifest->version : null;
                }
            }
        );
    }
};
