<?php
namespace Alptech\Wip;

/*
usage:
php cli.php getConf all;#logCollectorSecret,logCollectorSeed,logCollectorUrl

pk=$(echo -n $logCollectorSecret`date +$logCollectorSeed` | md5sum | awk '{print $1}');echo $pk;
#php -r 'echo md5($argv[1].date(str_replace("%","",$argv[2])));' $logCollectorSecret $logCollectorSeed;

curl -sSLk -b "pk=$pk;XDEBUG_SESSION=1" -d '{"host":"'$host'","type":"php500","k":"k","v":"v","severity":9}' $logCollectorUrl

curl -sSLk -b "pk=$pk;output=js" -d '{"sql":"select k,k2,v from logs where id=1874"}' $logCollectorUrl
curl -sSLk -b "pk=$pk" -d '{"host":"hostName","type":"php500","k":"k","v":"v","severity":9}'
#repeat n time to get an email warning
curl -sSLk -b "apk=$pk" -d '{"host":"hostName","type":"metric","k":"diskio","v":"70"}'

fun::mail
fun::sql
*/

class logCollector extends abstractController
{
    static function index()
    {
        $ipok = fun::getConf('authorizedIps');

        if (!in_array($_SERVER['REMOTE_ADDR'], $ipok)) {
            file_put_contents(__file__ . '.badip.log', "\n" . date('Ymd His') . '--' . $_SERVER['REMOTE_ADDR'], 8);
            fun::_die('#ip:' . $_SERVER['REMOTE_ADDR']);
        }

        $expectedMd5 = md5(fun::getConf('logCollectorSecret') . date(str_replace('%', '', fun::getConf('logCollectorSeed'))));

        if (!$_COOKIE) {
            fun::_die('#' . __line__);
        }
        if (!isset($_COOKIE['pk'])) {
            fun::_die('#' . __line__);
        }
        if ($_COOKIE['pk'] != $expectedMd5) {
            print_r($_COOKIE);
            fun::_die('#' . __line__);
        }

        $output = 'json';
        if (isset($_COOKIE['output'])) {
            $output = $_COOKIE['output'];
        }
        if (isset($_ENV['phpinput'])) {
            $input = $_ENV['phpinput'];
        } else {
            $input = trim(file_get_contents('php://input'), "\n\r \0");
        }#could be regular postdata .. what for cli ?
        $json=io::isJson($input);
        if(!$json){
            fun::_die('#'.__line__);
        }
#todo: security : drop,delete,truncate
#todo: show sql error
#is straight sql request :)
        if (isset($json['sqlSingle'])) {
            $ret = fun::sql($json['sqlSingle']);
            if (!$ret) {
                fun::_die('#sql exception#' . __line__);
            }
            #die(json_encode(['results'=>$ret]));
            fun::_die(end(end($ret)));
        }
        if (isset($json['sql'])) {
            $ret = fun::sql($json['sql']);
            foreach ($ret as &$t) {
                if (in_array(substr($t['v'], 0, 1), ['[', '{']) and in_array(substr($t['v'], -1), [']', '}'])) {
                    $t['v'] = json_decode($t['v'], 1);
                }
            }
            unset($t);
#preg_replace("~\s+~is",' ',
#preg_replace("~\\\{2,})~is",'',
            if ($output == 'js') {
                echo json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                fun::_die();
            }

            if ($output == 'dkv2') {
                $output = 'print_r';
                $kv = [];
                foreach ($ret as $t) {
                    $kv[][$t['date'] . ',' . $t['k'] . ',' . $t['k2']] = $t['v'];
                }
                $ret = $kv;
            }
            if ($output == 'kv2') {
                $output = 'print_r';
                $kv = [];
                foreach ($ret as $t) {
                    $kv[][$t['k'] . ',' . $t['k2']] = $t['v'];
                }
                $ret = $kv;
            }
            if ($output == 'dkv') {
                $output = 'print_r';
                $kv = [];
                foreach ($ret as $t) {
                    $kv[][$t['date'] . ',' . $t['k']] = $t['v'];
                }
                $ret = $kv;
            }
            if ($output == 'kv') {
                $output = 'print_r';
                $kv = [];
                foreach ($ret as $t) {
                    $kv[$t['k']] = $t['v'];
                }
                $ret = $kv;
            }#fusionne les clés pour un seul enregistrement
            if ($output == 'print_r') {
                echo str_replace('~', "\n", print_r($ret, 1));
                fun::_die();
            }
            fun::_die(json_encode(['results' => $ret]));
        }

        /*}INSERT{*/

        $errors = $inserted = [];
        $tablesFields = explode(';', 'host;type;k;k2;k3;k4;k5;k6;k7;v;severity;ip;json');#authorisé upon write
#pouvant être multiples ..
        $ak = array_keys($json);
        if ($ak[0] === 0) {
            ;
        } else {
            $json = [0 => $json];
        }#is one line

#print_r($json);
        foreach ($json as $idx => $data) {
            $ak = array_keys($data);
            $diff = array_diff($ak, $tablesFields);
            if ($diff) {
                $errors[$idx] == __line__;
                continue;
                print_r($diff);
                echo "\n#" . __line__;
                continue;
            }
            if (strpos($data['k'], '£')) {#oui
                $k = explode('£', $data['k']);#from 0 to 6 ..
                #fun::_die(print_r($k));
                /*
                    [0] => php500
                    [1] => _shut
                    [2] => die.php
                    [3] => ERROR
                    [4] => 5
                    [5] => Using $this when not in object context
                    [6] => www.perron-rigot.com.home/die.php
                */

                foreach ($k as $i => $t) {
                    if ($i > 6) {
                        continue;
                    }#ignored
                    if (!$i) {
                        $data['k'] = $t;
                    }#0
                    else {
                        $data['k' . ($i + 1)] = $t;
                    }#1=>2,3,4,5
                    #fun::_die(print_r($data));
                    #0=>1
                }
            }

#$_SERVER['HTTP_X_FORWARDED_FOR']
            $data['ip'] = fun::ip2hostname($_SERVER['REMOTE_ADDR']);#length:36#2a01:e0a:2d7:fe0:59d5:d01c:3ba9:b6ac
            foreach ($data as &$v) {
                #if(in_array(gettype($v),['array','object']))$v=print_r($v,1);
                if (in_array(gettype($v), ['array', 'object'])) {
                    $v = json_encode($v);
                }
            }
            unset($v);

#a1=`date +%y%m%d`;b1=`date +%d%m%y`;curl -sSLk -b "a1=$a1;b1=$b1" -d '[{"type":"test","k":"diskio","v":"sda 0.01 0.48 0.47 18.24 9.37 129.75 14.87 0.06 3.43 1.15 3.49 1.02 72.91"}]'
#if($data['type']=='test' and $data['k']=='diskio' ){preg_match_all("~[0-9\.]+~",$data['v'],$m);echo end($m[0]);if(intval(end($m[0]))>50)echo"+50";print_r($m[0]);}

            if ($data['type'] == 'metric') {
#load avg 11.94 3.37 1.60 13/349 47961
                if ($data['k'] == 'loadavg' and preg_match_all("~[0-9\.]+~", $data['v'], $m) and intval($m[1]) > 10) {#sur la 5ème minute
                    fun::alertMail('alert:' . $data['host'] . ' load avg', 'load avg ' . $data['v']);

                } elseif ($data['k'] == 'dfsda2' and intval($data['v']) > 89) {#is usage, not free space
                    fun::alertMail('alert:' . $data['host'] . ' disk usage ', 'disk usage ' . $data['v']);
#a1=`date +%y%m%d`;b1=`date +%d%m%y`;curl -sSLk -b "a1=$a1;b1=$b1" -d '[{"type":"metric","k":"freeMem","v":"MemAvailable:   24736 kB"}]'
                } elseif ($data['k'] == 'freeMem' and preg_match("~[0-9]+~", $data['v'], $m) and intval($m[0]) < 500000) {#500m free
                    fun::alertMail('alert:' . $data['host'] . ' free mem ', 'free mem ' . $data['v'] . ' ' . print_r($m, 1));

                } elseif ($data['k'] == 'postqueue' and preg_match("~([0-9]+) Kbytes in ([0-9]+) Requests~", $data['v'], $m) and $m[2] > 10) {
                    fun::alertMail('alert:' . $data['host'] . ' postqueue ', 'postqueue: ' . $data['v']);

                } elseif ($data['k'] == 'cpuidle_avg' and intval($data['v']) < 5) {
                    fun::alertMail('alert:' . $data['host'] . ' cpuidle_avg ', 'cpuidle_avg: ' . $data['v']);

                    /*
                    a1=`date +%y%m%d`;b1=`date +%d%m%y`;    curl -s -S -L -k -b "a1=$a1;b1=$b1" -d '{"host":"ben","type":"metric","k":"diskio","v":60}'
                    */
                } elseif ($data['k'] == 'diskio' and preg_match_all("~[0-9\.]+~", $data['v'], $m)) {
                    $data['v'] = intval(end($m[0]));#last digit to int
                    if ($data['v'] > 50) {#echo"#".__line__;
                        $k = $data['host'] . ' diskio';
                        sql("insert into alerts values(null,'$k','" . $data['v'] . "',NOW())", $s['h'], $s['u'], $s['p'], $s['db']);
                        $nb = sql("select count(*) as nb,group_concat(v),group_concat(date) from alerts where k='$k' and date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)", $s['h'], $s['u'], $s['p'], $s['db']);
                        $nb = $nb[0];
                        if ($nb['nb'] > 5) {
                            fun::alertMail('alert:' . $data['host'] . ' diskio ', 'diskio: ' . $data['v'] . " " . $nb['nb'] . " times within 1 hour <pre>" . print_r($nb, 1));
                        }

                    }
                    #echo"alert";
                }
                /*
                insert into alerts(NULL,'k',NOW())
                mysql select on datetime less than 1 hour ago
                select count(*) as nb from alerts where k='' and date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)

                sda 0.01 0.48 0.47 18.24 9.37 129.75 14.87 0.06 3.43 1.15 3.49 1.02 72.91
                Device:         rrqm/s   wrqm/s     r/s     w/s    rkB/s    wkB/s avgrq-sz avgqu-sz   await r_await w_await  svctm  %util
                sda               0,68     3,35    1,09    4,27     6,03    66,20    26,93     0,01    1,98    6,77    0,75   2,33   1,25
                */
                #print_r($m);
                #postqueue		-- 3 Kbytes in 1 Request.
                #cpuidle_avg		88.6
                #apache443		66
                #apache80		6,
            }
            $sql = "insert into logs " . fun::insertValues($data);
            $id = fun::sql($sql);
            if (!$id) {
                $errors[$idx] = '#' . $sql;
            } else {
                $inserted[$idx] = $id;
            }
        }
        fun::_die(json_encode(compact('inserted', 'errors')));
#kein errors
        fun::_die();


        die($sql);
        print_r($json);
#reads row postdata


        $sql = "show tables in ";

        print_r($x);
    }
}

return; ?>


git status -uno
git ls-files -m;#modified files only
git status -uno;

timeout_var=5;cpuidle_avg=$(timeout ${timeout_var} vmstat 1 > /tmp/checkcpu.tmp; awk '$15~/[0-9]/ {print $15}' /tmp/checkcpu.tmp | awk -v t=$timeout_var '{sum+=$1} END {print sum / t}');echo $cpuidle_avg;#idle cpu < 20 alors alerte
ss -ant | grep :80 | wc -l;#current apache connections > seuil 40 : ddos ?
postqueue -p | tail -n 1;#postqueue
#df -h | grep /dev/sda2;#disk usage
df --output=pcent /dev/sda2 | tail -n 1
ps -eo pid,ppid,cmd,%mem,%cpu --sort=-%mem | head
ps -eo pid,ppid,cmd,%mem,%cpu --sort=-%cpu | head
cat /proc/meminfo | grep MemAvailable#centos
cat /proc/loadavg;#aVg load -> si premier chiffre > 20 alerte
mysql -e "show processlist;"


function cuj() { out='';xd='a=b';if [ "$4" == "1" ]; then xd='XDEBUG_SESSION=XDEBUG_ECLIPSE';fi;echo '';echo "1:$1,2:$2;3:$3,4:$4 => $xd;$out;PHPSESSID=$2;$5;";
} #$out }


ini_set('display_errors',1);
#$_POST['to'];$_POST['sub'];$_POST['body'];
if($_POST){
$s = "\r\n";
#if (strpos($head, 'text/html') === false) {
#$head .= "";#Content-type: text/html; charset=utf-8{$s}            iso-8859-1
$headers='From: '.$_POST['from'] . $s .'Reply-To: '.$_POST['from'].$s.'MIME-Version: 1.0'.$s;
echo mail($_POST['to'],$_POST['sub'],$_POST['body'],$headers);#could fail with html content in body ?
}
return;
#return;
#print_r($_POST);
#echo __file__.__line__;
}
