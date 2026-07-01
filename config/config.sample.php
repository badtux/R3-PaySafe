<?php

define('APP_LIVE', true); 
define('BASE_PATH','/paysafe/cmb');


$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$tenant = explode('.', (string)$host)[0];

if ($tenant === 'localhost' || empty($tenant)) {
    $tenant = 'default';
}

if (!defined('TENANT')) {
    define('TENANT', $tenant);
}

$databaseName = "{$tenant}_paysafe";

define('LOGO', 'https://static.wixstatic.com/media/c7b147_b3d1abb02b5346b68d176a13f1ae27d5~mv2.jpg/v1/fill/w_847,h_807,al_c,q_85/Malkey%20Logo%20Red%20-%20Milindu%20Mallawaratchie.jpg');

if (APP_LIVE) {
    define('MERCHANT_ID_USD', 'MALKEYRENUSD'); // live 
    define('API_USERNAME_USD', 'merchant.MALKEYRENUSD');
    define('API_PASSWORD_USD', '5c20ea34cca4a7a383352b0056482568');
    define('REDIRECT_URL', "https://{$tenant}.go.digitable.io/paysafe/cmb/status");

    define('MERCHANT_ID_LKR', 'MALKEYRENLKR'); //live 
    define('API_USERNAME_LKR', 'merchant.MALKEYRENLKR');
    define('API_PASSWORD_LKR', '8ac724a6d1a9b99f4060c808142d47c6');


    define('DATABASE_URL', 'mongodb://192.168.167.75:27017');
    define('COLLECTION', 'payments');
    define('DB', $databaseName);

    define('ASSET_PATH_URL', 'https://malkey.go.digitable.io/paysafe/cmb/');
    define('CC_LIST', ['milindum@gmail.com','accounts@malkey.lk','billing@malkey.lk','info@malkey.lk' ]);
    define('BCC_LIST', ['piumal0713@gmail.com','viraj.abayarathna@gmail.com',]);

} else {
    define('MERCHANT_ID_LKR', 'TESTMALKEYRENLKR'); // sandbox 
    define('API_USERNAME_LKR', 'merchant.TESTMALKEYRENLKR');
    define('API_PASSWORD_LKR', '0778afc55fa88712010a6e258f60c565');

    define('MERCHANT_ID_USD', 'TESTMALKEYRENUSD'); // sandbox 
    define('API_USERNAME_USD', 'merchant.TESTMALKEYRENUSD');
    define('API_PASSWORD_USD', 'a0524267d0593d281975c7e69bed8bd4');
    define('REDIRECT_URL', "http://{$tenant}.paymentgateway.loc/cmb/status");

  define('DATABASE_URL', 'mongodb+srv://piumal0713:Adyp%400713@cluster0.8bv15.mongodb.net/?retryWrites=true&w=majority&authSource=admin');
    define('COLLECTION', 'payments');
    define('DB', $databaseName);

    define('ASSET_PATH_URL', 'http://http://paymentgateway.loc/cmb/');
    define('CC_LIST', []);
    define('BCC_LIST', []);
    

}


define('NAME', 'Malkey Rent A Car');
define('MAIL_DRIVER', 'ses');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'AKIA5K7Q37VY26UOCQ64');
define('MAIL_PASSWORD', 'BIEtWDDtnqIBa5tSGSHQm9Bfi+m6i6iu6wcYRnqbxB2O');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_SECURE', false);
define('MAIL_ADDRESS', 'no-reply@go.digitable.io');
define('MAIL_NAME', 'Malkey Rent A Car');