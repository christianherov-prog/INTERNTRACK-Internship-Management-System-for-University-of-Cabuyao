<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/app');
$ite = new RecursiveIteratorIterator($dir);
foreach ($ite as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $newContent = str_replace(['section?->name', 'section->name'], ['section', 'section'], $content);
        if ($newContent !== $content) {
            file_put_contents($file->getPathname(), $newContent);
            echo "Updated " . $file->getPathname() . "\n";
        }
    }
}
