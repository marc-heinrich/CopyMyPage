<?php
/**
 * @package     Joomla.Site
 * @subpackage  Package.CopyMyPage
 * @copyright   (C) 2025 Open Source Matters
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
use Joomla\CMS\Version;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Helper;

return new class () implements ServiceProviderInterface
{
    /**
     * Registers the installer script in the DI container.
     *
     * @param  Container  $container
     * @return void
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class () implements InstallerScriptInterface
            {
                /**
                 * Installed (old) and incoming (new) versions.
                 *
                 * @var string|null
                 */
                protected ?string $oldVersion = null;
                protected ?string $newVersion = null;

                /**
                 * Package element key. Must match #__extensions.element and manifest filename.
                 *
                 * @var string
                 */
                protected string $element = 'pkg_copymypage';

                /**
                 * Minimum requirements.
                 *
                 * @var string
                 */
                protected string $minimum_Joomla = '6.0';

                /**
                 * @var string
                 */
                protected string $minimum_PHP = JOOMLA_MINIMUM_PHP;

                /**
                 * Minimum bytes required to upload the package (e.g. 30 MB).
                 *
                 * @var int
                 */
                protected int $minimum_Byte = 31457280;

                /**
                 * Collected errors during checks (we do not abort; just report).
                 *
                 * @var array<int, string>
                 */
                protected array $errors = [];

                // ─────────────────────────────────────────────────────────────────────────────
                // Lifecycle
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Runs before install or update starts.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    // Optional: adopt manifest <name> if it looks like a package key (pkg_*)
                    $incomingElement = $this->readIncomingElement($adapter);
                    if ($incomingElement && \preg_match('/^pkg_/i', $incomingElement)) {
                        $this->element = $incomingElement;
                    }

                    // Buffer versions (installed → incoming)
                    $this->oldVersion = $this->readInstalledVersion();
                    $this->newVersion = $this->readIncomingVersion($adapter);

                    // Soft warning on downgrade (no abort)
                    if ($this->oldVersion && $this->newVersion
                        && version_compare($this->newVersion, $this->oldVersion, '<')) {
                        $this->notify(
                            sprintf(
                                'CopyMyPage: downgrade detected (%s → %s). Proceeding anyway (soft warning).',
                                $this->oldVersion,
                                $this->newVersion
                            ),
                            'warning'
                        );
                    }

                    // Environment checks (also soft)
                    return $this->runPreflightChecks($type, $adapter);
                }

                /**
                 * Runs on fresh installation.
                 */
                public function install(InstallerAdapter $adapter): bool
                {
                    // Failsafe: also warn here if a downgrade is observed
                    if ($this->oldVersion && $this->newVersion
                        && version_compare($this->newVersion, $this->oldVersion, '<')) {
                        $this->notify(
                            sprintf(
                                'CopyMyPage: downgrade detected during install (%s → %s). Proceeding (soft warning).',
                                $this->oldVersion,
                                $this->newVersion
                            ),
                            'warning'
                        );
                    }

                    return true;
                }

                /**
                 * Runs on update.
                 */
                public function update(InstallerAdapter $adapter): bool
                {
                    $from = $this->oldVersion ? ' from ' . $this->oldVersion : '';
                    $to   = $this->newVersion ?: 'unknown';

                    $this->notify(sprintf('CopyMyPage updated%s to %s.', $from, $to), 'message');

                    return true;
                }

                /**
                 * Runs after install or update finishes.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
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
                // Orchestration & Checks (soft reporting)
                // ─────────────────────────────────────────────────────────────────────────────

                protected function runPreflightChecks(string $type, InstallerAdapter $adapter): bool
                {
                    $this->checkPhpVersion();
                    $this->checkJoomlaVersion();
                    $this->checkUploadLimits();

                    // Summarize issues without aborting
                    if (!empty($this->errors)) {
                        $this->notify(
                            sprintf('CopyMyPage: preflight reported %d issue(s). Installation will continue.', \count($this->errors)),
                            'warning'
                        );
                    }

                    return true;
                }

                protected function checkPhpVersion(): void
                {
                    if (version_compare(PHP_VERSION, $this->minimum_PHP, '<')) {
                        $this->addIssue(
                            sprintf('PHP %s or higher recommended. Current: %s.', $this->minimum_PHP, PHP_VERSION)
                        );
                    }
                }

                protected function checkJoomlaVersion(): void
                {
                    $version = new Version();
                    $current = $version->getShortVersion();

                    if (version_compare($current, $this->minimum_Joomla, '<')) {
                        $this->addIssue(
                            sprintf('Joomla %s or higher recommended. Current: %s.', $this->minimum_Joomla, $current)
                        );
                    }
                }

                protected function checkUploadLimits(): void
                {
                    $maxUploadBytes = (int) Helper::getFileUploadMaxSize(false);

                    if ($maxUploadBytes < $this->minimum_Byte) {
                        $this->addIssue(
                            sprintf(
                                'Upload limit is low (%d bytes). Recommended at least %d bytes for package uploads.',
                                $maxUploadBytes,
                                $this->minimum_Byte
                            )
                        );
                    }
                }

                // ─────────────────────────────────────────────────────────────────────────────
                // Messaging helpers
                // ─────────────────────────────────────────────────────────────────────────────

                protected function notify(string $message, string $type = 'message'): void
                {
                    Factory::getApplication()->enqueueMessage($message, $type);
                }

                protected function addIssue(string $message): void
                {
                    $this->errors[] = $message;
                    $this->notify($message, 'warning');
                }

                // ─────────────────────────────────────────────────────────────────────────────
                // Manifest / Version readers
                // ─────────────────────────────────────────────────────────────────────────────

                /**
                 * Read incoming manifest <name>.
                 */
                protected function readIncomingElement(InstallerAdapter $adapter): ?string
                {
                    $manifest = $adapter->getManifest();

                    return isset($manifest->name) ? (string) $manifest->name : null;
                }

                /**
                 * Read installed version (no aborts if not found).
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
                 * Read incoming version from the uploaded manifest.
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
