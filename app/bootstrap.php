<?php
declare(strict_types=1);

// Autoloader: App\Core\Foo -> app/core/Foo.php (nama folder huruf kecil, nama kelas apa adanya).
spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $parts = explode('\\', substr($class, 4));
    $file  = array_pop($parts);
    $dir   = $parts ? strtolower(implode('/', $parts)) . '/' : '';
    $path  = BASE_PATH . '/app/' . $dir . $file . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/helpers/functions.php';
require BASE_PATH . '/app/helpers/format.php';
