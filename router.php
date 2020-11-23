<?php

namespace Alptech\Wip;

class router
{
    static function test(){
        fun::_die(__METHOD__);
    }
    /*also fun::tryAlptechRoutes*/
    static function tryPath($url = '', $virtual = 0)
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $url = fun::firstBefore($url, ['?','#']);

        if(substr_count(ltrim($url,'/'),'/')>0){
            $y=explode('/',ltrim($url));
            $c=count($y);$i=0;
            $_ENV['urlParameters']=[];
            while($i<$c){
                $_ENV['urlParameters'][$y[$i]]=$_GET[$y[$i]]=$_REQUEST[$y[$i]]=$_GET[$y[$i]]=$y[$i+1];#k=$v ....
                $i+=2;#jumps each couple of parameters
            }
        }
/*only in 404 mode ... a/logCollector ... etc .. a/logCollector/b/index/c/1*/
        if (isset($_GET['a'])) {
            $method = 'index';#default
            $class = $_GET['a'];
            if (isset($_GET['b'])) {
                $method = $_GET['b'];
            }
            $ok = 0;
            $pf='\\' . __NAMESPACE__ . '\\' . $class;
            $ok += class_exists($pf);
            $ok += method_exists($pf, $method);
            if ($ok == 2) {
                $ok = $pf::$method();
                $a = 1;
            }else{
                $_ENV['_err']['routing'][]="$url => $class::$method";
            }
            #a=logCollector
        }

        $routes=fun::getconf('routes');
        foreach ($routes as $route => $classMethod) {
            if (preg_match('~^' . $route . '~i', $url, $m)) {
                $pf='\\' . __NAMESPACE__ . '\\' . $classMethod[0];
                $ok += class_exists($pf);
                $ok += method_exists($pf,$classMethod[1]);
                if ($ok == 2) {
                    $ok = $pf::$classMethod[1]();
                }else{
                    $_ENV['_err']['routing'][]="$url => $classMethod[0]::$classMethod[1]";
                }
            }
        }

        #/_/ #virtual route separator for class/action mvc behaviour !
        if(preg_match('~^/route1~',$url,$m)){#
            #then split remaining / are parameters
            $_found=1;
            return 1;
        }
        $_notfound=1;
        return;
    }
}
