<?php

declare(strict_types=1);

use Admin\Bootstrap;
use Admin\Http\Request;
use Admin\Security\PhpSession;

require dirname(__DIR__) . '/vendor/autoload.php';

// no servidor embutido do PHP (php -S), arquivo que existe em public/ é servido direto
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';
$app = Bootstrap::app();
$app->handle(Request::fromGlobals(new PhpSession($https)))->send();
