<?php
chdir(__DIR__);
// Is the script placed on root ?
$f='../../../vendor/autoload.php';if(is_file($f))require_once $f;
$f='vendor/autoload.php';if(is_file($f))require_once $f;

use Alptech\Wip\fun;
use Alptech\Wip\user;
use Alptech\Wip\user2;

try {
    if(0){
    fun::main(['coucou'=>1]);// autoloads functions and exec init static method
    $a = [];
    $a['Input Parameters']=$_GET;
    if (fun::$env == 'http') {// Runs firewall in HTTP mode, try uploading a .php file, or some Obvious Injection Patterns
        $blocked = fun::firewall();
        if ($blocked) {
            $a['Blocked'] = $blocked;
            die(fun::cleanHtml(json_encode($a)));
        }
    }

    $db = '1.db';
    $unik = time();
    $a['sqlite'.__LINE__] = fun::sqlite($db, '/* piphop */ create table if not exists t1(id integer primary key autoincrement,k varchar(255),k2 varchar(255))');
    $a['sqlite'.__LINE__] = fun::sqlite($db, '/* BenJiii */ CREATE /* UNIQUE */ index if not exists  t1_k on t1(k)');
    $a['sqlite'.__LINE__] = fun::sqlite($db, 'insert into t1(k,k2)values(:k,:k2)', ['k' => $unik, 'k2' => $_GET['a']]);
    $a['sqlite'.__LINE__] = fun::sqlite($db, 'update t1 set k2=:k2 where k=:k', ['k' => $unik, 'k2' => $_GET['b']]);
    $a['sqlite'.__LINE__] = fun::sqlite($db, 'select id as pkid,k2 as roww from t1');

    return;
    }
// ORM Test AND Stuff :)
    fun::$connection=['h'=>'127.0.0.1','u'=>'root','p'=>'b','db'=>'developer'];//$s['h'], $s['u'], $s['p'], $s['db'];
    fun::sql3("create table if not exists rkv(id   int auto_increment,model_id int,model_type varchar(255) null,k varchar(255) null,v varchar(255) null,constraint id primary key (id))");
/*
 SELECT 1        FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'yourschema' AND TABLE_NAME='yourtable' AND INDEX_NAME='yourindex') != 1
 */
//fun::sql3("create index index2 on rkv (model_type, k, model_id)");//if not exists
//fun::sql3("create index rkv_model_id_model_type_index on rkv (model_id, model_type);");//if not exists
    fun::sql3("create table if not exists user(id   int auto_increment,name varchar(255) null,constraint id primary key (id))");
    fun::sql3("create table if not exists user2(id   int auto_increment,name varchar(255) null,constraint id primary key (id))");

    if('try once to check user is persisted upon shudown via __destroy model method'){
        $u = new user(['name' => time()]);
        $u1 = new user2(['name' => 'bob','a'=>time(),'b'=>time()]);//rkv = product_attributes
        $u2 = new user2(['name' => 'bennn','c'=>time(),'d'=>time()]);//rkv = product_attributes
        $u2->save();
        $res[__LINE__] = user2::getWhere(['name' => 'bennn', 'id' => 1]);// get from memory ?
        $res[__LINE__] = user2::findOrFail(1);// get from memory ?
        $res[__LINE__] = user2::where('name', 1)->get();// lots of reulsts
        $res[__LINE__] = user2::where('name', 'bennn')->get();// lots of reulsts
        // u1 will be saved at shutdown
        $res[__LINE__] = $u2MightBeMultipleResults = user2::loadBy('name','bennn');



        $u1->name='bennn';
// Aggregations, tests :)
        $res[__LINE__]=fun::sql3("/* cleanest :)*/ select model_type as K1,model_id as K2,k as K3,v as K4 from rkv");
        $res[__LINE__]=fun::sql3("/* pas plusieurs id pour cela */select model_type as K1,model_id as K2,k as K3,v as K4,id from rkv");
        $res[__LINE__]=fun::sql3("select model_type as K1,model_id as K2,k as K3 from rkv");
        $res[__LINE__]=fun::sql3("select model_type as K1,id as K2 from rkv");
        $res[__LINE__]=fun::sql3("select id as K1 from rkv");

        $res[__LINE__]=fun::sql3("select model_type as K1,id from rkv /* K1 et restes */");

        $res[__LINE__]=fun::sql3("select model_type as K1,model_id as K2,id from rkv /* parfait: liste tous les identifiants */");
        $res[__LINE__]=fun::sql3("select model_type as K1,model_id as K2,group_concat(id) as ids,group_concat(v) from rkv group by model_type,model_id");





        die(''.$u2->id);//saves on shutdown
    }

    echo $u2MightBeMultipleResults[0];//__tostring
    $a=serialize($u2MightBeMultipleResults[0]);//__serialize
    echo $a;
    print_r(unserialize($a));
    echo json_encode($u2MightBeMultipleResults);
    die;

    $u=user::load(1);

    $a[]=
    $u->name=time();
    $mod=$u->save();



    $a[]=$u->save();
    $a[]=$u2->save();
    $a[]=user2::load(1);
    $u->hop=time();// Will trigger an error if
} catch (\throwable $___e) {
    $a['exception'] = $___e->getMessage();
}
die(fun::cleanHtml(json_encode($a)));?>


