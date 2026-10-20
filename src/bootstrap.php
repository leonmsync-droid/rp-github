<?php
declare(strict_types=1);

// Shared setup for ingest, scoring and display scripts.

define('APP_ROOT', dirname(__DIR__));
// RP_DATA_DIR lets a local demo dataset be used without touching real data.
define('DATA_DIR', getenv('RP_DATA_DIR') ?: APP_ROOT . '/data');

date_default_timezone_set('UTC');

spl_autoload_register(function (string $class): void {
    $file = APP_ROOT . '/src/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $path = APP_ROOT . '/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Missing config.php — copy config.example.php and fill it in.');
        }
        $config = require $path;
        $config['product'] = require APP_ROOT . '/config/product.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        $value = $value[$part] ?? null;
    }
    return $value;
}

function logline(string $message): void
{
    fwrite(STDERR, '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
}

function today(): string
{
    return gmdate('Y-m-d');
}
