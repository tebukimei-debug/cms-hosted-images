<?php
header('Content-Type: text/plain');
echo "<h1>Debug Info v2</h1>";
echo "DIR: " . __DIR__ . "<br>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current User: " . get_current_user() . " (UID: " . getmyuid() . ", GID: " . getmygid() . ")<br>";
echo "PHP User: " . exec('whoami') . "<br>";

$uploadsDir = __DIR__ . '/uploads';
echo "Uploads dir exists? " . (is_dir($uploadsDir) ? 'Yes' : 'No') . "<br>";
if (is_dir($uploadsDir)) {
    echo "Uploads owner UID: " . fileowner($uploadsDir) . "<br>";
    echo "Uploads permissions: " . substr(sprintf('%o', fileperms($uploadsDir)), -4) . "<br>";
    echo "Is uploads writable? " . (is_writable($uploadsDir) ? 'Yes' : 'No') . "<br>";
}

$thumbDir = __DIR__ . '/uploads/thumbs';
echo "Thumbs dir exists? " . (is_dir($thumbDir) ? 'Yes' : 'No') . "<br>";
if (is_dir($thumbDir)) {
    echo "Thumbs owner UID: " . fileowner($thumbDir) . "<br>";
    echo "Thumbs permissions: " . substr(sprintf('%o', fileperms($thumbDir)), -4) . "<br>";
    echo "Is thumbs writable? " . (is_writable($thumbDir) ? 'Yes' : 'No') . "<br>";
}

// Intentar crear un archivo
$testFile = $uploadsDir . '/test.txt';
$written = @file_put_contents($testFile, "test");
if ($written !== false) {
    echo "Test write to uploads: SUCCESS<br>";
    unlink($testFile);
} else {
    $err = error_get_last();
    echo "Test write to uploads: FAILED - " . ($err['message'] ?? 'Unknown error') . "<br>";
}
