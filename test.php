<?php
echo "<h1>Debug Info</h1>";
echo "DIR: " . __DIR__ . "<br>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

$uploadsDir = __DIR__ . '/uploads';
echo "Uploads dir exists? " . (is_dir($uploadsDir) ? 'Yes' : 'No') . "<br>";

if (is_dir($uploadsDir)) {
    echo "<h2>Files in uploads:</h2><ul>";
    $files = scandir($uploadsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
}

$thumbsDir = __DIR__ . '/uploads/thumbs';
echo "Thumbs dir exists? " . (is_dir($thumbsDir) ? 'Yes' : 'No') . "<br>";

if (is_dir($thumbsDir)) {
    echo "<h2>Files in thumbs:</h2><ul>";
    $files = scandir($thumbsDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "<li>$file</li>";
        }
    }
    echo "</ul>";
}
