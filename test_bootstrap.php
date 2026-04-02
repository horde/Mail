<?php
// Custom bootstrap for testing to handle local dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Register PSR-4 autoloader for new namespaced Horde code
spl_autoload_register(function ($class) {
    // Horde\Exception namespace
    if (strpos($class, 'Horde\\Exception\\') === 0) {
        $file = __DIR__ . '/../Exception/src/' . str_replace('\\', '/', substr($class, 16)) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Horde\Util namespace
    if (strpos($class, 'Horde\\Util\\') === 0) {
        $file = __DIR__ . '/../Util/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Register PSR-0 autoloader for local Horde dependencies
spl_autoload_register(function ($class) {
    // Map class prefixes to base directories
    $prefixMap = [
        'Horde_Exception' => __DIR__ . '/../Exception/lib/',
        'Horde_Idna' => __DIR__ . '/../Idna/lib/',
        'Horde_Mime' => __DIR__ . '/../Mime/lib/',
        'Horde_Stream' => __DIR__ . '/../Stream_Filter/lib/',
        'Horde_Translation' => __DIR__ . '/../Translation/lib/',
        'Horde_Util' => __DIR__ . '/../Util/lib/',
        'Horde_String' => __DIR__ . '/../Util/lib/',
        'Horde_Array' => __DIR__ . '/../Util/lib/',
        'Horde_Domhtml' => __DIR__ . '/../Util/lib/',
        'Horde_Variables' => __DIR__ . '/../Util/lib/',
        'Horde_Smtp' => __DIR__ . '/../Smtp/lib/',
    ];

    // Try each prefix
    foreach ($prefixMap as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            // PSR-0 style: Horde_String -> Horde/String.php
            $file = $baseDir . str_replace('_', '/', $class) . '.php';

            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});
