<?php
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/route.php';
require_once __DIR__.'/config/config.php';

$router = new Router();

$logger->info('Base path: ' . BASE_PATH);

$router->addRoute('GET', BASE_PATH, function() use ($logger) {
    require_once('hosted.php');
});

$router->addRoute('GET', BASE_PATH . '/auth', function() use ($logger) {
    require_once('hostedAuth.php');
});

$router->addRoute('GET', BASE_PATH . '/status', function() use ($logger){
    require_once('response.php');
});

$router->setNotFound(function() use ($logger) {
    require_once('404.php');
});

$router->handleRequest();