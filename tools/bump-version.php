<?php

declare(strict_types=1);

/**
 * Bumps <version> tags across Joomla manifests for CopyMyPage.
 *
 * Usage:
 *   php tools/bump-version.php 1.2.3
 *
 * Notes:
 * - Comments in English as requested.
 * - Preserves manifest formatting by replacing only the first <version> value.
 * - PSR-12 compliant formatting.
 */

namespace CopyMyPage\Tools;

final class BumpVersion
{
    /** @var string */
    private $root;

    /** @var string */
    private $version;

    /**
     * @param string $root    Project root path
     * @param string $version Target semver (MAJOR.MINOR.PATCH)
     */
    public function __construct(string $root, string $version)
    {
        $this->root    = rtrim($root, DIRECTORY_SEPARATOR);
        $this->version = $version;
    }

    public function run(): void
    {
        $this->assertSemver($this->version);

        $manifestFiles = $this->collectManifestFiles();

        foreach ($manifestFiles as $file) {
            $this->updateManifestVersion($file, $this->version);
            echo "[bump]   {$file} -> {$this->version}\n";
        }

        $updateXml = $this->root . '/update.xml';
        if (is_file($updateXml)) {
            $this->validateUpdateXml($updateXml);
            echo "[update] {$updateXml} (validated only; formatting untouched)\n";
        }

        echo "\nDone. New version: {$this->version}\n";
    }

    /**
     * @return array<int,string>
     */
    private function collectManifestFiles(): array
    {
        $files = [];

        $src = $this->root . '/src';
        if (is_dir($src)) {
            foreach (glob($src . '/*/*.xml') ?: [] as $file) {
                if ($this->isLikelyJoomlaManifest($file)) {
                    $files[] = $file;
                }
            }
        }

        $pkg = $this->root . '/pkg_copymypage/pkg_copymypage.xml';
        if (is_file($pkg)) {
            $files[] = $pkg;
        }

        return array_values(array_unique($files));
    }

    private function isLikelyJoomlaManifest(string $file): bool
    {
        $basename = basename($file);

        if (preg_match('/^com_.*\.xml$/', $basename) === 1) {
            return true;
        }

        if ($basename === 'copymypage.xml') {
            return true;
        }

        if (preg_match('/^mod_.*\.xml$/', $basename) === 1) {
            return true;
        }

        if ($basename === 'templateDetails.xml') {
            return true;
        }

        return false;
    }

    private function updateManifestVersion(string $file, string $version): void
    {
        $xml = file_get_contents($file);

        if ($xml === false) {
            throw new \RuntimeException("Unable to read XML: {$file}");
        }

        $this->assertXmlIsParseable($xml, $file);

        $updated = preg_replace_callback(
            '/(<version\b[^>]*>)([^<]*)(<\/version>)/i',
            static function (array $matches) use ($version): string {
                return $matches[1] . $version . $matches[3];
            },
            $xml,
            1,
            $count
        );

        if ($updated === null) {
            throw new \RuntimeException("Failed to update version in XML: {$file}");
        }

        if ($count === 0) {
            throw new \RuntimeException("No <version> tag found in manifest: {$file}");
        }

        file_put_contents($file, $updated);
    }

    private function validateUpdateXml(string $file): void
    {
        $xml = file_get_contents($file);

        if ($xml === false) {
            return;
        }

        $this->assertXmlIsParseable($xml, $file);
    }

    private function assertXmlIsParseable(string $xml, string $file): void
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;

        if (@$dom->loadXML($xml) === false) {
            throw new \RuntimeException("Unable to parse XML: {$file}");
        }
    }

    private function assertSemver(string $version): void
    {
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException("Version '{$version}' is not valid semver (MAJOR.MINOR.PATCH).");
        }
    }
}

(static function (): void {
    $argv = $_SERVER['argv'] ?? [];
    if (count($argv) < 2) {
        fwrite(STDERR, "Usage: php tools/bump-version.php <MAJOR.MINOR.PATCH>\n");
        exit(1);
    }

    $version = (string) $argv[1];
    $root    = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

    (new BumpVersion($root, $version))->run();
})();
