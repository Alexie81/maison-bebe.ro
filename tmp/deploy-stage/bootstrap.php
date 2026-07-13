<?php
declare(strict_types=1);

use MaisonBebe\Core\Env;
use MaisonBebe\Core\ProductionWorker;
use MaisonBebe\Core\Session;

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'MaisonBebe\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $file = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');
Env::load(BASE_PATH . '/.env.local');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Europe/Bucharest'));

Session::start();

require BASE_PATH . '/app/Helpers/functions.php';

ProductionWorker::register();

return require BASE_PATH . '/routes/app10.php';


