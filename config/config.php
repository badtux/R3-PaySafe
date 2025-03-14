<?php

define('APP_LIVE', true);
define('BASE_PATH', '/paysafe');


define('LOGO', 'https://static.wixstatic.com/media/c7b147_b3d1abb02b5346b68d176a13f1ae27d5~mv2.jpg/v1/fill/w_847,h_807,al_c,q_85/Malkey%20Logo%20Red%20-%20Milindu%20Mallawaratchie.jpg');

if (APP_LIVE) {
    define('MERCHANT_ID_USD', ''); // live 
    define('API_USERNAME_USD', '');
    define('API_PASSWORD_USD', '');

    define('MERCHANT_ID_LKR', ''); //live 
    define('API_USERNAME_LKR', '');
    define('API_PASSWORD_LKR', '');
} else {
    define('MERCHANT_ID_LKR', 'TESTMALKEYRENLKR'); // sandbox 
    define('API_USERNAME_LKR', 'merchant.TESTMALKEYRENLKR');
    define('API_PASSWORD_LKR', '0778afc55fa88712010a6e258f60c565');

    define('MERCHANT_ID_USD', 'TESTMALKEYRENUSD'); // sandbox 
    define('API_USERNAME_USD', 'merchant.TESTMALKEYRENUSD');
    define('API_PASSWORD_USD', 'a0524267d0593d281975c7e69bed8bd4');
}


if (APP_LIVE) {

    define('ASSET_PATH_URL', 'https://malkey.go.digitable.io/paysafe/cmb/');
} else {
    define('ASSET_PATH_URL', 'http://http://cmbgateway.loc/paysafe/');
}



define('NAME', 'Malkey Rent A Car');

//define('CC_LIST', ['thamara.dasun1@gmail.com']);

define('CC_LIST', ['viraj.abayarathna@gmail.com', 'milindum@gmail.com', 'accounts@malkey.lk' ,'piumal0713@gmail.com']);
define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME', 'AKIA5K7Q37VYYJEFNMN2');
define('MAIL_PASSWORD', 'BHwtncYWVjdoVtd5Y9Epu1/UBPV7fRi+zbblftJlqabg');
define('MAIL_ADDRESS', 'rype3-dtaas-platform@rype3.com');
define('MAIL_NAME', 'DT Plutos');
