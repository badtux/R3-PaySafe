
<?php
require_once 'config/route.php';

$router = new Router();


$router->addRoute('GET', '', function () {
    include 'paymentpage.php';
});



$router->setNotFound(function () {
    include '404.php';
});


$router->handleRequest();
?> 


