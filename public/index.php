<?php
declare(strict_types=1);

// Front controller: satu-satunya pintu masuk aplikasi.
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

App\Core\Application::run();
