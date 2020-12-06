<?php
/*
cd ~/home/d9/vendor/alptech/wip/;. functions.bash;reloadVars;#extracts all vars to bash
phpx ~/home/d9/vendor/alptech/wip/cli.php "?host=yo&url=a&body={\"pop\":1}&dr="fr"&ip=127&cookies[a]=1&cookies[b]=2&post[a]=1&get[b]=2&request[c]=3"
*/
namespace Alptech\Wip;
require_once __DIR__.'/spark.php';

$_SERVER['REQUEST_SCHEME']='https:';
$_SERVER['HTTP_HOST']='alptech.dev';

spark::init();#doesnt require composer autoloader
router::tryPath();#it does work fine :)

if(!isset($argv)){fun::_die('#'.__line__);}
$filename=array_shift($argv);
switch($argv[0]){
#x=`php cli.php getConf logCollectorSecret`
#todo get all variables and unpack them
    case'get':
    case'getConf':
#phpx cli.php get all;
        if ($argv[1] == 'all') {
            $allConf = fun::getConf();
            $z = [];
            $ifs = '§';
#Bash does not support multidimensionnal Arrays
            $_b=fun::var2bash($allConf,'',0,0);#old style
            fun::_die('( ' . implode($ifs, $_b) . ' )');
            break;
        }
        fun::_die(fun::getConf($argv[1]));break;
    case'set':
        break;;#....
}

fun::_die('#not recognized:'.$argv[0]);
return;
?>
