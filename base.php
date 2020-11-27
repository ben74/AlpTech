<?php

namespace Alptech\Wip;
/*
Adds and objects registry for debugging purposes & picking outside of context Singleton style !!
usage :
Class Weird_Doctrine_Orm_Object extends \Alptech\Wip\Base
 */
class base
{
    #public $_data = [];
    static $t=0,$_shared = [];#collectif tant que non re-déclaré sur le scope de l'entité enfant
    #base::_shared['base']=1;
    /** set breakpoints here !!!! */
    function set($k, $v=0, $hydrate = 0, $_newer = 0, $virtual = 0 /* might provide additional contexts */)
    {
        $breakpoint=1;#interception !!
        if ($virtual) {
            return;
        }
        if (!isset($this)) {
            $el = static::i();
        } else {
            $el = $this;
        }
        if (!$k) {
            return $el;
        }

        if(is_array($k)){
            foreach($k as $k2=>$v2){
                $el->set($k2, $v2, $hydrate, $_newer, $virtual);
            }
            return $el;
        }

        static::$t=1;
        $el->{$k} = $v;#passe par __set si non défini
        static::$t=0;
        return $el;
    }

    #on non-existing -- Loss the proper Backtrace Context when Passed using set(
    function __set($k, $v)
    {
        if (static::$t) {#prevents looping :: second passage
            $this->{$k} = $v;
            return $this;
        }
        return $this->set($k, $v, 0, 1);#1er passage -- afin de pouvoir l'intercepter plus haut
    }

    function inc($k)
    {
        $_ENV['zzset'] = 1;#static::$zzset=1;
        if (!isset($this->$k)) {
            $this->$k = 0;
        }
        $this->$k++;
        $_ENV['zzset'] = 0;#static::$zzset=0;
    }

    static function push($array, $k = null, $v = null)
    {
        if (!isset($this)) {$el = static::i();} else {$el = $this;}
        $a = 1;
        if (is_null($v) and $k) {
            $v = $k;
            $k = null;
        }#simple push
        if (!$k and $k !== 0) {
            $err = 'no keys';
        }
        if (!isset($el->{$array})) {
            $el->{$array} = [];
        }
        if (is_null($k) or (!$k and $k !== 0)) {
            $el->{$array}[] = $v;
        } else {
            $el->{$array}[$k] = $v;
        }
    }

#for interceptions
    static function get($k)
    {
        if (!isset($this)) {$el = static::i();} else {$el = $this;}
        if (!isset($el->$k)) {
            return null;
        }
        return $el->$k;
    }

/* multiples or singleton ?? */
    static function getinstances()
    {
        return $_ENV['_obj'][static::gc()];
    }

/*
 * Can perform the singleton check here
$a=fun::i(['k1'=>'v1','k2'=>'v2'])->set(['k3'=>'v3','k4'=>'v4']);
*/
    static function i($p = null)
    {
        $class = static::gc();
        if(!isset($_ENV['_obj']))$_ENV['_obj']=[];
        if (!isset($_ENV['_obj'][$class])) {# creates one
            if (is_array($p) and count($p) == 1 and array_keys($p) == [0]) {
                $p = reset($p);#unpack one dimension
                $a = 1;
            }
            if (0 and $p) {#replaced with $o->setOrGetKv($p);
                $reflector = new ReflectionClass($class);
                $o = $reflector->newInstanceArgs($p);
            } else {
                $o = new static();
            }
        } else {
            $o = reset($_ENV['_obj'][$class]);
        }
        $o->setOrGetKv($p);
        return $o;
    }

    function __construct()
    {
        $p = func_get_args();
        if ($p) {
            $a = 1;
            $this->setOrGetKv($p);
            #$this->_data = $p;
        }
        $_ENV['_obj'][ static::gc()][] = $this;
    }

#herited
    function __wakeup()
    {#unserialize
        $class = static::gc();
        if ($class == 'plBasket') {
            $a = 1;
        }
        $_ENV['_obj'][$class][] = $this;
    }
#on non-existing : get from collective self::$data ?
#intercepter propriété non séttée
#[type] => 1,[message] => Cannot access empty property
    function __get($k)
    {
        if (!$k) {
            return null;
        }
        return null;#à décorer - null de toutes façons
        $k = strtolower($k);
        if (!isset($this->$k)) {
            return null;#ah-ah beware of this !
        }
        return $this->$k;
    }
    /* decorator */
#$b is array of passed parameters, could be several ..
    function __call($a, $b)
    {
        $a = strtolower($a);
        if (substr($a, 0, 3) == 'get') {
            $a = substr($a, 3);
            if (isset($this->$a)) {
                return $this->$a;
            }
            return null;#if empty .. try
        } elseif (substr($a, 0, 3) == 'set') {
            $a = substr($a, 3);
            $this->$a = $b[0];
            return $this;
        }
        if (0) {
            #if (array_key_exists($a, $this->data)) {return $this->data[$name];} #obj->name();
#failsafe for _ method differences
            $name = strtolower(str_replace('_', '', $name));
            $matches = [$name, 'get' . $name, 'set' . $name];
            foreach ($matches as $method) {
                if (method_exists($this, $method)) {
                    return call_user_func_array([$this, $method], $args);
                    #return $this->$method($args);#arguments as array <> call_user_func()
                }
            }
        }

        $namespace = '';
        $func = $namespace . '\\' . $a;
        $exists = function_exists($func);
        if ($exists) {
            return call_user_func_array($func, $b);
        } else {
            $err = 'nf';
        }
        return $this;#otherwise
    }

/*base::method(1);*/
#$b is array of passed parameters, could be several ..
    static function __callStatic($a, $b)
    {
        $i = static::i();
        if(!method_exists($i,$a)){
            $_ENV['_err']['static class method not found'][]=static::gc().'::'.$a;
            return;
        }
        return $i->{$a}($b);#[0]
        #set singleton value
    }

    static function setStatic($a, $b)
    {
        static::${$a}=$b;
    }

    static function getStatic($a)
    {
        static::${$a};
    }

    /* php5.4 compactible, instead of static::class */
    static function gc()
    {
        return get_called_class();
        #return static::class;
    }

    static function setOrGetKv($p = null, $obj = 0)
    {
        if ($obj) {
            $el = $obj;
        } elseif (!isset($this)) {
            $el = static::i();
        } else {#is declared object
            $el = $this;
        }
        if (!$p) {
            return $el;
        }

        if (is_array($p)) {
            foreach ($p as $k => $v) {
                $el->{$k} = $v;
            }
            return $el;
        } elseif (isset($el->{$p})) {
            return $el->{$p};
        } else {
            return null;
        }

    }
/** todo */
    function __invoke()
    {
        $a = 1;
    }

    function __clone()
    {
        $a = 1;
    }

}
return;?>
