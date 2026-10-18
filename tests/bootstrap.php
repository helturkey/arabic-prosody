<?php

declare(strict_types=1);

error_reporting(E_ALL);
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;

    return;
}
spl_autoload_register(static function (string $class): void {
    $root = dirname(__DIR__);
    if (str_starts_with($class, 'Arud\\Tests\\')) {
        $file = $root . '/tests/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    } elseif (str_starts_with($class, 'Arud\\')) {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    } else {
        return;
    }
    if (is_file($file)) {
        require $file;
    }
});
