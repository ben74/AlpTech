<?php
require_once 'vendor/autoload.php';

use Alptech\Wip\fun;
use Alptech\Wip\user;
use Alptech\Wip\user2;

try {
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
// ORM Test AND Stuff :)
    fun::$connection=['h'=>'127.0.0.1','u'=>'root','p'=>'b','db'=>'developer'];//$s['h'], $s['u'], $s['p'], $s['db'];
    fun::sql3("create table if not exists user(id   int auto_increment,name varchar(255) null,constraint id primary key (id))");
    fun::sql3("create table if not exists user2(id   int auto_increment,name varchar(255) null,constraint id primary key (id))")

    if('try once to check user is persisted upon shudown via __destroy model method'){
        $u=new user(['name'=>time()]);
        $u2 = new user2(['name'=>'1']);
        $u2->save();
        $u2->name='bennn';
        die(''.$u2->id);//saves on shutdown
    }
    $u2MightBeMultipleResults=user2::loadBy('name','bennn');
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


