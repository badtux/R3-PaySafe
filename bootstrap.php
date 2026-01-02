<?php
require_once __DIR__ . '/vendor/autoload.php';
session_start();

use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\StreamHandler;

$logger = new Logger('paysafe_logger');
$logger->pushHandler(new SyslogHandler(
    ident: 'paysafe_logger',
    facility: LOG_USER,
    level: Logger::DEBUG
));

$logger->pushHandler(new StreamHandler('/tmp/paysafe.log', Logger::DEBUG));
$logger->info('Logger initialized in bootstrap.php');
