<?php

declare(strict_types=1);

namespace Acms\PluginTools;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Release helper shared by scripts/package.php, scripts/version.php and scripts/release.php.
 * Packaging / versioning chores run in plain PHP (ZipArchive) via Composer scripts. See README.md
 * ("Releasing").
 *
 * The single source of truth for the plugin version is `$version` in src/ServiceProvider.php.
 * Do NOT add a "version" field to composer.json: Composer derives versions from git tags, and a
 * hard-coded value competes with the tag (the most common cause of "release not published").
 */
final class Packager
{
    /**
     * Files that must never ship inside the deployed plugin folder. `src/composer.json` /
     * `src/composer.lock` only manage the (optional) bundled runtime dependencies at build time;
     * they are useless — and confusing — inside the installed plugin.
     */
    private const IGNORES = ['composer.json', 'composer.lock'];

    /**
     * Names never copied into the package, at any depth: dev-only dirs / VCS / OS cruft. Without
     * this a plugin that keeps a build project under src/ (e.g. a Webpack theme) would ship its
     * whole node_modules — tens of MB of dependencies that have no place in the deployed plugin.
     */
    private const EXCLUDE_NAMES = ['node_modules', '.git', '.DS_Store'];

    /**
     * Default root-level files/dirs bundled alongside src/ when present. Override per plugin via
     * composer.json `extra.acms-plugin-tools.extras` (e.g. ["docs"]). `images/` is the plugin thumbnail
     * a-blog cms shows in the admin; LICENSE is removed by post-create-project, so it is only
     * bundled when the plugin author added one back.
     */
    private const DEFAULT_EXTRAS = ['README.md', 'LICENSE', 'images'];

    public function __construct(private string $root)
    {
    }

    /**
     * Derive the plugin folder name (e.g. "Skeleton") from the composer.json in the project root.
     */
    public function pluginName(): string
    {
        return self::pluginNameFromComposer($this->composerContents());
    }

    /**
     * Root-level paths bundled alongside src/. Override via composer.json
     * `extra.acms-plugin-tools.extras`; defaults to self::DEFAULT_EXTRAS.
     *
     * @return list<string>
     */
    public function extras(): array
    {
        $configured = $this->extraConfig()['extras'] ?? null;
        if (is_array($configured)) {
            return array_values(array_filter(array_map('strval', $configured), static fn($s) => $s !== ''));
        }

        return self::DEFAULT_EXTRAS;
    }

    /**
     * Base name (without extension) of the built zip, rendered from the `zipFileName` template in
     * composer.json `extra.acms-plugin-tools` (default "{pluginName}"). Placeholders:
     *   {pluginName} – plugin/folder name (from autoload.psr-4)
     *   {version}    – current $version from src/ServiceProvider.php
     * e.g. "{pluginName}v{version}" → "Skeletonv1.2.3". Keeping the version in the name helps when
     * the artifact is downloaded and distributed by hand; add more placeholders here as needed.
     */
    public function zipBaseName(): string
    {
        $template = (string) ($this->extraConfig()['zipFileName'] ?? '{pluginName}');
        if ($template === '') {
            $template = '{pluginName}';
        }

        return strtr($template, [
            '{pluginName}' => $this->pluginName(),
            '{version}' => $this->currentVersion(),
        ]);
    }

    /**
     * Read the version currently declared in src/ServiceProvider.php.
     */
    public function currentVersion(): string
    {
        return self::versionFromServiceProvider(
            (string) file_get_contents($this->serviceProviderPath())
        );
    }

    /**
     * Overwrite `$version` in src/ServiceProvider.php and return the value that was written.
     */
    public function setVersion(string $newVersion): string
    {
        $path = $this->serviceProviderPath();
        $code = (string) file_get_contents($path);
        file_put_contents($path, self::replaceVersion($code, $newVersion));

        return $newVersion;
    }

    /**
     * Build build/{Name}.zip (top-level entry is {Name}/…) and return the zip path.
     */
    public function build(): string
    {
        $name = $this->pluginName();
        $srcDir = $this->root . '/src';
        if (!is_dir($srcDir)) {
            throw new RuntimeException("src/ not found at {$srcDir}");
        }

        // If the plugin bundles its own runtime dependencies, vendor them into src/vendor/ first.
        if (is_file($srcDir . '/composer.json')) {
            $this->installRuntimeDeps($srcDir);
        }

        $buildDir = $this->root . '/build';
        $this->ensureDir($buildDir);

        $stageDir = $buildDir . '/' . $name;
        $this->removeTree($stageDir);
        $this->copyTree($srcDir, $stageDir);

        foreach ($this->extras() as $extra) {
            $from = $this->root . '/' . $extra;
            if (file_exists($from)) {
                $this->copyTree($from, $stageDir . '/' . $extra);
            }
        }

        foreach (self::IGNORES as $ignore) {
            $this->removeTree($stageDir . '/' . $ignore);
        }

        $zipPath = $buildDir . '/' . $this->zipBaseName() . '.zip';
        $this->zipTree($stageDir, $name, $zipPath);
        $this->removeTree($stageDir);

        return $zipPath;
    }

    // ---------------------------------------------------------------------
    // Pure helpers (no filesystem side effects) — easy to reason about/reuse.
    // ---------------------------------------------------------------------

    public static function pluginNameFromComposer(string $contents): string
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new RuntimeException('composer.json is not valid JSON.');
        }

        $psr4 = $data['autoload']['psr-4'] ?? null;
        if (!is_array($psr4) || $psr4 === []) {
            throw new RuntimeException('composer.json has no autoload.psr-4 entry.');
        }

        // Prefer the namespace that maps to src/ (the deployed plugin root).
        $key = null;
        foreach ($psr4 as $namespace => $path) {
            foreach ((array) $path as $p) {
                if (rtrim((string) $p, '/') === 'src') {
                    $key = (string) $namespace;
                    break 2;
                }
            }
        }
        $key ??= (string) array_key_first($psr4);

        $segments = array_values(array_filter(
            explode('\\', trim($key, '\\')),
            static fn(string $s): bool => $s !== ''
        ));
        if ($segments === []) {
            throw new RuntimeException("Cannot derive a plugin name from the psr-4 key '{$key}'.");
        }

        return (string) end($segments);
    }

    public static function versionFromServiceProvider(string $code): string
    {
        if (preg_match('/\$version\s*=\s*\'([\d.]+)\'/', $code, $m) !== 1) {
            throw new RuntimeException('Could not find $version in ServiceProvider.php.');
        }

        return $m[1];
    }

    public static function isValidVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }

    public static function bumpVersion(string $current, string $part): string
    {
        if (!self::isValidVersion($current)) {
            throw new RuntimeException("Current version '{$current}' is not X.Y.Z.");
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $current));
        switch ($part) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
                $patch++;
                break;
            default:
                throw new RuntimeException("Unknown version part '{$part}' (expected major|minor|patch).");
        }

        return "{$major}.{$minor}.{$patch}";
    }

    public static function replaceVersion(string $code, string $newVersion): string
    {
        if (!self::isValidVersion($newVersion)) {
            throw new RuntimeException("Version '{$newVersion}' is not X.Y.Z.");
        }

        $count = 0;
        $result = preg_replace(
            '/(\$version\s*=\s*\')[\d.]+(\')/',
            '${1}' . $newVersion . '${2}',
            $code,
            1,
            $count
        );
        if ($result === null || $count === 0) {
            throw new RuntimeException('Could not update $version in ServiceProvider.php.');
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // Filesystem / process helpers.
    // ---------------------------------------------------------------------

    private function composerContents(): string
    {
        $composerJson = $this->root . '/composer.json';
        if (!is_file($composerJson)) {
            throw new RuntimeException("composer.json not found at {$composerJson}");
        }

        return (string) file_get_contents($composerJson);
    }

    /**
     * The `extra.acms-plugin-tools` object from composer.json (extras / zipFileName), or []
     * if absent.
     *
     * @return array<string, mixed>
     */
    private function extraConfig(): array
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($this->composerContents(), true);
        $config = $data['extra']['acms-plugin-tools'] ?? [];

        return is_array($config) ? $config : [];
    }

    private function serviceProviderPath(): string
    {
        $path = $this->root . '/src/ServiceProvider.php';
        if (!is_file($path)) {
            throw new RuntimeException("ServiceProvider.php not found at {$path}");
        }

        return $path;
    }

    private function installRuntimeDeps(string $srcDir): void
    {
        $this->run(sprintf(
            'composer install --working-dir=%s --no-dev --optimize-autoloader --no-interaction --no-progress',
            escapeshellarg($srcDir)
        ));
    }

    private function zipTree(string $stageDir, string $name, string $zipPath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip extension (ext-zip) is required to build the package.');
        }
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not open {$zipPath} for writing.");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stageDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $relative = str_replace('\\', '/', ltrim(substr($item->getPathname(), strlen($stageDir)), '/\\'));
            $entry = $name . '/' . $relative;
            if ($item->isDir()) {
                $zip->addEmptyDir($entry);
            } else {
                $zip->addFile($item->getPathname(), $entry);
            }
        }

        $zip->close();
    }

    private function copyTree(string $from, string $to): void
    {
        if (in_array(basename($from), self::EXCLUDE_NAMES, true)) {
            return;
        }
        if (is_dir($from)) {
            $this->ensureDir($to);
            foreach (scandir($from) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $this->copyTree($from . '/' . $item, $to . '/' . $item);
            }

            return;
        }

        $this->ensureDir(dirname($to));
        if (!copy($from, $to)) {
            throw new RuntimeException("Failed to copy {$from} to {$to}");
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $item);
        }
        @rmdir($path);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0o777, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory {$dir}");
        }
    }

    private function run(string $command): void
    {
        echo $command, "\n";
        $status = 1;
        passthru($command, $status);
        if ($status !== 0) {
            throw new RuntimeException("Command failed (exit {$status}): {$command}");
        }
    }
}
