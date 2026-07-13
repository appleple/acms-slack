<?php

declare(strict_types=1);

/**
 * Set the plugin version in src/ServiceProvider.php (the single source of truth).
 *
 * Usage:
 *   php scripts/version.php 1.2.3     # set an explicit version
 *   php scripts/version.php patch     # bump the current version (patch|minor|major)
 */

require __DIR__ . '/Packager.php';

use Acms\PluginTools\Packager;

$root = dirname(__DIR__);
$arg = $argv[1] ?? '';

if ($arg === '') {
    fwrite(STDERR, "Usage: php scripts/version.php <X.Y.Z|patch|minor|major>\n");
    exit(1);
}

try {
    $packager = new Packager($root);
    $current = $packager->currentVersion();

    if (in_array($arg, ['patch', 'minor', 'major'], true)) {
        $new = Packager::bumpVersion($current, $arg);
    } elseif (Packager::isValidVersion($arg)) {
        $new = $arg;
    } else {
        fwrite(STDERR, "Invalid version '{$arg}' (expected X.Y.Z or patch|minor|major).\n");
        exit(1);
    }

    $packager->setVersion($new);
    echo "Version: {$current} -> {$new}\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
