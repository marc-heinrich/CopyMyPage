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
                protected ?string $oldVersion = null;
                protected ?string $newVersion = null;

                /** matches #__extensions.element and manifest filename */
                protected string $element = 'pkg_copymypage';

                /** requirements */
                protected string $minimum_Joomla = '6.0';
                protected string $minimum_PHP    = JOOMLA_MINIMUM_PHP;

                /** e.g. 30 MB */
                protected int $minimum_Byte = 31457280;

                /** collected soft issues */
                protected array $issues = [];

                /** flags */
                protected bool $wasDowngrade = false;
                protected bool $wasUpgrade   = false;

                // ─────────────────────────────────────────────────────────────
                // Lifecycle
                // ─────────────────────────────────────────────────────────────

                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    $incomingElement = $this->readIncomingElement($adapter);
                    if ($incomingElement && \preg_match('/^pkg_/i', $incomingElement)) {
                        $this->element = $incomingElement;
                    }

                    $this->oldVersion = $this->readInstalledVersion();
                    $this->newVersion = $this->readIncomingVersion($adapter);

                    if ($this->oldVersion && $this->newVersion) {
                        if (version_compare($this->newVersion, $this->oldVersion, '<')) {
                            $this->wasDowngrade = true;
                            $this->softLog(
                                sprintf('CopyMyPage: downgrade detected (%s → %s).', $this->oldVersion, $this->newVersion)
                            );
                        } elseif (version_compare($this->newVersion, $this->oldVersion, '>')) {
                            $this->wasUpgrade = true;
                            $this->softLog(
                                sprintf('CopyMyPage: upgrade detected (%s → %s).', $this->oldVersion, $this->newVersion)
                            );
                        }
                    }

                    return $this->runPreflightChecks();
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    // Nur fürs Log – sichtbare Box kommt in postflight()
                    if ($this->oldVersion && $this->newVersion) {
                        $this->softLog(sprintf('CopyMyPage updated from %s to %s.', $this->oldVersion, $this->newVersion));
                    }

                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    $app = Factory::getApplication();

                    // ── Success box for upgrades ─────────────────────────────
                    if ($this->wasUpgrade && $this->oldVersion && $this->newVersion) {
                        $message = sprintf(
                            '<div class="alert alert-success" style="margin-top:1rem;">
                                <strong>CopyMyPage – Your website. Just copy it.</strong><br>
                                Successfully updated from <code>%s</code> to <code>%s</code>.
                             </div>',
                            htmlspecialchars($this->oldVersion),
                            htmlspecialchars($this->newVersion)
                        );

                        $layout = new FileLayout('message');
                        echo $layout->render(['msg' => $message, 'type' => 'success']);

                        $app->enqueueMessage(strip_tags(sprintf(
                            'CopyMyPage successfully updated from %s to %s.',
                            $this->oldVersion,
                            $this->newVersion
                        )), 'message');

                        $this->softLog(sprintf(
                            'CopyMyPage: successful update (%s → %s).',
                            $this->oldVersion,
                            $this->newVersion
                        ));
                    }

                    // ── Warning box for downgrades ──────────────────────────
                    if ($this->wasDowngrade) {
                        $message = sprintf(
                            '<div class="alert alert-warning" style="margin-top:1rem;">
                                <strong>CopyMyPage – Your website. Just copy it.</strong><br>
                                A downgrade was detected (<code>%s → %s</code>).<br>
                                The installation continued, but please verify your system integrity.
                             </div>',
                            htmlspecialchars($this->oldVersion ?? 'unknown'),
                            htmlspecialchars($this->newVersion ?? 'unknown')
                        );

                        $layout = new FileLayout('message');
                        echo $layout->render(['msg' => $message, 'type' => 'warning']);

                        $app->enqueueMessage(strip_tags(sprintf(
                            'CopyMyPage: downgrade detected (%s → %s). Proceeding (soft warning).',
                            $this->oldVersion ?? 'unknown',
                            $this->newVersion ?? 'unknown'
                        )), 'warning');

                        $this->softLog(sprintf(
                            'CopyMyPage: downgrade warning shown (%s → %s).',
                            $this->oldVersion ?? 'unknown',
                            $this->newVersion ?? 'unknown'
                        ));
                    }

                    // ── Other soft issues (PHP/Joomla/upload limits) ────────
                    if (!empty($this->issues)) {
                        $summary = sprintf(
                            'CopyMyPage preflight reported %d issue(s). Installation continued.',
                            \count($this->issues)
                        );
                        $app->enqueueMessage($summary, 'warning');

                        foreach ($this->issues as $issue) {
                            $app->enqueueMessage($issue, 'info');
                        }
                    }

                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                // ─────────────────────────────────────────────────────────────
                // Soft Environment Checks
                // ─────────────────────────────────────────────────────────────

                protected function runPreflightChecks(): bool
                {
                    $this->checkPhpVersion();
                    $this->checkJoomlaVersion();
                    $this->checkUploadLimits();
                    return true;
                }

                protected function checkPhpVersion(): void
                {
                    if (version_compare(PHP_VERSION, $this->minimum_PHP, '<')) {
                        $this->issues[] = sprintf(
                            'PHP %s or higher recommended. Current: %s.',
                            $this->minimum_PHP,
                            PHP_VERSION
                        );
                    }
                }

                protected function checkJoomlaVersion(): void
                {
                    $version = new Version();
                    $current = $version->getShortVersion();

                    if (version_compare($current, $this->minimum_Joomla, '<')) {
                        $this->issues[] = sprintf(
                            'Joomla %s or higher recommended. Current: %s.',
                            $this->minimum_Joomla,
                            $current
                        );
                    }
                }

                protected function checkUploadLimits(): void
                {
                    $maxUploadBytes = (int) Helper::getFileUploadMaxSize(false);

                    if ($maxUploadBytes < $this->minimum_Byte) {
                        $this->issues[] = sprintf(
                            'Upload limit is low (%d bytes). Recommended at least %d bytes for package uploads.',
                            $maxUploadBytes,
                            $this->minimum_Byte
                        );
                    }
                }

                // ─────────────────────────────────────────────────────────────
                // Logging
                // ─────────────────────────────────────────────────────────────

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
                        // ignore
                    }
                }

                // ─────────────────────────────────────────────────────────────
                // Manifest / Version readers
                // ─────────────────────────────────────────────────────────────

                protected function readIncomingElement(InstallerAdapter $adapter): ?string
                {
                    $manifest = $adapter->getManifest();
                    return isset($manifest->name) ? (string) $manifest->name : null;
                }

                protected function readInstalledVersion(): ?string
                {
                    $manifestPath = \JPATH_MANIFESTS . '/packages/' . $this->element . '.xml';

                    if (\is_file($manifestPath) && \is_readable($manifestPath)) {
                        try {
                            $pkg = new PackageManifest($manifestPath);
                            if (!empty($pkg->version)) {
                                return (string) $pkg->version;
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }

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

                protected function readIncomingVersion(InstallerAdapter $adapter): ?string
                {
                    $manifest = $adapter->getManifest();
                    return isset($manifest->version) ? (string) $manifest->version : null;
                }
            }
        );
    }
};
