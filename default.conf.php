<?php
$_ENV['alpTechConf'] = [
    'mysql_user'=>'a',
    'mysql_pass'=>'b',
    'mysql_db'=>'b',

    'logdir'=>'logs',
    'logCollector'=>'https://1.x24.fr/a/logCollector.php',
    'sendLogs'=>1,
    'pathSeparator'=>'-_',
    'thumbAuthorizedWidths' => [100],
    'thumbAuthorizedHeights' => [100],
    'thumbnailsDir' => 'y/thumbs/',
    'defaultImage'=>'y/default.png',
    'mediaTypes'=>'jpeg,jpg,png,webp,ico,gif,woff,ttf,eot,woff2,css,js,map',#404 is /**/
];
$f='conf.php';
if (!is_file($f)) {
    file_put_contents($f,'<?php /*$yourConf=[];$_ENV[\'alpTechConf\'] = array_merge($_ENV[\'alpTechConf\'],$yourConf);*/return;?>');#AKA :: setup
    require_once $f;
}
return; ?>

