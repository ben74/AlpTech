<?php
namespace Alptech\Wip;

class redisFailSafe
{//
    static $folder = 'z/', $ext = '.redisfs', $db = 0;
    static function path($k){return static::$folder . static::$db .'/'. $k . static::$ext;}


    static function get($k)
    {
        $f = static::path($k);
        $res = @file_get_contents($f);
        if (strpos($res, '{') === 0 || strpos($res, '[') === 0) {
            $res = json_decode($res, true);
        }
        if (is_numeric($res)) {
            if (is_int($res + 0)) $res = (int)$res;
            elseif (is_float($res + 0)) $res = (float)$res;
        }
        return $res;
    }


    function __call($method, $args = []){return static::call($method, $args);}
    static function __callstatic($method, $args = []){return static::call($method, $args);}

    static function call($method = null, $args = [])
    {
        if(count($args)>1)return static::set($args[0],$args[1]);
        return static::get($args[0]);
        return ['redisFS:__call:'.$method, $args];
    }

    static function hgetall($k){return static::get($k);}
    static function sMembers($k,$from = 0, $to = 0){return static::get($k);}
    static function zRange($k,$from = 0, $to = 0){return static::get($k);}
    static function lrange($k, $from = 0, $to = 0){return static::get($k);/* to=-1 */}

    static function incrBy($k, $by){
        $a=static::get($k);
        if(!$a || is_array($a))$a=0;
        $a+=$by;
        return static::set($k,$a);
    }
    static function decrBy($k, $by){return static::incrBy($k,-$by);}
    static function incr($k){return static::incrBy($k,1);}
    static function decr($k){return static::incrBy($k,-1);}

/****** ****/
    static function write($k, $data = null)
    {
        if (!is_dir(static::$folder . static::$db)) {
            mkdir(static::$folder . static::$db, 0777, true);
        }
        if (is_array($data) || is_object($data)) $data = json_encode($data);
        return file_put_contents(static::path($k), $data);
    }


    static function brpop($k){$res=false;while(!$res){$res=static::lpop($k);if(!$res)sleep(1);}return $res;}
    static function blpop($k){$res=false;while(!$res){$res=static::rpop($k);if(!$res)sleep(1);}return $res;}
    static function lpop($k)
    {
        $a = static::get($k);if(!$a)return;
        $res = array_shift($a);
        static::set($k, $a);
        return $res;
    }

    static function rpop($k)
    {
        $a = static::get($k);if(!$a)return;
        $res = array_pop($a);
        static::set($k, $a);
        return $res;
    }

    static function rpush($k, $v)
    {
        $a=static::get($k);if(!$a)$a=[];
        $a[]=$v;
        return static::set($k,$a);
    }

    static function lpush($k, $v)
    {
        $a=static::get($k);if(!$a)$a=[];
        array_unshift($a,$v);
        return static::set($k,$a);
    }

    static function set($k, $v){return static::write($k,$v);}

    static function ttl($k)
    {
        return filemtime(static::path($k)) - time();
    }

    static function exists($k)
    {
        return is_file(static::path($k));
    }

    static function del($k)
    {
        return @unlink(static::path($k));
    }

    static function keys($k = '*')
    {
        return glob(static::path($k));
    }


    static function select($db = 0)
    {
        return static::$db = $db;
    }

    static function auth($pw = 0)
    {
        return;
    }

    static function hmset($k,$kv){
        $a=static::get($k);if(!$a)$a=[];
        $a+=$kv;return static::set($k,$a);
    }
    static function hset($k,$key,$value){
        $a=static::get($k);if(!$a)$a=[];
        $a[$key]=$value;return static::set($k,$a);
    }
    static function hget($k,$key){
        $a=static::get($k);if(!$a)$a=[];
        return isset($a[$key])?$a[$key]:null;
    }


    static function type($k=null){return 1;}

}
