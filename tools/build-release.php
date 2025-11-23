<?php

declare(strict_types=1);

/**
 * Builds CopyMyPage release:
 *  - Zips each first-level extension in src/ (except _vendor_extensions)
 *  - Optionally includes third-party vendor zips from src/_vendor_extensions
 *  - Assembles pkg_copymypage-<version>.zip in dist/
 *
 * Requirements:
 *  - PHP ZipArchive enabled
 *  - Run bump-version.php before this script
 */

namespace CopyMyPage\Tools;

use ZipArchive;

final class BuildRelease
{
    /** @var string */
    private $root;

    /** @var string */
    private $version;

    /** @var bool */
    private $includeVendors;

    public function __construct(string $root, string $version, bool $includeVendors)
    {
        $this->root           = rtrim($root, DIRECTORY_SEPARATOR);
        $this->version        = $version;
        $this->includeVendors = $includeVendors;
    }

    public function run(): void
    {
        $this->assertSemver($this->version);
        $this->assertZipAvailable();

        $distDir        = $this->root . '/dist';
        $subpackagesDir = $this->root . '/pkg_copymypage/subpackages';

        $this->ensureDir($distDir);
        $this->ensureDir($subpackagesDir);

        // 1) Build core extension zips from src/* (excluding _vendor_extensions)
        $src = $this->root . '/src';

        foreach (scandir($src) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '_vendor_extensions') {
                continue;
            }

            $extPath = $src . '/' . $entry;

            if (is_dir($extPath)) {
                $zipName = $entry . '.zip';
                $zipPath = $subpackagesDir . '/' . $zipName;

                echo "[zip]    {$entry} -> pkg_copymypage/subpackages/{$zipName}\n";
                $this->zipDirectory($extPath, $zipPath);
            }
        }

        // 2) Optionally copy vendor zips into subpackages/
        if ($this->includeVendors) {
            $vendorDir = $this->root . '/src/_vendor_extensions';

            if (is_dir($vendorDir)) {
                foreach (glob($vendorDir . '/*.zip') ?: [] as $vendorZip) {
                    $target = $subpackagesDir . '/' . basename($vendorZip);
                    echo "[vendor] + " . basename($vendorZip) . "\n";
                    copy($vendorZip, $target);
                }
            }
        }

        // 3) Create final package zip in dist/
        $packageZip = $distDir . '/pkg_copymypage-' . $this->version . '.zip';

        echo "[pkg]   dist/" . basename($packageZip) . "\n";
        $this->zipPackage($packageZip);

        echo "\nBuild complete.\n";
    }

    private function zipDirectory(string $dir, string $zipFile): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create zip: {$zipFile}");
        }

        $dir = rtrim($dir, DIRECTORY_SEPARATOR);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            $localName = ltrim(str_replace($dir, '', $path), DIRECTORY_SEPARATOR);

            if ($file->isDir()) {
                $zip->addEmptyDir($localName);
                continue;
            }

            $zip->addFile($path, $localName);
        }

        $zip->close();
    }

    private function zipPackage(string $zipFile): void
    {
        $pkgRoot = $this->root . '/pkg_copymypage';

        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create package zip: {$zipFile}");
        }

        // Include: pkg_copymypage.xml, script.php, subpackages/*.zip, changelogs/changelog.xml, README.md
        $include = [
            'pkg_copymypage.xml',
            'script.php',
            'README.md',
        ];

        foreach ($include as $file) {
            $path = $pkgRoot . '/' . $file;

            if (is_file($path)) {
                $zip->addFile($path, $file);
            }
        }

        // changelogs
        $changelog = $pkgRoot . '/changelogs/changelog.xml';

        if (is_file($changelog)) {
            $zip->addFile($changelog, 'changelogs/changelog.xml');
        }

        // subpackages
        $subpackagesDir = $pkgRoot . '/subpackages';

        if (is_dir($subpackagesDir)) {
            $it = new \DirectoryIterator($subpackagesDir);

            foreach ($it as $f) {
                if ($f->isDot() || !$f->isFile()) {
                    continue;
                }

                if (strtolower($f->getExtension()) !== 'zip') {
                    continue;
                }

                $zip->addFile($f->getPathname(), 'subpackages/' . $f->getFilename());
            }
        }

        $zip->close();
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($concurrentDirectory = $dir, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }
    }

    private function assertSemver(string $version): void
    {
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException("Version '{$version}' is not valid semver (MAJOR.MINOR.PATCH).");
        }
    }

    private function assertZipAvailable(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive is not available. Enable it in your PHP build.');
        }
    }
}

(static function (): void {
    $argv = $_SERVER['argv'] ?? [];

    if (count($argv) < 2) {
        fwrite(STDERR, "Usage: php tools/build-release.php <MAJOR.MINOR.PATCH> [--include-vendor]\n");
        exit(1);
    }

    $version        = (string) $argv[1];
    $includeVendors = \in_array('--include-vendor', $argv, true);

    $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

    (new BuildRelease($root, $version, $includeVendors))->run();
})();
