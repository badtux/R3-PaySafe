<?php

define('APP_LIVE', true);
define('BASE_PATH', '/paysafe/sey');

define('LOGO', 'https://www.helpagesl.org/assets/images/logo-sri-lanka.webp');

$host = $_SERVER['HTTP_HOST'];
$tenant = explode('.', $host)[0];

if ($tenant === 'localhost' || empty($tenant)) {
    $tenant = 'default';
}

$databaseName = "{$tenant}_paysafe";

if (APP_LIVE) {
    define('MERCHANT_ID', 'TESTSEYLAN124');
    define('API_USERNAME', 'merchant.TESTSEYLAN124');
    define('API_PASSWORD', '5426b5fd696461dc7f6a68d0cc4a78f9');

    define('REDIRECT_URL', 'https://helpage.go.digitable.io/paysafe/sey/status');
    define('DATABASE_URL', 'mongodb://192.168.167.75:27017');
    define('COLLECTION', 'payments');
    define('DB', $databaseName);

    define('ROBOT_SITE_KEY', '6LfFD_QrAAAAAFN9rh4-zClDQ3jhdDsR7CKtiFNG');
    define('ROBOT_SECRET_KEY', '6LfFD_QrAAAAAO6IB7_gw_oDuRteZ-M3A7gYWkHr');
} else {
    define('MERCHANT_ID', 'TESTSEYLAN124');
    define('API_USERNAME', 'merchant.TESTSEYLAN124');
    define('API_PASSWORD', '5426b5fd696461dc7f6a68d0cc4a78f9');

    define('DATABASE_URL', 'mongodb+srv://piumal0713:Adyp%400713@cluster0.8bv15.mongodb.net/?retryWrites=true&w=majority&authSource=admin');
    define('COLLECTION', 'payments');
    define('DB', $databaseName);
}

if (APP_LIVE) {
    define('ASSET_PATH_URL', 'https://seylan.go.digitable.io/paysafe/seylan/');
} else {
    define('ASSET_PATH_URL', 'http://paymentgateway.loc/seylan/');
}

define('NAME', 'HelpAge');
define('CC_LIST', ['piumal0713@gmail.com','viraj.abauarathna@gmail.com']);
define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME', 'AKIA5K7Q37VYYJEFNMN2');
define('MAIL_PASSWORD', 'BHwtncYWVjdoVtd5Y9Epu1/UBPV7fRi+zbblftJlqabg');
define('MAIL_ADDRESS', 'rype3-dtaas-platform@rype3.com');
define('MAIL_NAME', 'HelpAge');
