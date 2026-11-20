<?php
// app/core/Autoloader.php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    // 1. Direct path check (exact case)
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // 2. Linux case-sensitive directory check (e.g. App\Core\Env -> app/core/Env.php)
    $parts = explode('\\', $relativeClass);
    if (count($parts) > 1) {
        $className = array_pop($parts);
        $subDirs = array_map('strtolower', $parts);
        $linuxFile = $baseDir . implode('/', $subDirs) . '/' . $className . '.php';
        if (file_exists($linuxFile)) {
            require $linuxFile;
            return;
        }
    }
});
