<?php 

define('APP_LIVE', true);

if(APP_LIVE){
    define('SMPLY_PUBKEY', 'lvpb_MDMwNGEzMmYtNzQxZi00MWNkLWEyOTktMTZlNDJjY2FlZTYw'); //live 
    define('SMPLY_PVKEY', '1nwUv+QJwDCQ0FviCoLcgrzrgHb3V2yfhjD9xjVX3lJ5YFFQL0ODSXAOkNtXTToq');
}
else {
    define('SMPLY_PUBKEY' , 'sbpb_NjU0NWMyMjMtMzVmYi00ZWVjLWI0NDItN2I4MjljZWJiM2I0'); //sandbox
    define('SMPLY_PVKEY' , '5Hsh1LbHPktNOcWZ0ZBwUQADlyquDfSmiPMwX7qxrzd5YFFQL0ODSXAOkNtXTToq');
}

?>