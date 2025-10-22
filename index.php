<?php
session_start();

require_once('config/route.php');
require_once('vendor/autoload.php');
require_once('config/config.php');



$router = new Router();

$router->addRoute('GET', BASE_PATH, function () {
    include 'seylan_hosted.php';
});
$router->addRoute('GET', BASE_PATH.'/auth', function () {
    include 'seylan_hostedAuth.php';
});
$router->addRoute('GET', BASE_PATH.'/status', function () {
    include 'response.php';
});
$router->setNotFound(function () {
    include '404.php';
});
$router->handleRequest();


error_log("Session ID: " . session_id());


?>
