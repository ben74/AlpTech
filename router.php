<?php

namespace Alptech\Wip;

class router
{
    /*also fun::tryAlptechRoutes*/
    static function tryPath($url = '', $virtual = 0)
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $url = fun::firstBefore($url, ['?','#']);
        #/_/ #virtual route separator for class/action mvc behaviour !
        if(preg_match('~^/route1~',$url,$m)){
            #then split remaining / are parameters
            $_found=1;
        }
        $_notfound=1;
    }
}
