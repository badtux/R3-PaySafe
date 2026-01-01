<?php
require_once('route.php');
require_once('vendor/autoload.php');
require_once('config/config.php');

session_start();

use Monolog\Logger;
use Monolog\Handler\SyslogHandler;

$logger = new Logger('paysafe_logger');
$logger->pushHandler(new SyslogHandler(
    ident: 'paysafe_logger',          // Appears as the program name in syslog
    facility: LOG_USER,       // Syslog facility
    level: Logger::DEBUG      // Minimum log level
));

$router = new Router();

$router->addRoute('GET', BASE_PATH . '/', function () {
    require_once('seylan_hosted.php');
});

$router->addRoute('GET', BASE_PATH . '/auth', function () {
    require_once('seylan_hostedAuth.php');
});

$router->addRoute('GET', BASE_PATH . '/status', function () {
    require_once('response.php');
});

$router->setNotFound(function () {
    require_once('404.php');
});

$router->handleRequest();