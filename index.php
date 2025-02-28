<?php
session_start();
require_once('config/route.php');
require_once('config/config.php');
require_once('vendor/autoload.php');

$router = new Router();

$router->addRoute('GET', BASE_PATH, function () {
    include 'ntb_hosted.php';
});

$router->addRoute('GET', BASE_PATH.'/auth', function () {
    include 'ntb_sessionAuth.php';
});

$router->addRoute('GET', BASE_PATH.'/status', function () {
    include 'response.php';
});


$router->setNotFound(function () {
    include '404.php';
});

$router->handleRequest();
?>