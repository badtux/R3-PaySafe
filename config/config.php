<?php

define('APP_LIVE', false);
define('BASE_PATH', '/paysafe');
define('CC_LIST', ['viraj.abayarathna@gmail.com', 'milindum@gmail.com', 'accounts@malkey.lk' ,'piumal0713@gmail.com']);


// config.php
// Mastercard Gateway Credentials
define('MERCHANT_ID', 'TEST9170372718');
define('API_PASSWORD', '9561cde89b146e22afd2dbec7d145a4f');


ini_set('display_errors', 0);
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'domain' => '.cmbgateway.loc',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}



define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME', 'AKIA5K7Q37VYYJEFNMN2');
define('MAIL_PASSWORD', 'BHwtncYWVjdoVtd5Y9Epu1/UBPV7fRi+zbblftJlqabg');
define('MAIL_ADDRESS', 'rype3-dtaas-platform@rype3.com');
define('MAIL_NAME', 'DT Plutos');
