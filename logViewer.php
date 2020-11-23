<?php
namespace Alptech\wip;

class logViewer extends abstractController{
static function index(){

/*
https://d9.home/alptech.php?a=logViewer

delete from logs where type='metric' and DATEDIFF(NOW(),date)>1
SELECT count(*)as nb,max(date),host,k,k2,k3,k4,k5,k6,k7,v from logs where k='php500' group by k3,k5 limit 100

SELECT id,date,host,k,k2,k3,k4,k5,k6,k7,v from logs where left(k,6) in ('php500','phperr','erreur') order by id desc limit 100 -- grouped errors
a
*/
fun::nocache();
#tha,pr,prv6,benv6
#2a01:e0a:2d7:fe0:9c0e:8ed8:10e5:c8b3 ipv6 home
$ipok = fun::getConf('authorizedIps');
if (!in_array($_SERVER['REMOTE_ADDR'], $ipok)) {
    die('#ip:' . $_SERVER['REMOTE_ADDR']);
}

$k = 'json';
if (isset($_POST[$k]) and $_POST[$k]) {
    $_COOKIE[$k] = $v = $_POST[$k];
    setCookie($k, $v, strtotime('+10 years'), '/');
}
$k = 'sql';
if (isset($_POST[$k]) and $_POST[$k]) {
    $_COOKIE[$k] = $v = $_POST[$k];
    setCookie($k, $v, strtotime('+10 years'), '/');
}

if (!isset($_COOKIE['sql'])) {
    $_COOKIE['sql'] = $v = "SELECT * FROM logs where type='debug' order by id desc";
    setCookie('sql', $v, strtotime('+10 years'), '/');
}
if (!isset($_COOKIE['json'])) {
    $_COOKIE['json'] = $v = '';
    setCookie('json', $v, strtotime('+10 years'), '/');
}
#require_once '../fun.php';
fun::sql("delete from logs where type='metric' and DATEDIFF(NOW(),date)>2");#

if (!stripos($_COOKIE['sql'], ' limit ')) {
    $_COOKIE['sql'] = trim($_COOKIE['sql']) . ' limit 100';
}
$_COOKIE['sql'] = trim($_COOKIE['sql']);

$res = fun::sql($_COOKIE['sql']);
$history = '';
$f = __file__ . '.log';
if (is_file($f)) {
    $history = file_get_contents($f);
}
if ($res and $_COOKIE['sql'] and strpos($history, $_COOKIE['sql']) === false) {
    file_put_contents($f, "\n" . trim($_COOKIE['sql']), 8);
    $history .= "\n" . $_COOKIE['sql'];
}
?>
<html>
<head>
    <style>
        html, body, textarea, input, a {
            background: #000;
            color: #0F0;
            font: 16px Assistant;
        }

        table {
            border-collapse: collapse;
        }

        .wide {
            min-height: 10vh;
            width: 100%;
            min-width: 100%;
            max-width: 50vw;
        }

        input[type=submit] {
            cursor: pointer;
        }

        td.v, .w100, table, textarea, input[type=submit] {
            width: 100%
        }

        tbody td {
            word-break: break-word;
        }

        td {
            width: 7%
        }
    </style>
    <div style='position:fixed;right:0;'><a href='#top'>top</a> - <a href='#bot'>bot</a></div>
    <?php echo "</head><body><form method=post id=top><textarea spellcheck=false style='width:100%' name='sql'>" . $_COOKIE['sql'] . "</textarea><input class=w100 name=json placeholder='json unpack keys' value=\"" . $_COOKIE['json'] . "\"><input type=submit accesskey=s></form>";
    if ($res) {
        echo "<table border=1><thead><tr>";
        $ak = array_keys($res[0]);
        foreach ($ak as $v) {
            echo "<td>$v</td>";
        }
        echo "</tr></thead><tbody>";
        foreach ($res as $t) {
            echo "<tr>";
            foreach ($t as $k => $v) {
                $json = false;
                $s = ' class=' . $k;
                if (in_array($k, ['k', 'k2'])) {
                    $v = str_replace('/', ' ', $v);
                } elseif (in_array($k, ['v', 'json']) and $v and $t['type'] != 'metric') {
                    if (in_array(substr($v, 0, 1), ['[', '{']) and in_array(substr($v, -1), [']', '}']) and $json = @json_decode($v, 1)) {
                        ;
                    }
                    if ($json and $_COOKIE['json']) {
                        $ret = [];
                        $j = explode(',', $_COOKIE['json']);
                        foreach ($j as $k2) {
                            if (isset($json[$k2])) {
                                $ret[$k2] = $json[$k2];
                            }
                        }
                        if ($ret) {
                            $v = json_encode($ret);
                        }
                    }
                    $v = "<textarea spellcheck=false class=wide>$v</textarea>";
                }
                echo "<td$s>$v</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    }
    ?>
    <hr>
    <pre id=bot><? echo $history;
    fun::_die();#no more processing or output :)
        return;
        }

        }
        return; ?>
$ret=sql($json['sqlSingle'],$s['h'],$s['u'],$s['p'],$s['db']);

