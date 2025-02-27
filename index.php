<?php
session_start();
require_once('config/route.php');
require_once('config/config.php');
require_once('vendor/autoload.php');

$router = new Router();

$router->addRoute('GET', BASE_PATH, function () {
    include 'cmb_hosted.php';
});

$router->addRoute('POST', BASE_PATH.'/auth', function () {
    include 'cmb_hostedAuth.php';
});

$router->setNotFound(function () {
    include '404.php';
});

$router->handleRequest();
?>