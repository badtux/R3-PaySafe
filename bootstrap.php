<?php
require_once __DIR__.'/vendor/autoload.php';
session_start();
use Monolog\Logger;
use Monolog\Handler\SyslogHandler;

$logger = new Logger('paysafe_logger');
$logger->pushHandler(new SyslogHandler(
    ident: 'paysafe_logger',
    facility: LOG_USER,
    level: Logger::DEBUG
));