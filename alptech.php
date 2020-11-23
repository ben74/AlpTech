<?php
namespace Alptech\Wip;/*case sensitive is really important here boy !!!

v=333;cd ~/home/d9/vendor/alptech/wip;git add *;gu;git tag v0.0.$v;git push origin v0.0.$v;git push -f;


cuj https://d9.home/alptech.php a '' 1

. functions.bash;reloadVars;#php cli.php getConf all;
pk=$(echo -n $logCollectorSecret`date +$logCollectorSeed` | md5sum | awk '{print $1}');echo $pk;
curl -sSLk -b "pk=$pk;XDEBUG_SESSION=1" -d '{"host":"'.$host.'","type":"php500","k":"k","v":"v","severity":9}' $logCollectorUrl;#<<<< OK :)

cuj https://d9.home/alptech.php?a=logViewer a '' 1;#$logCollectorUrl




cuj https://d9.home/alptech.php?a=GET_HOST_NAME-firewall a '' 1;#blocked


*/
require_once'../vendor/autoload.php';
#require_once'../vendor/alptech/wip/spark.php';#does not necesseraly need composer autoload
spark::init();
fun::firewall();
fun::init();#$_ENV['_err']['static class method not found']
router::tryPath();#-> log collector or not

#triggersErrorAndException before shutdown
$a=[];
$b=fun::init();
$a[]=fun::logCollector();
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
triggersError::a();#parseError as Exception
die;
#echo __file__;
return;?>
