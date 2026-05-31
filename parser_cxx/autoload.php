<?php

declare(strict_types=1);

spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
