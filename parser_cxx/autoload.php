<?php

declare(strict_types=1);

spl_autoload_register(function (string $class) {
    $file = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Fallback: scan the directory for the class definition
    $nsParts = explode('\\', $class);
    $shortName = array_pop($nsParts);
    $dir = __DIR__ . '/' . implode('/', $nsParts);
    if (is_dir($dir)) {
        foreach (scandir($dir) as $entry) {
            if (str_ends_with($entry, '.php')) {
                $content = file_get_contents("$dir/$entry");
                if (preg_match("/\b(class|enum)\s+$shortName\b/", $content)) {
                    require "$dir/$entry";
                    return;
                }
            }
        }
    }
});
