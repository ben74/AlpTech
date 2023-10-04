<?php
/*
 * $_ENV['conf']['nostats']=1;//reduces memory usage : no mysql results keps
 */
namespace Alptech\Wip;

abstract class model implements hasTable /* extends base */{

    static $pk = 'id', $attributes = ['id'], $registry = [], $sql = [], $params = [], $table = 'notset';// $table et autre parametres à override dans les classes enfants
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

    function save()
    {// to sql
        if (!$this->mod) {//not modified
            return 1;
        }// nothin 2 update, is not dirty
        $params1 = $upd1 = $upd = [];
        if($this->data[static::$pk] and count($this->mod)){
            $updates = $this->mod;
            unset($updates[static::$pk]);// id

            $notInTableFields = array_diff(array_keys($updates), static::$attributes);
            foreach($notInTableFields as $k){
                $upd1[$k]=$updates[$k];
                unset($updates[$k]);
            }
            static::rkv($this->data[static::$pk], $upd1);

            foreach ($updates as $k => $v) {
                $upd[] = '`' . $k . '`=?';
                $params[] = $v;
            }
            $params[] = $this->data[static::$pk];
            $s = "update " . static::$table . " set " . implode(',', $upd) . " where " . static::$pk.'=?';

            return fun::sql3($s, $params);//update
        } elseif('insert') {//
            $notInTableFields = array_diff(array_keys($this->data), static::$attributes);
            foreach ($notInTableFields as $k) {
                $upd1[$k]=$this->data[$k];
                unset($this->data[$k]);
            }

            $af = array_fill(0, count($this->data), '?');
            $s = "insert into " . static::$table . "(`" . implode('`,`', array_keys($this->data)) . "`)values(" . implode(',', $af) . ')';

            $inserted = fun::sql3($s, array_values($this->data));//insert

            static::rkv($inserted, $upd1);
            return $inserted;
        }
    }

    static function rkv($objId, $updates = [])
    {
        if ($updates) {
            foreach ($updates as $k => $v) {
                $params = [$objId, static::$table, $k];
                $cid = fun::sql3("select id as unikk from rkv where model_id=? and model_type=? and k=?", $params);
                if ($cid and 'update') {
                    $a = fun::sql3("update rkv set v=? where id=" . $cid, [$v]);
                } elseif ('insert') {
                    $params[] = $v;
                    $cid = fun::sql3("insert into rkv(model_id,model_type,k,v)values(?,?,?,?)", $params);
                }
            }
            // does field exists ? or insert
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
        $current = new static();
        static::$sql['where']['`' . $k . '`' . $rel] = $v;
        return $current;//$current::$sql
    }

    static function getWhere($wheres = []){
        $current = new static();
        foreach ($wheres as $k => $v) {
            static::$sql['where']['`' . $k . '` = '] = $v;
        }
        return $current->get();//$current::$sql
    }

    static function loadBy($k, $v)
    {
        $rk = static::$table . ':' . $k . ':' . $v;
        if (isset(static::$registry[$rk])) {
            return static::$registry[$rk];
        }
        $a=static::where($k, $v)->get();
        if(!$a)unset($a);
        static::$registry[$rk] =$a;
        return $a;
    }

    static function load($id)
    {
        $rk=static::$table.':'.static::$pk.':'.$id;
        if (isset(static::$registry[$rk])) {
            return static::$registry[$rk];
        }
        $a = static::where(static::$pk, $id)->get();
        if(!$a)return null;
        static::$registry[$rk] = reset($a);
        return reset($a);
    }

    function get()
    {
        $a=static::$table;//__CLASS__;//self::$table;//get_class()
        foreach (static::$sql['where'] as $k => $v) {
            $wheres[] = $k . '?';
            $params[] = $v;
        }
        static::$sql = [];// reset for next usage pleaz :)
        $datas = fun::sql3("select * from " . static::$table . " where " . implode(' and ', $wheres), $params);

        if(!$datas){
            return null;
        }
        $pk = $res = [];
        foreach ($datas as $data) {
            if (isset($data[static::$pk])) {
                $pk[] = $data[static::$pk];
            }
        }

        if($pk){
            $s="select model_id as K1,k as K2,v as K3 from rkv  where model_id in (" . implode(',', $pk).") and model_type='".static::$table."'";
            $rkvs = fun::sql3($s);
        }

        foreach ($datas as $data) {
            $k = null;
            if (isset($data[static::$pk])) {
                $k = $data[static::$pk];
            }
            if (isset($rkvs[$k])) {
                $data = $rkvs[$k] + $data;
            }
            $res[$k] = new static($data, false);
        }
        return $res;
        // return as new instance
    }

    static function find($id){
        return static::load($id);
    }

    static function findOrFail($id)
    {
        $x = static::load($id);
        if (!$x) throw new \Exception(static::$table . '#' . $id . ' not found');
        return $x;
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
