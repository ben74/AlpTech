<?php
/*
 * $_ENV['conf']['nostats']=1;//reduces memory usage : no mysql results keps
 */
namespace Alptech\Wip;

abstract class model implements hasTable /* extends base */{

    static $pk = 'id', $registry=[], $sql = [], $params = [], $table = 'notset';// $table et autre parametres à override dans les classes enfants
    public $data = [], $mod=[];

    function __construct($data = [] , $new = true)
    {
        if ($data) {
            $this->data = $data;
            if($new) $this->mod = $data;
        }
    }

    function __toString(){
        return json_encode($this->data);
    }
    function __unserialize(array $data){
        $this->data = $data;
    }
    function __serialize(){// replaces sleep
        return $this->data;
    }
    function __sleep(){
        return $this->data;
    }
    function __wakeup(){
        // this->connect
    }

    function save(){// to sql
        if (!$this->mod) return 1;// nothin 2 update, is not dirty
        if($this->data[static::$pk] and count($this->mod)){
            $upd=[];
            $updates=$this->mod;
            unset($updates[static::$pk]);
            foreach($updates as $k=>$v){
                $upd[]='`'.$k.'`=?';$params[]=$v;
            }
            $params[]=$this->data[static::$pk];
            $s="update " . static::$table . " set ".implode(',',$upd)." where " . static::$pk.'=?';
            return fun::sql3($s, $params);//update
        }else{
            $af=array_fill(0,count($this->data),'?');
            $s="insert into " . static::$table . "(`".implode('`,`',array_keys($this->data))."`)values(".implode(',',$af).')';
            return fun::sql3($s, array_values($this->data));//insert
        }
    }

    function __get($k)
    {
        if (!isset($this->data[$k])) return null;
        return $this->data[$k];
    }

    function __set($k, $v)
    {
        if (!isset($this->data[$k]) or $this->data[$k] != $v) {
            $this->mod[$k] = $v;
        }
        $this->data[$k] = $v;
    }

    function __destruct(){
        $this->save();
    }

    static function reset(){
    }

    static function where($k, $v, $rel = '=')
    {
        static::$sql['where']['`' . $k . '`' . $rel] = $v;
    }

    static function loadBy($k, $v)
    {
        $rk=static::$table.':'.$k.':'.$v;
        if (isset(static::$registry[$rk])) return static::$registry[$rk];
        static::where($k, $v);
        $a=static::get();
        if($a)static::$registry[$rk] =$a;
        return $a;
    }

    static function load($id)
    {
        $rk=static::$table.':'.static::$pk.':'.$id;
        if (isset(static::$registry[$rk])) return static::$registry[$rk];
        static::where(static::$pk, $id);
        $a=static::get();
        if($a)static::$registry[$rk] = $a[0];// return unique object
        return $a[0];
    }

    static function get()
    {
        $a=static::$table;//__CLASS__;//self::$table;//get_class()
        foreach (static::$sql['where'] as $k => $v) {
            $wheres[] = $k . '?';
            $params[] = $v;
        }
        static::$sql = [];
        $datas = fun::sql3("select * from " . static::$table . " where " . implode(' and ', $wheres), $params);
        if(!$datas)return null;
        foreach($datas as &$data){
            $data = new static($data, false);
        }
        return $datas;
        // return as new instance
    }

}

interface hasTable{
    //static $table;//function table();
}

return; ?>

$u=new user(['name'=>'bob']);
$u->save();
user::load(1);
user2::load(1);
user::reset();

user::where('id',1,'=');
