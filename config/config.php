<?php

define('APP_LIVE',false);
define('BASE_PATH', '/paysafe');


define('LOGO', 'https://static.wixstatic.com/media/c7b147_b3d1abb02b5346b68d176a13f1ae27d5~mv2.jpg/v1/fill/w_847,h_807,al_c,q_85/Malkey%20Logo%20Red%20-%20Milindu%20Mallawaratchie.jpg'); 

if (APP_LIVE) {
    define('MERCHANT_ID', '9170372718'); // live 
    define('API_USERNAME', 'merchant.9170372718');
    define('API_PASSWORD', '2bc2cac63cef6ebf59c7c925e571ee49');

} else {
    define('MERCHANT_ID', 'TEST9170372718'); // sandbox 
    define('API_USERNAME', 'merchant.TEST9170372718');
    define('API_PASSWORD', '9561cde89b146e22afd2dbec7d145a4f');
}
define('NAME', 'Malkey Rent A Car');

define('CC_LIST', ['thamara.dasun1@gmail.com']);
//define('CC_LIST', ['viraj.abayarathna@gmail.com', 'milindum@gmail.com', 'accounts@malkey.lk' ,'piumal0713@gmail.com']);
define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME', 'AKIA5K7Q37VYYJEFNMN2');
define('MAIL_PASSWORD', 'BHwtncYWVjdoVtd5Y9Epu1/UBPV7fRi+zbblftJlqabg');
define('MAIL_ADDRESS', 'rype3-dtaas-platform@rype3.com');
define('MAIL_NAME', 'DT Plutos');