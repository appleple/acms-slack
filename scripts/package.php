<?php

declare(strict_types=1);

/**
 * Build the distributable plugin zip (build/{Name}.zip).
 *
 * Usage:
 *   php scripts/package.php                     # build the zip
 *   php scripts/package.php --check-version=1.2.3   # assert ServiceProvider $version == 1.2.3
 *
 * The --check-version mode is used by the Release workflow to fail fast when a pushed tag
 * (e.g. v1.2.3) disagrees with the version baked into src/ServiceProvider.php.
 */

require __DIR__ . '/Packager.php';

use Acms\PluginTools\Packager;

$root = dirname(__DIR__);

try {
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--check-version=')) {
            $expected = substr($arg, strlen('--check-version='));
            $actual = (new Packager($root))->currentVersion();
            if ($expected !== $actual) {
                fwrite(STDERR, "Version mismatch: tag='{$expected}' but ServiceProvider \$version='{$actual}'.\n");
                exit(1);
            }
            echo "Version OK: {$actual}\n";
            exit(0);
        }
    }

    $zipPath = (new Packager($root))->build();
    printf("Created %s (%s bytes)\n", $zipPath, number_format((float) filesize($zipPath)));
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
