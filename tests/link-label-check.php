<?php

/**
 * The withdrawal link label is decided in exactly one place.
 *
 * Art. 11a(1) lets a shop reword "withdraw from contract here" as long as the
 * alternative is unambiguous, and the `link_text` setting is how it does that.
 * The setting reached the shortcode and the footer link but not the My Account
 * order-view button, which printed the statutory English sentence directly, so
 * a reworded shop showed two different labels for one right.
 *
 * A second copy of the default string anywhere in src/ is that defect coming
 * back, so this counts them.
 *
 * Run: php tests/link-label-check.php
 */

declare(strict_types=1);

// Scoped to the storefront: the admin screen prints the same sentence as the
// placeholder on the link_text field, which is the setting working, not a
// second render path.
$src = dirname(__DIR__) . '/src/Frontend';
$default = 'Withdraw from contract here';

$hits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    foreach (file($file->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
        if (str_contains($line, $default) && ! str_starts_with(ltrim($line), '*')) {
                $hits[] = sprintf('src/Frontend/%s:%d', substr($file->getPathname(), strlen($src) + 1), $i + 1);
        }
    }
}

$failures = [];

if (count($hits) !== 1) {
    $failures[] = sprintf(
        "the default label appears %d times, expected 1 (WithdrawLink::label):\n    %s",
        count($hits),
        implode("\n    ", $hits),
    );
} else {
    echo "  ok      the default label is written once, in {$hits[0]}\n";
}

// Any storefront class that builds a link to the withdrawal form is a control
// the label rule has to cover, so find them rather than list them.
foreach (new DirectoryIterator($src) as $file) {
    if ($file->isDot() || $file->getExtension() !== 'php') {
        continue;
    }
    $code = (string) file_get_contents($file->getPathname());
    if (! str_contains($code, 'form_page_id') && ! str_contains($code, 'wd_order')) {
        continue;
    }
    $rel = 'src/Frontend/' . $file->getFilename();
    $ok = str_contains($code, 'self::label(') || str_contains($code, 'WithdrawLink::label(');
    echo ($ok ? '  ok      ' : '  FAILED  ') . "{$rel} resolves its label through WithdrawLink::label()\n";
    if (! $ok) {
        $failures[] = "{$rel} links to the form but does not call WithdrawLink::label()";
    }
}

if ($failures !== []) {
    echo "\nFAILED\n";
    foreach ($failures as $f) {
        echo '  ' . $f . "\n";
    }
    exit(1);
}

echo "\nRESULT: pass\n";
exit(0);
