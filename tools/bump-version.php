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
            $this->touchUpdateXml($updateXml, $this->version);
            echo "[update] {$updateXml} (no version bump, updated timestamp if applicable)\n";
        }

        echo "\nDone. New version: {$this->version}\n";
    }

    /**
     * @return array<int,string>
     */
    private function collectManifestFiles(): array
    {
        $files = [];

        // src/ sub-extensions
        $src = $this->root . '/src';
        if (is_dir($src)) {
            $pattern = implode(
                PATH_SEPARATOR,
                [
                    $src . '/*/*.xml',           // com_*/mod_*/tpl_* manifests
                    $src . '/*/*/*.xml',         // nested (e.g., language xmls, we’ll filter)
                ]
            );

            // We’ll filter to typical Joomla manifests only by filename pattern heuristics.
            foreach (glob($src . '/*/*.xml') ?: [] as $file) {
                if ($this->isLikelyJoomlaManifest($file)) {
                    $files[] = $file;
                }
            }
        }

        // Package manifest
        $pkg = $this->root . '/pkg_copymypage/pkg_copymypage.xml';
        if (is_file($pkg)) {
            $files[] = $pkg;
        }

        // Optional: templateDetails.xml already covered above via pattern
        return array_values(array_unique($files));
    }

    private function isLikelyJoomlaManifest(string $file): bool
    {
        $basename = basename($file);
        if (\in_array($basename, ['templateDetails.xml'], true)) {
            return true;
        }

        // Generic heuristic: com_*.xml, mod_*.xml, plg_*.xml, pkg_*.xml etc.
        if (preg_match('/^(com|mod|plg|pkg|tpl|lib)_[a-z0-9_]+\.xml$/i', $basename) === 1) {
            return true;
        }

        // Fallback: keep conservative
        return false;
    }

    private function updateManifestVersion(string $file, string $version): void
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (@$dom->load($file) === false) {
            throw new \RuntimeException("Unable to parse XML: {$file}");
        }

        $versionNodes = $dom->getElementsByTagName('version');
        if ($versionNodes->length > 0) {
            $versionNodes->item(0)->nodeValue = $version;
        } else {
            // If no <version> exists, create one at root level (conservative fallback).
            $root = $dom->documentElement;
            if ($root === null) {
                throw new \RuntimeException("Invalid XML (no root): {$file}");
            }
            $v = $dom->createElement('version', $version);
            $root->appendChild($v);
        }

        $this->saveDom($dom, $file);
    }

    private function touchUpdateXml(string $file, string $version): void
    {
        // We typically do not rewrite history in update.xml here.
        // Optionally, you could inject a new <update> entry programmatically.
        // For safety, we just normalize formatting and ensure UTF-8.
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (@$dom->load($file) === false) {
            // Not fatal; just return.
            return;
        }

        $this->saveDom($dom, $file);
    }

    private function saveDom(\DOMDocument $dom, string $file): void
    {
        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException("Failed to serialize XML: {$file}");
        }
        file_put_contents($file, $xml);
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
