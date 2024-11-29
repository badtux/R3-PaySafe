<?php

define('APP_LIVE', false);
define('BASE_PATH', '');
define('CC_LIST', ['viraj.abayarathna@gmail.com', 'milindum@gmail.com', 'accounts@malkey.lk' ,'piumal0713@gmail.com']);

if (APP_LIVE) {
    define('SMPLY_LKR_PUBKEY', 'lvpb_MDMwNGEzMmYtNzQxZi00MWNkLWEyOTktMTZlNDJjY2FlZTYw'); //live 
    define('SMPLY_LKR_PVKEY', '1nwUv+QJwDCQ0FviCoLcgrzrgHb3V2yfhjD9xjVX3lJ5YFFQL0ODSXAOkNtXTToq');

    define('SMPLY_USD_PUBKEY', 'lvpb_ZGQzYWMzNzYtZGQ4MS00NGFlLTkyZTItODk4NjMyZmY4YWU2'); //live
    define('SMPLY_USD_PVKEY', 'BBrLYBh5dkefJEiG4sa0Ojr3nF101eQN0dtQx34juY95YFFQL0ODSXAOkNtXTToq');
} else {
    define('SMPLY_LKR_PUBKEY', 'sbpb_NjU0NWMyMjMtMzVmYi00ZWVjLWI0NDItN2I4MjljZWJiM2I0'); //sandbox
    define('SMPLY_LKR_PVKEY', '5Hsh1LbHPktNOcWZ0ZBwUQADlyquDfSmiPMwX7qxrzd5YFFQL0ODSXAOkNtXTToq');

    define('SMPLY_USD_PUBKEY', 'sbpb_ZTY2NTA3ZjYtZTBhZi00OGZmLTg0N2YtZmE0NWJlMTBkOGVm'); //sandbox
    define('SMPLY_USD_PVKEY', 'TrwuQb5xNUoriNasPO8cDnfIvUPQ35ta+0SvXi80XS95YFFQL0ODSXAOkNtXTToq');
}

define('MAIL_DRIVER', 'smtp');
define('MAIL_HOST', 'email-smtp.us-east-1.amazonaws.com');
define('MAIL_PORT', 465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME', 'AKIA5K7Q37VYYJEFNMN2');
define('MAIL_PASSWORD', 'BHwtncYWVjdoVtd5Y9Epu1/UBPV7fRi+zbblftJlqabg');
define('MAIL_ADDRESS', 'rype3-dtaas-platform@rype3.com');
define('MAIL_NAME', 'DT Plutos');
