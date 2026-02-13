<?php
// Simple test runner used by CI to sanity-check basic behaviours
// Author: repo automation / commit: tests: add simple runner

echo "Running simple PHP checks...\n";

$files = glob(__DIR__ . '/../php/*.php');
$ok = true;
foreach ($files as $f) {
    $out = null;
    $rc = null;
    exec("php -l " . escapeshellarg($f), $out, $rc);
    if ($rc !== 0) {
        echo "Syntax error in $f:\n" . implode("\n", $out) . "\n";
        $ok = false;
    }
}

if ($ok) {
    echo "All PHP files lint OK.\n";
    exit(0);
} else {
    echo "PHP lint failed.\n";
    exit(2);
}
