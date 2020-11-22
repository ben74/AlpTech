<?php
return [
    'devIps'=>['127.0.0.1'],'localhostIps'=>['127.0.0.1'],
    'mysql_user' => 'a',
    'mysql_pass' => 'b',
    'mysql_db' => 'b',
    /** activate the logs here */
    'log'=>0,
    'sendLogs' => 0,
    'logdir' => 'logs',

    'logCollectorUrl' => $_SERVER['DOCUMENT_ROOT'].'/alptech.php',#exposed path loading alptech
    'logCollectorSecret'=>'hophophop',
    'logCollectorSeed'=>'%y%m%d',#one valid per day, avoid Hours if datetime resolution is not the same sync or timezone, will cause mismatches
    'authorizedIps'=>['local'=>'127.0.0.1','l6'=>'::1','pom2'=>'2a01:e0a:2d7:fe0:cda9:527f:604a:10e9'],

    'pathSeparator' => '-_',
    'thumbAuthorizedWidths' => [100],
    'thumbAuthorizedHeights' => [100],
    'thumbnailsDir' => 'y/thumbs/',
    'defaultImage' => 'y/default.png',
    'mediaTypes' => 'jpeg,jpg,png,webp,ico,gif,woff,ttf,eot,woff2,css,js,map',#404 is /**/
];?>

