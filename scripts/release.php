<?php

declare(strict_types=1);

/**
 * Cut a release: bump the version, commit, and create a git tag.
 *
 * Usage:
 *   php scripts/release.php patch            # bump + commit + tag  (does NOT push)
 *   php scripts/release.php minor --push     # also push the commit and tag
 *
 * By default this stops after creating the local tag and prints the push command, because
 * pushing a v* tag triggers the public Release workflow — an outward-facing, hard-to-undo step
 * that should be pulled deliberately by a human. Pass --push to opt into pushing automatically.
 */

require __DIR__ . '/Packager.php';

use Acms\PluginTools\Packager;

$root = dirname(__DIR__);

$part = null;
$push = false;
foreach (array_slice($argv, 1) as $arg) {
    if (in_array($arg, ['patch', 'minor', 'major'], true)) {
        $part = $arg;
    } elseif ($arg === '--push') {
        $push = true;
    }
}

if ($part === null) {
    fwrite(STDERR, "Usage: php scripts/release.php <patch|minor|major> [--push]\n");
    exit(1);
}

/**
 * Run a shell command from the project root, echoing it first; abort on a non-zero exit.
 */
$run = static function (string $command) use ($root): void {
    $full = 'git -C ' . escapeshellarg($root) . ' ' . $command;
    echo $full, "\n";
    $status = 1;
    passthru($full, $status);
    if ($status !== 0) {
        fwrite(STDERR, "Command failed (exit {$status}): {$full}\n");
        exit($status);
    }
};

try {
    $packager = new Packager($root);
    $current = $packager->currentVersion();
    $new = Packager::bumpVersion($current, $part);
    $tag = "v{$new}";

    $packager->setVersion($new);
    echo "Version: {$current} -> {$new}\n";

    $run('add -A');
    $run('commit -m ' . escapeshellarg($tag));
    // Annotated tag: `git push --follow-tags` only pushes annotated tags, so a lightweight
    // `git tag <tag>` would never reach the remote and the Release workflow would not fire.
    $run('tag -a ' . escapeshellarg($tag) . ' -m ' . escapeshellarg($tag));

    if ($push) {
        $run('push --follow-tags');
        echo "Pushed {$tag}. CI (release.yml / bitbucket-pipelines.yml) builds and publishes the zip for this tag.\n";
    } else {
        echo "\nCreated local tag {$tag}. To publish, push it:\n";
        echo "  git push --follow-tags\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
