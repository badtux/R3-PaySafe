<?php
session_start();

require_once('config/route.php');
require_once('vendor/autoload.php');
require_once('config/config.php');

$router = new Router();

$router->addRoute('GET', BASE_PATH, function () {
    error_log('seylan hosted php called');
    include 'seylan_hosted.php';
});

$router->addRoute('GET', BASE_PATH . '/auth', function () {
    error_log('seylan hosted Auth php called');
    include 'seylan_hostedAuth.php';
});
$router->addRoute('GET', BASE_PATH . '/status', function () {
    error_log('seylan response php called');
    include 'response.php';
});
$router->setNotFound(function () {
    error_log('seylan 404 php called');
    include '404.php';
});
$router->handleRequest();


error_log("Session ID: " . session_id());
