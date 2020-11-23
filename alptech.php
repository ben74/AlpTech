<?php
namespace Alptech\Wip;
require_once'../vendor/autoload.php';
spark::init();#variables and new autoloader for this scope only
fun::firewall();#trim bad requests
fun::init();#adds an $_ENV['_err']['static class method not found']
router::tryPath();#use this over 404 handler to catch alptech routes defined in conf.php
# exemple -> log collector and log viewer as default ones

$a=[];
$privateClass=new privateClass();
$res[]=fun::getAllVars($privateClass);
$res[]=fun::getAllMethods($privateClass);

list($reflect,$methods,$props,$values)=fun::privateAccess($privateClass);
foreach($props as $prop){
    $v=$prop->getValue($privateClass);
    $prop->setValue($privateClass,$prop->name.'_'.$v.'_');
}
foreach($methods as $method){
    $res[$method->name]=$method->invoke($privateClass);
}
print_r($res);

$b=fun::i(['k1'=>'v1','k2'=>'v2'])->set(['k3'=>'v3','k4'=>'v4']);
$a[]=$b;
$a[]=fun::firewall('GET_HOST_NAME');
$a[]=fun::main();
$webp=fun::thumbnailFileName('y/default.png',100,100).'.webp#tricky';
#plus joli point d'entrée vers le routeur :)
$a[]=fun::tryAlptechRoutes($webp,1);#is okay
$a[]=fun::tryAlptechRoutes('y/thumbs/y-_NotExists-_w100.png',1);#returns null
$a[]=fun::tryAlptechRoutes('y/thumbs/y-_default-_w100.png',1);
echo'<pre>';print_r($a);
#triggersParseException before shutdown
triggersError::a();#parseError as Exception
die;
#echo __file__;
return;?>
