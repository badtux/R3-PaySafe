<?php

define('APP_LIVE', false);
define('BASE_PATH', '/paysafe');

if (APP_LIVE) {
    define('MERCHANT_ID', '9170372718'); // live 
    define('API_USERNAME', 'merchant.TEST9170372718'); 
    define('API_PASSWORD', '2bc2cac63cef6ebf59c7c925e571ee49'); 
} else {
    define('MERCHANT_ID', 'TEST9170372718'); // sandbox 
    define('API_USERNAME', 'merchant.TEST9170372718'); 
    define('API_PASSWORD', '9561cde89b146e22afd2dbec7d145a4f'); 
}