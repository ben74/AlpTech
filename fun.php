<?php
/*
 * $_ENV['conf']['nostats']=1;//reduces memory usage : no mysql results keps
 */
namespace Alptech\Wip;

class fun /* extends base */
{
    static $connection, $ext, $h, $u, $uq, $dr, $q, $ip, $local, $env, $t = 0, $data = [],$conf = [], $args = [],$_shared = [], $quotes=["'",'"'], $unquotes=["′",'″'];

    static function conf($x=null){// fun::conf(['a'=>1]);
        if(!$x)return;
        elseif(is_array($x)){
            static::$data=array_merge(static::$data,$x);
        }elseif(isset(static::$data[$x])){
            return static::$data[$x];
        }
    }

    static function breakpoint($x=null)
    {
        $args = func_get_args();
        $breakpoint = 'here -- usefull when using xDebug';
    }

    static function main($setData = null)
    {
        if ($setData) static::conf($setData);
        return __FILE__ . __LINE__;#
    }

    static function blockMaliciousRequests(){// Alias of
        return static::firewall();
    }

    static function firewall($url = null, $rawBody = null, $req = null, $lp = null, $files = null)
    {
        if (!$lp) {
            $lp = static::getConf('logs');
        }
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
            if (preg_match('~accesson.php~i', $url, $m)) {
                return 'injection pattern ' . $m[0] . ' in url ' . $url;
            }#and querystring}
        }
        if (!$rawBody) {
            $data = static::getBody();
        }
        if (!$req and $_REQUEST) {
            $req = $_REQUEST;
        }
        if (!$files and $_FILES) {
            $files = $_FILES;
        }
        if ($url) {
            $x = static::injectionPattern($url);#check the uri along with query string .. avoiding injection via rewriting within where like requests ..
            if ($x) {
                return 'injection pattern ' . $x . ' in url ' . $url;#and querystring
            }
        }

        if ($rawBody) {
            $x = static::injectionPattern($rawBody, 'rawbody');#check the uri alondg with query string
            if ($x) {
                return 'injection pattern ' . $x . ' in rawBody';
            }
        }

        if ($req && 'query string parameters goes here ..') {
            foreach ($req as $k => $v) {
                if (in_array($k, ['contacts_societe', 'contacts_message'])) {
                    continue;
                }#skip those
                $x = static::injectionPattern($v);
                if ($x) {
                    return 'injection pattern k:' . $x . ' in ' . $v;
                }
                $x = static::injectionPattern($k);
                if ($x) {
                    return 'injection pattern v:' . $x . ' in ' . $k;
                }
            }
        }

        if (isset($files) and $files) {
            $json = json_encode($files);
            if (preg_match('~"name":"[^\"]+\.php[^\"]*"~i', $json, $m)) {#way much more simpler but wont work for more complex ..
                return 'file upload: ' . $m[0];
            }
            if (preg_match('~":"[^\"]+\.php[^\"]*"~i', $json, $m)) {
                return 'complex nested file upload: ' . $m[0];
            }/*
            $foundUploads = static::searchInArrayDepths($files, ['name'], '~\.php~');
            if ($foundUploads) {
                return 'deep file upload: ' . json_encode($foundUploads);
            }*/
        }
        return false;#clear :)
    }

    static function injectionPattern($x, $type = '')
    {
        /* recursive returns first positive match */
        if (is_array($x)) {
            foreach ($x as $v) {
                $res = static::injectionPattern($v);
                if ($res && 'returns first found') {
                    return $res;
                }
            }
            return false;
        }

        /* most common possible injection patterns '--', '||',  'grant ','create ',  */
        $sqlInjectionPatterns = ['sleep(', 'GET_HOST_NAME', 'drop ', 'truncate ', ' delete ', 'cast(', 'ascii(', 'char(', '<script', '<ifram', '<img'];
        if ($type != 'rawbody') {
            $sqlInjectionPatterns += ['/*', '*/', '@@',];
        }#plupl
        foreach ($sqlInjectionPatterns as $v) {
            if (stripos($x, $v) !== false) {
                return $v;
            }
        }

        $m = [];
        if (Preg_Match("~' *or|\" *or|or *1 *= *1|union *all~i", $x, $m) && !Preg_Match("~[l|d]' *or~i", $x, $m) && 'pas anodin ..') {
            return $m[0];
        }

        if (Preg_Match("~url\(|data:image|/png;|base64,|option=com_xmap&view=xml&tmpl=component~i", $x, $m)) {
            return $m[0];
        }
        if (Preg_Match("~_users|\~root|print-439573653|/RK=|/RS=|concat\(|0x3a,password,usertype\)|http://http://|\*!union\*|plugin=imgmanager|w00tw00t|zologize/axa|HNAP1/|admin/file_manager|%63%67%69%2D%62%69%6E|%70%68%70?%2D%64+|cash+loans+|webdav/|cgi-bin|php?-d|union%20all%20select|convert%28int%2C~i", $x, $m)) {
            return $m[0];
        }
        $phps = explode(';;', '<?php ;;$_SERVER[\'DOCUMENT_ROOT\'];;accesson.php');
        foreach ($phps as $v) {
            if (stripos($x, $v) !== false) {
                return $v;
            }
        }
        return;#nothing found
    }

    static function _die($x = null)
    {
        $_ENV['die'] = 1;
        static::gt('_die, breakpoint here is a good idea');#caught at shutdown function, is neat :)
        if ($x && in_array(gettype($x), ['array', 'object'])) {
            print_r($x);
        } elseif ($x) {
            echo $x;
        }
        #$e=error_get_last();
        die;#launch gc then shutdown functions at end
    }

    static function r404($x = '', $y = '')
    {
        if ($y) null;
        header('HTTP/1.0 404 Not Found', 1, 404);
        static::_die('/* <a href="/">not found : ' . trim($x, ' */') . ' </a><script>location.href="/#' . str_replace('"', '', $x) . '";</script>*/');
    }

    static function hl($a = '', $b = true, $c = null)
    {
        \header($a, $b, $c);
    }

    static function r302($x = '', $virtual = 0)
    {
        if ($virtual) {
            return "r302::$x";
        }
        static::hl('Location: ' . $x, 1, 302);
        static::_die();
    }

    static function dbm($x, $sub = null, $f = null)
    {// todo:if config set send debug to url ....
        if ($f) null;
        if (!static::getConf('sendLogs') || !static::getConf('logCollectorUrl')) {
            return;
        }
        #return;
        if (0 and (DEV or LOCAL)) {
            return;
        }#$a=1;DEVBREAKPOINT
        $bt = static::bt(1);
        if (!$sub) {
            $sub = $_ENV['h'] . ' debug';
        }

        $json = ['host' => static::getConf('host'), 'type' => 'debug', 'k' => $sub, 'k2' => $_ENV['h'] . $_ENV['u'], 'v' => $x];
        $pk = md5(static::getConf('logCollectorSecret') . date(str_replace("%", "", static::getConf('logCollectorSeed'))));
        $headers = ["Cookie: pk=" . $pk . ';XDEBUG_SESSION=1'];
        $url = static::getConf('logCollectorUrl');
        $opt = [
            10015 => json_encode($json),#post payload
            10023 => $headers,#all headers as one array, sets
            10002 => $url,
            10036 => 'POST',
            19913 => 1,
            42 => 1,
            45 => false,
            81 => false,
            64 => false,
            13 => 10,
            78 => 10,#timeout
            52 => 1, #redir
            2 => 1,
            41 => 1,
            58 => 1,  #?? Follow Return Headers
        ];
        $_sent = static::cuo($opt);
        return;

        $opt = $headers = [];
        $from = 'dx24.fr>';
        $to = 'dx24.fr';
        $post = [
            'from' => $from,
            'to' => $to,
            'sub' => $sub,
            'body' => '<pre>' . $sub . ' -- ' . date('YmdHis') . ' ' . $_ENV['h'] . $_ENV['u'] . "  {\n" . print_r(
                    [
                        'x' => $x/*Rhtmlspecialchars($x)*/,
                        'bt' => $bt,
                        'host' =>
                            $_ENV['h'],
                        'post' => $_POST,
                        'files' => $_FILES,
                        'get' => $_GET,
                        'cook' => $_COOKIE,
                        'ip' => $_ENV['IP']
                    ],
                    10
                )
        ];
        $_sent = static::cup($url, $opt, $post, $headers, 1);
        return;
        /*
        foreach($_ENV['debugMails'] as $mail){
            wmail($mail, $sub, '<pre>' . $sub . ' -- ' . date('YmdHis') . ' ' . $_ENV['h'].$_ENV['u'] . "  {\n" . print_r(compact('x', 'bt') + ['host' => $_ENV['h'], 'post' => $_POST, 'get' => $_GET, 'cook' => $_COOKIE, 'ip' => $_ENV['IP']], 1));
        }*/
        static::db($x, $f);
    }

    static function db($x, $f = null)
    {
        if (!$f) {
            $f = ini_get('error_log');
        }
        if (strpos($f, $_ENV['lp']) === false) {#anom.log
            $f = $_ENV['lp'] . $f;
        }
        $bt = static::bt(1);
        io::fpc($f, "\n\n}" . date('YmdHis') . ' ' . $_ENV['h'] . '/' . $_ENV['u'] . "{" . print_r(compact('x', 'bt'), 1) . json_encode(array_filter(['post' => $_POST, 'get' => $_GET, 'cook' => $_COOKIE, 'ip' => $_ENV['IP']]), 1) . "\n\n", 8);
    }

    static function arrayContains($array, $contains = 0, $lv = 0, $bk = [])
    {
        $found = [];
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $found = array_merge($found, static::arrayContains($v, $contains, $lv + 1, array_merge($bk, [$k])));
            } elseif (preg_match($contains, $v)) {
                #_die(["found:$k"=>$v]);
                $found[] = [$k => $v];
            }
        }
        return $found;
    }

    static function searchInArrayDepths($array, $keys = 0, $contains = 0, $lv = 0, $bk = ['root'])
    {
        if (!$keys) {
            $keys = explode(',', 'name,tmp_name');
        }
        $c = count($keys);
        $matching = 0;
        #if($lv==1)_die(compact('bk','array'));
        foreach ($keys as $key) {
            if (isset($array[$key])) {
                if (is_array($array[$key]) and $contains) {
                    $c1 = count(static::arrayContains($array[$key], $contains, 0, $key));
                    #_die($key.$contains.$c1);found twice
                    #echo $c1;
                    $matching += $c1;
                } elseif ($contains) {
                    if (preg_match($contains, $array[$key])) {
                        $matching++;
                    }
                } else {
                    $matching++;
                }
            }
        }
        if ($matching >= $c) {
            #_die("ok:$matching $c");
            return $array;
        }
        $found = [];
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $e = static::searchInArrayDepths($v, $keys, $contains, $lv + 1, array_merge($bk, [$k]));#search deeper
                if ($e) {
                    $found = array_merge($found, $e);
                    #_die("found::".$found);
                }
            }
        }
        return $found;
    }

    /** CURL multi exec or Guzzle::futures */
    static function cme($options = [], $defaults = [], $maxParallel = 10, $laterFileFeed = null)
    {
        static $distributed = [], $mh, $sep = '£$€', $files = [], $delayed = [], $res = [], $curlActiveConnexions = [], $fh = false, $parallel = 0, $readenBytes = 0, $readBufferLength = 4096;
        if (!$mh) {
            $mh = \curl_multi_init();
        }
        if ($laterFileFeed) {
            $fh = fopen($laterFileFeed, 'r');
        }

        if ('FEED' and $options) {
            foreach ($options as $opts) {
                if ($parallel >= $maxParallel) {
                    $delayed[] = $opts;
                }
                $c = static::addCMEHandle($mh, $opts, $defaults, $files, $curlReq, $curlActiveConnexions);
                $parallel++;
            }
        }

        if ('LAUNCH') {
            $active = null;
            do {
                $mrc = \curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);//lancer
        }

        if ('PROCESSING THEM ALL') {
            //$slept = $ko = $ok = 0;
            $cycle1Running = 1;
            while ($cycle1Running and $curlReq or ($active && $mrc == CURLM_OK)) {// nécessaire au départ
                do {
                    $mrc = \curl_multi_exec($mh, $active);
                    $ir = \curl_multi_info_read($mh);
                    if ($ir) {
                        //ir['msg']=1, ir['result']=3
                        $ha = $ir['handle'];
                        $inc = (int) $ha;
                        $opts = $curlReq[$inc];
                        $i = \curl_getinfo($ha);
                        //$k = (int)($i['http_code']);$l = (int)($i['download_content_length']);
                        $newJob = false;
                        $___u = $opts[CURLOPT_URL];

                        if ('HEAD request returns info' && isset($opts[CURLOPT_NOBODY]) && $opts[CURLOPT_NOBODY]) {
                            //$isHead = 1;
                        } elseif (isset($opts['FILE'])) {// le fichier est téléchargé::CURLOPT_BUFFERSIZE
                            //CURLOPT_FILE => $fileHandles[$inc2],
                            //$__r = \curl_multi_getcontent($curlActiveConnexions[$inc]);
                            fclose($files[$inc]);
                            //$fs = filesize($opts['FILE']);//$fileHandles[$inc]$a = 1;
                        } elseif ('isSimpleGetContents') {
                            $i = curl_multi_getcontent($ha);// Header+contents
                        }
                        $k = $inc . ':' . $___u;
                        if (isset($opts['id'])) {
                            $k = $opts['id'];
                        }
                        $res[$k] = $i;

                        if (isset($opts['cb'])) {
                            $newJob = $opts['cb']($i);
                        }

                        if ($newJob) {
                            $delayed[] = $newJob;
                        }

                        \curl_multi_remove_handle($mh, $curlActiveConnexions[$inc]);
                        unset($curlReq[$inc], $curlActiveConnexions[$inc]);
                        $parallel--;
                    } else {
                        $rienAlirePourLinstantAutreCycleDeWait++;
                        // Et s'il y avait de nouvelles choses à injecter là dedans .. lecture d'un websocket, d'un redis, d'un rabbit ?
                    }
// de toutes façons ..
                    if ($parallel < $maxParallel && 'si une place libérée') {
                        if ($fh) {//file for watching
                            $fs = filesize($laterFileFeed);
                            if ($fs > $readenBytes) {
                                $rbuf = '';
                                while (($readen = fread($fh, $readBufferLength)) && $readen) {
                                    $rbuf .= $readen;
                                }
                                $readenBytes = $fs;
                                if ($rbuf) {// peut être nul, rien de rajouté dans ce fichier
                                    $newJobs = explode($sep, $rbuf);
                                    foreach ($newJobs as $t) {
                                        $delayed[] = json_decode($t, true);
                                    }
                                    $rbuf = '';
                                }
                            }
                        }
                        if ($delayed) {
                            $opts = array_shift($delayed);
                            $c = static::addCMEHandle($mh, $opts, $defaults, $files, $curlReq, $curlActiveConnexions);
                            $parallel++;
                        }
                    }
                } while ($cycle1Running and $curlReq and $mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }
        if ($fh) {
            fclose($fh);
        }
        \curl_multi_close($mh);// end of processing : get the ability of being feeded asynchronously ( via a file, jsonOptions exploded by £$€, for example )
        return $res;
    }

    /**
     * @param $feeder
     * @param $onResponse
     * @param $onFailure
     * @param $parallelRequests
     * @param $eternalLoop : in case of consuming some queue or sql results, there's no given end of this command, just avoid memory leaks or kill the worker on feeder when no more pendingResults
     * @param $onRequeues : on increasing 503 responses toward an host might consider to delay some call or introduce usleep() in between
     * @return array
     */
    static function cme2($feeder, $onResponse, $onFailure, $parallelRequests = 20, $eternalLoop = true, $onRequeues = false)
    {
        $usleep = 100000;// 100 milliseconds in order to avoid cpu burn
        $codeForRequeues = [502, 503];

        $delayed = $queue = $data = $pending = $options = $files = $urls = [];
        $ret = ['started' => microtime(true), 'tot' => 0, 'slept' => 0, 'rq' => 0, 'results' => []];
        $finished = false;
        $mh = \curl_multi_init();
        while ($pending || !$finished) {
            \curl_multi_exec($mh, $active);
            $ir = \curl_multi_info_read($mh);
            if ($ir && 'onResult =>') {//$ir['msg']=1 && ir['result']=0;
                $tries++;
                $ha = $ir['handle'];
                $reqNumber = (int) $ha;
                $opts = $options[$reqNumber];
                $i = \curl_getinfo($ha);
                //echo"\n".$reqNumber;
                if ('HEAD request returns info' && isset($opts[CURLOPT_NOBODY]) && $opts[CURLOPT_NOBODY]) {
                    $i['body'] = null;
                } elseif (isset($opts['FILE'])) {
                    fclose($files[$reqNumber]);
                } elseif ('isSimpleGetContents on get request') {
                    $c=\curl_multi_getcontent($ha);
                    $i['body'] = \substr($c,$i['header_size']);// Header + contents`
                }
                \curl_multi_remove_handle($mh, $pending[$reqNumber]);
                unset($pending[$reqNumber]);

                $rc = (int)$i['http_code'];
                //$cl = (int)$i['download_content_length'];
                $i['url2']=$url2 = $urls[$reqNumber];// $i['url']
                $ret['results'][$url2 . '#' . $reqNumber] = $rc . ',' . $i['body'];
                //echo':';
                if (in_array($rc, $codeForRequeues)) {// Requeues 502,503
                    if($onRequeues)$onRequeues($i);// sleeping and temporising requests ?
                    $ret['rq']++;//echo'£';
                    $co = $options[$reqNumber];
                    $data1 = $data[$reqNumber];
                    unset($options[$reqNumber], $data[$reqNumber], $urls[$reqNumber]);

                    $c = \curl_init();
                    $reqNumber = (int) $c;
                    $data[$reqNumber] = $data1;
                    $urls[$reqNumber] = $url2;
                    $options[$reqNumber] = $co;
                    $pending[$reqNumber] = $c;
                    \curl_setopt_array($c, $co);
                    \curl_multi_add_handle($mh, $c);
                    continue;
                }

                unset($options[$reqNumber]);

                if ($rc < 200 || $rc > 503 || strlen($rc) > 4) {
                    $onFailure($i, $data[$reqNumber]);
                    $ret[$opts[CURLOPT_URL]] = json_encode($i);echo';';
                    continue;
                }

                $newJob = $onResponse($i, $data[$reqNumber], count($pending), $ret);
                if ($newJob) {
                    $delayed = array_merge($delayed, $newJob);
                    $finished = false;
                    //echo"--".$i['body'].'->'.json_encode($newJob);
                }
                unset($urls[$reqNumber], $data[$reqNumber]);
                //echo'=>'.count($pending).','.count($delayed);
            }

            $feeded = 0;
            $emptyFeeder = false;
            // Can continue feeding the Beast with Requests
            while (!isset(static::$data['stopCurlMultiExec']) && !$finished && (count($pending) < $parallelRequests) && ( $delayed || !$emptyFeeder) ) {
                if ($delayed) {
                    $queue[] = array_shift($delayed);//echo'a';
                }

                while ('2 : No More Things to feed here' && !$queue && !$emptyFeeder) {
                    $err = 0;
                    $ok = false;
                    while (!$ok && $err < 5) {// Feeder tries n times if cas of exception
                        try {
                            $queue = $feeder(count($pending), $iteration);// Might return : empty on end condition reached
                            if (!$queue) $emptyFeeder = true;
                            $ok = true;
                        } catch (\Throwable $e) {
                            $err++;
                            echo"\n".substr($e->getMessage(), 0, 120) . ',';
                        }
                    }
                    $iteration++;
                    $ret['tot'] += count($queue);
                }

                if (!$queue){
                    if(!$eternalLoop) {
                        $finished = true;
                        if (!$pending) {
                            $ret['break:']=__line__;
                            continue 2;// break 2 :: is truly finished
                        }
                    }
                    $ret[ __line__]++;
                    continue;// still pending things, continue loop 1
                }

                $t = array_shift($queue);
                $feeded++;
                $c = \curl_init();
                $reqNumber = (int) $c;
                $pending[$reqNumber] = $c;
                $data[$reqNumber] = $t['data'] ?? [];
                $options[$reqNumber] = $t['options'];
                $urls[$reqNumber] = $t['options'][CURLOPT_URL];
                \curl_setopt_array($c, $t['options']);
                \curl_multi_add_handle($mh, $c);
            }

            if (!$ir and !$feeded and count($pending) and 'no results :: awaiting, even one microstep when finished') {
                $ret['slept']++;//echo'.'.count($pending);
                usleep($usleep);
            }
        }

        $ret['delayed'] = count($delayed);
        $ret['time'] = round(microtime(true) - $ret['started'], 4);
        unset($ret['started']);
        return $ret;
    }

    static function addCMEHandle($mh, $opts, $defaults, &$files, &$curlReq, &$curlActiveConnexions)
    {
        $c = \curl_init();
        $inc = (int) $c;// sont recyclées une fois libérées
        $curlActiveConnexions[$inc] = $c;

        $opts = $opts + $defaults;
        $curlReq[$inc] = $opts;

        if (isset($opts['cb'])) unset($opts['cb']);
        if (isset($opts['id'])) unset($opts['id']);
        if (isset($opts['FILE'])) {
            $opts[CURLOPT_FILE] = $files[$inc] = fopen($opts['FILE'], 'w+');
            unset($opts['FILE']);
        }
        try {
            \curl_setopt_array($c, $opts);
        } catch (\throwable $e) {
            $a = 1;
            throw $e;
        }
        \curl_multi_add_handle($mh, $c);
        return $c;
    }

    static function curlFile($url, $file, $name = '', $headers = [], $timeout = 999)
    {
        if (is_array($url)) extract($url);
        if (!$name) {
            $name = basename($file);
        }#enctype : multipoart
        #$files=['file' => '@' . realpath($file).';filename='.$name];#does not sends files
        $files = ['file' => curl_file_create($file, '.jpg', $name)];#gives : error: operation aborted by callback
        return static::cup(['url' => $url, 'post' => $files, 'headers' => $headers + ['content-type: multipart/form-data'], 'timeout' => $timeout]);
    }

    static function cup($url, $opt = [], $post = [], $headers = [], $timeout = 30, $unsecure = 1, $forcePort = 0, $follow = 1)
    {
        if (is_array($url)) {
            extract($url);
        }
        $url = str_replace(' ', '%20', $url);#no urlencode ..
        $ch = \curl_init();
        $headers[] = 'Expect:';/*100 header*/
        if (isset($opt[CURLOPT_URL]) and $opt[CURLOPT_URL]) {
            $url = $opt[CURLOPT_URL];
        }
        $opts = [CURLOPT_URL => $url, CURLOPT_HEADER => 1, CURLINFO_HEADER_OUT => 1, CURLOPT_VERBOSE => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_AUTOREFERER => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_HTTPHEADER => $headers];
        if ($follow) {
            $opts += [CURLOPT_FOLLOWLOCATION => $follow];
        }#
        if ($unsecure) {
            $opts += [CURLOPT_SSL_VERIFYHOST => false, CURLOPT_SSL_VERIFYPEER => false];
        }
        if ($forcePort) {
            $opts += [CURLOPT_PORT => strpos($url, 'ttps:/') ? 443 : 80];
        }

        foreach ($opt as $k => $v) {
            $opts[$k] = $v;
        }#par dessus les options par défaut
        #$opts[CURLOPT_HTTPHEADER][] = 'Expect:';#in case of 100 continue soft "error"
        if ($post) {
            $opts[CURLOPT_POST] = 1;
            $opts[CURLOPT_POSTFIELDS] = $post;#$url2Callback[$url]['post']
        }
        // 3swift:138:11749914»»  {"type":2,"message":"curl_setopt_array(): supplied resource is not a valid File-Handle resource","file":"\/var\/www\/html\/vendor\/alptech\/wip\/fun.php","line":481}
        \curl_setopt_array($ch, $opts);
        $result = \curl_exec($ch);
        $info = \curl_getinfo($ch);
        $error = \curl_error($ch);
        \curl_close($ch);
        $header = substr($result, 0, $info['header_size']);
        $contents = substr($result, $info['header_size']);
        return compact('contents', 'header', 'info', 'error', 'opts');
    }

    static function cuj($url, $jsonPayload){
        return static::cup($url, [], $jsonPayload, ['Content-Type: application/json']);
    }

    static function fDl($distant, $local)
    {
        $fh = fopen($local, 'w');
        $co = [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_NOPROGRESS => true, CURLOPT_BUFFERSIZE => CURL_MAX_READ_SIZE];#
        $co[CURLOPT_HEADER] = $co[CURLINFO_HEADER_OUT] = $co[CURLOPT_VERBOSE] = false;
        $res = static::cup($distant, $co, [], [], 999, 1);
        \fclose($fh);
        $fh = null;
        return $res;
    }

    static function cuo($opts)
    {
        $curl = \curl_init();
        \curl_setopt_array($curl, $opts);
        $result = \curl_exec($curl);
        $info = \curl_getinfo($curl);
        $error = \curl_error($curl);
        \curl_close($curl);
        $header = substr($result, 0, $info['header_size']);
        $contents = trim(substr($result, $info['header_size']));
        return compact('contents', 'info', 'header', 'error');#$a=1;
    }

    static function gt($x = null)
    {
    }

    static function bt($x = null)
    {
        if ($x) null;
        return debug_backtrace(2);
    }

    static function getConf($k = null)
    {
        if (!static::$conf) {
            #if (!isset($_ENV['alpTechConf'])) {
            $conf = [];
            if (1 and isset($GLOBALS['argv'])/* and!isset($_SERVER['DOCUMENT_ROOT'])*/) {
                $_SERVER['DOCUMENT_ROOT'] = __DIR__ . '/../../app/';
                $_SERVER['HTTP_HOST'] = 'superwebsite.com';
                $_SERVER['REQUEST_SCHEME'] = 'https';

                $f = __DIR__ . '/cli.conf.php';
                if (!is_file($f)) {
                    copy(__DIR__ . '/default.cli.conf.php', $f);#is setup
                }
                $conf = require_once $f;
                if ($conf['cliHost']) $_SERVER['HTTP_HOST'] = $conf['cliHost'];
                if ($conf['cliDocRoot']) $_SERVER['DOCUMENT_ROOT'] = $conf['cliDocRoot'];
            }

            $f = __DIR__ . '/conf.php';
            if (!is_file($f)) {
                copy(__DIR__ . '/default.conf.php', $f);#is setup
            }
            $new = require_once $f;
            $conf += $new;
            static::setStatic('conf', $conf);
        }

        if (!$k) {
            return static::$conf;
        }
        if (!isset(static::$conf[$k])) {
            return null;
        }
        return static::$conf[$k];
    }

    static function tryAlptechRoutes($url = '', $virtual = 0)
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $isThumbnailRoute = static::isThumbnailRoute($url, $virtual);
        if ($isThumbnailRoute) {
            return $isThumbnailRoute;
        }
    }

    static function getExtension($url = '')
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        if (strpos($url, '?')) {
            $url = explode('?', $url);
            $url = reset($url);
        }
        $ext = explode('.', $url);

        if (!count($ext)) {
            $ext = '';
        } else {
            $ext = strtolower(end($ext));
        }
        return $ext;
    }

    static function isMedia($url = '')
    {
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
        $mediaTypes = explode(',', static::getConf('mediaTypes'));
        $ext = static::getExtension($url);
        $res = in_array($ext, $mediaTypes);
        return $res;
    }


    static function isThumbnailRoute($url = '', $virtual = 0)
    {
        spark::init();
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
        }
#is 404 yet becuz not set on the server
        $s = static::getConf('pathSeparator');
        $url = static::firstBefore($url, ['?', '#']);
        if (static::isMedia($url) and strpos($url, $s) and preg_match('~' . static::getConf('thumbnailsDir') . '(.*)~i', $url, $m)) {
#purge querystring, then hashtag
            $filepath = $m[1];
            $target = $_ENV['dr'] . ltrim($url, '/');

            $width = $height = null;#final public path, is expected target for thumbnail

            preg_match('~' . $s . 'w([0-9]+)~', $m[1], $w);
            preg_match('~' . $s . 'h([0-9]+)~', $m[1], $h);

            if ($w && $w[1] && (int)$w[1]) {
                if (!in_array((int)$w[1], static::getConf('thumbAuthorizedWidths'))) {
                    null;#return;
                }
                $filepath = str_replace($w[0], '', $filepath);#strip out parameter
                $width = (int)$w[1];
            }
            if ($h && $h[1] && (int)$h[1]) {
                if (!in_array((int)$w[1], static::getConf('thumbAuthorizedHeights'))) {
                    null;#return;
                }
                $filepath = str_replace($h[0], '', $filepath);#strip out
                $height = (int)$h[1];
            }

            if (!$width and !$height) {
                return static::r302('/' . $url . '#original picture : as no width, nor height specified', $virtual);
            }

            $originalFile = ltrim(str_replace($s, '/', $filepath), '/');
            $finalExt = static::getExtension($originalFile);

            $opaths = array_filter(array_unique([$originalFile, str_replace('.webp', '', $originalFile)]));#trick is to append .webp at the end of the path ..
            foreach ($opaths as $opath) {
                $opath = $_ENV['dr'] . $opath;
                if (is_file($opath)) {#générer la thumb ici - once and for all !!
                    #$b = thumbnail($opath, $width, $height);
                    try {
                        $b = static::resizeImage(['ext2' => $finalExt, 'filename' => $opath], $width, $height, $target);
                        if ($b) {
                            return static::r302($url . '#?generated=' . date('YmdHis') . '#', $virtual);
                        }
                        $a = 1;
                    } catch (\Exception $_e) {
                        $a = 1;
                    }
#static public function stream($image_name, $width, $height, $format=null)
                }
            }
            #sinon 404
            #die('/*'.is_file($opath).$b.'*/');
            return static::r404m($virtual);
        }
        return;
        #return static::thumbnailFileName($file, 0, 0);
    }

    static function firstBefore($x, $s = ['?', '#'])
    {
        if (!is_array($s)) {
            $s = [$s];
        }
        foreach ($s as $separator) {
            if (strpos($x, $separator)) {
                $x = explode($separator, $x);
                return reset($x);
            }
        }
        return $x;
    }

    static function lastOf($x, $s = ['?', '#'])
    {
        if (!is_array($s)) {
            $s = [$s];
        }
        foreach ($s as $separator) {
            if (strpos($x, $separator)) {
                $x = explode($separator, $x);
                return end($x);
            }
        }
        return $x;
    }

    static function r404m($virtual = 0)
    {
        if ($virtual) {
            return __function__ . '/' . static::getConf('defaultImage');
        }
        header('Content-type: image/png');
        header('HTTP/1.0 404 Not Found', 1, 404);
        readfile(static::getConf('defaultImage'));
        static::_die();
        #static::die("/*$x*/");
    }

#static::resizeImage(['ext2'=>'webp','filename'=>])..
    static function resizeImage($filename, $w = null, $h = null, $target = null)
    {
        #was thumbgen::main(compact('filename','target','h','w'));#list($cuwidth, $cuheight) = getimagesize($filename);
        #global $debug;

        $quality = 70;#jpeg
        $pngq = 9; //0 : no compression, 9 :best
        $srcx = $srcy = $ext2 = null;
        //$posx = $posy = $ext = $oheight = $owidth = $ts = null;

        #if(c('CLI'))print_r($params);
        if (is_array($filename)) {
            extract($filename);
        }
        $s = '#';
        if (strpos($filename, $s)) {
            $filename = explode($s, $filename);
            $filename = reset($filename);
        }
        if (!is_file($filename)) {
            throw new \Exception(__function__ . __file__ . __line__ . "!not file : $filename");
        }
        $info = getimagesize($filename);
        if (!$info) {
            throw new \Exception(__function__ . __file__ . __line__ . "!image error - no mime : $filename");
            return;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/webp':
                $image_create_func = 'imagecreatefromwebp';
                $image_save_func = 'imagewebp';
                $ext = 'webp';
                break;
            case 'image/jpeg':
                $image_create_func = 'imagecreatefromjpeg';
                $image_save_func = 'imagejpeg';
                $ext = 'jpg';
                break;
            case 'image/png':
                $image_create_func = 'imagecreatefrompng';
                $image_save_func = 'imagepng';
                $ext = 'png';
                $quality = $pngq;
                break;
            case 'image/gif':
                $image_create_func = 'imagecreatefromgif';
                $image_save_func = 'imagegif';
                $ext = 'gif';
                break;
            default:
                throw new \Exception(__function__ . __file__ . __line__ . "Unknown image type. : $mime");
        }

        if ($target) {
            $ext2 = static::getExtension($target);
            $ext = $ext2;
            switch ($ext2) {
                case 'webp':
                    $image_save_func = 'imagewebp';
                    $quality = 80;
                    break;
                case 'jpg':
                    $image_save_func = 'imagejpeg';
                    $quality = 70;
                    break;
                case 'png':
                    $image_save_func = 'imagepng';
                    $quality = $pngq;
                    break;
                case 'gif':
                    $image_save_func = 'imagegif';
                    break;
                #default:throw new \Exception(__function__ . __file__ . __line__ . "Unknown image type. : $mime");
            }
        }

        $img = $image_create_func($filename);
        list($cuwidth, $cuheight) = getimagesize($filename);
        $oheight = $cuheight;
        $owidth = $cuwidth;
        $ratio = $cuwidth / $cuheight;
        if ($h and !$w) {
            $w = $h * $ratio;
        }
        if ($w and !$h) {
            $h = $w / $ratio;
        }
        if ($w > $cuwidth) {
            $h = ceil($cuwidth * $h / $w);
            $w = $cuwidth;
        }#do not enlarge
        $width = $w;
        $height = $h;
        $tmp = imagecreatetruecolor($width, $height);
        $posx = $posy = 0;

        if (isset($cropping)) {
            if (isset($resize)) { #cropped from middle
                if ($ratio >= 1) {
                    $srcy = 0;
                    $srcx = ($cuwidth - $cuheight) / 2;
                    $cuwidth = $cuheight;
                } else {
                    $srcx = 0;
                    $srcy = ($cuheight - $cuwidth) / 2;
                    $cuheight = $cuwidth;
                }
            } else {
                $srcx = ($cuwidth - $width) / 2;
                $srcy = ($cuheight - $height) / 2;
                $cuwidth = $width;
                $cuheight = $height;
            }
            #print_r(compact('ratio','posx','posy','srcx','srcy','width','height','cuwidth','cuheight'));
            #imagecopyresampled($tmp, $img, $posx, $posy, $srcx, $srcy, $width, $height, $cuwidth, $cuheight);$image_save_func($tmp, $target, $quality);
        } else {
            if (isset($vertical_center) or isset($horizontal_center)) {
                if (isset($background_color)) {
                    $hex2rgb = static::hex2rgb2($background_color);
                    $color = imagecolorallocate($tmp, $hex2rgb[0], $hex2rgb[1], $hex2rgb[2]); //filled in white
                    imagefilledrectangle($tmp, 0, 0, $width, $height, $color);
                }
                if (isset($vertical_center)) {
                    $height = ($cuheight / $cuwidth) * $width;
                }
                if (isset($horizontal_center)) {
                    $width = ($cuwidth / $cuheight) * $height;
                }
                /** ne pas dépasser ni les dimensions spécifiées, ni celle de l'image source -> cumul des deux centrages */
                $height = ($height > $cuheight) ? $cuheight : (($height > $oheight) ? $oheight : $height);
                $width = ($width > $cuwidth) ? $cuwidth : (($width > $owidth) ? $owidth : $width);
            }
            /** sinon la déformation est explicite*/
        }

        if (in_array($ext, ['png', 'webp'])) {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            /**rempli de la couleur transparente le fond puis*/
            imagefilledrectangle($tmp, 0, 0, $width, $height, $transparent);
#imagecopyresampled($tmp, $img, $posx, $posy, $srcx, $srcy, $width, $height,$cuwidth, $cuheight);
        }

        imagecopyresampled($tmp, $img, $posx, $posy, $srcx, $srcy, $width, $height, $cuwidth, $cuheight);

        if (strpos($target, 'thumbs/') and 0) { #salomon.com pants -retry until less than 30% of the thumb is white
            $count = \getPixelCountByColor($tmp, 16777215);
            while (($count[0] / $count[1]) > 0.3) {
                $w *= 2;
                $h *= 2;
                $cuwidth *= 2;
                $cuheight *= 2; #417x1000px
                $srcx = ($owidth - $w) / 2;
                $srcy = ($oheight - $h) / 2;
                if ($srcx + $cuwidth > $owidth or $srcy + $cuheight > $oheight) {
                    break;
                }
                imagecopyresampled($tmp, $img, $posx, $posy, $srcx, $srcy, $width, $height, $cuwidth, $cuheight);
                $count = \getPixelCountByColor($tmp, 16777215);
            }
        }

        if (isset($grayscale)) {
            imagefilter($tmp, IMG_FILTER_GRAYSCALE);
        }

        if (!isset($target)) {#todo: pourquoi ai-je fais cela à l'époque ??? Génération image inline sans sauvegarder le fihier ???
            $image_save_func($tmp);
            imagedestroy($tmp);
            throw new \Exception(__function__ . __file__ . __line__ . "!no target");
        }

        static::makereps($target);#construire l'arboresence si manquante
        $success = @touch($target);
#catched above ..
        /*try {$success = @touch($target);} catch (\Exception $e) {throw new \Exception(__function__.__file__.__line__."#no writable:$target".$e->getMessage());}*/
        if (!$success) {
            return;
        }

        if ($ext == 'jpg') {#progressive
            imageinterlace($tmp, true);
        }

        #can't write this file ..
        if ($tmp) {
            $image_save_func($tmp, $target, $quality); # . $ext
            imagedestroy($tmp);
        }
        return filesize($target); /*todo: is success if touched, might be empty on failure ( too big or others ... )*/
    }

    static function makereps($target)
    {
        $x = dirname($target);
        if (is_dir($x)) {
            return 1;
        }
        return mkdir($x, 0777, true);
    }

    static function thumbnailFileName($file, $w = 0, $h = 0)
    {
        $td = static::getconf('thumbnailsDir');#y/thumbs
        $defaultImage = static::getconf('defaultImage');#y/default.png

        $file = str_replace('/', '-_', trim($file, '/'));
        $file = explode('.', $file);
        $ext = array_pop($file);
        $file = implode('.', $file);
        if (strlen($file) < 10) {
            $file = str_replace('/', '-_', $defaultImage);
            $a = 1;
        }
        if ($w) {
            $file .= '-_w' . $w;
        }
        if ($h) {
            $file .= '-_h' . $h;
        }
        $file .= '.' . $ext;
        return $td . $file;
    }

    /* parses an old legacy thumbnail path, converting in new one */
    static function thumb($img)
    {
        #return _LANG_PATH_ . '/stream/index.html?image=' .$img;#old way otherwise ..
        \parse_str('image=' . $img, $m);
        $m['width'] = (isset($m['width'])) ? $m['width'] : 0;
        $m['height'] = (isset($m['height'])) ? $m['height'] : 0;
        return static::thumbnailFileName($m['image'], $m['width'], $m['height']);
    }

    /*accessed via __callStatic*/
    private function privateMethod($a)
    {
        return json_encode([__function__, $a]);
    }

    /*
    $privateClass=new privateClass();
    list($reflect,$methods,$props,$values)=static::privateAccess($privateClass);
    foreach($props as $k=>$v){
        $k->setValue($privateClass,$k.$v2.'_');
    }
    foreach($methods as $k=>$v){
        $res[$k]=$v->invoke($object);
    }
    print_r($res);
    [$reflect,$privateVariablesAndMethods];#$reflect[$prop]->setValue($private,$v);
    */
    static function privateAccess($class)
    {
        $privateVariablesAndMethods = ['methods' => [], 'vars' => []];
        if (is_object($class)) {
            $object = $class;
        } else {
            $object = new $class();#no parameters !
        }

        $reflect = new \ReflectionClass($object);
#ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_ABSTRACT | ReflectionMethod::IS_FINAL
        $which = \ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE;
        $methods = $reflect->getMethods($which);
        $props = $reflect->getProperties($which);

        foreach ($methods as $method) {
            $method->setAccessible(true);
            $privateVariablesAndMethods['methods'][] = $method->getName();
            #$exec=$method->invoke($object);#new exclusive
        }

        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $v = $prop->getValue($object);
            #$prop->setValue($private, $v . '_2');#alter private prop
            $privateVariablesAndMethods['vars'][$prop->getName()] = $v;
        }
        return [$reflect, $methods, $props, $privateVariablesAndMethods];#$reflect[$prop]->setValue($private,$v);
        #return compact('reflect','methods','props','privateVariablesAndMethods');#$reflect[$prop]->setValue($private,$v);
    }

    static function getAllVars($class)
    {
        if (is_object($class)) {
            $object = $class;
        } else {
            $object = new $class();#no parameters !
        }
        $reflect = new \ReflectionClass($object);
        $which = \ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE;
        $props = $reflect->getProperties($which);
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $ret[$prop->getName()] = $prop->getValue($object);
        }
        return $ret;
    }

    static function getAllMethods($class)
    {
        if (is_object($class)) {
            $object = $class;
        } else {
            $object = new $class();#no parameters !
        }
        $reflect = new \ReflectionClass($object);
        $which = \ReflectionMethod::IS_PROTECTED | \ReflectionMethod::IS_PRIVATE;
        $methods = $reflect->getMethods($which);
        foreach ($methods as $method) {
            $method->setAccessible(true);
            $ret[$method->name] = $method->invoke($object);
        }
        return $ret;
    }

    static function insert4row($z)
    {
        foreach ($z as &$v) {
            if (0 and $v === "'0'") {
                $v = 0;
            } elseif ($v and in_array(strtolower($v), ['?', 'now()'])) {
                null;#keep
            } elseif ($v and strpos(strtolower($v), 'convert(') !== FALSE) {
                null;
            } elseif ($v and strpos(strtolower($v), 'binary(') !== FALSE) {
                null;
            } elseif ($v and substr($v, 0, 2) == '0x') {//Keep as if
                $binary = 1;
            } elseif ($v and !is_numeric($v)) {
                $v = "'" . str_replace("'", "\'", preg_replace("~(\\r+|\\n+)~is", '\n', $v)) . "'";
                $a = 1;
            } elseif (is_null($v)) {
                $v = 'null';
            } elseif (is_string($v) and empty($v)) {
                $v = "''";
            }
        }
        unset($v);
        return '(' . implode(',', $z) . ')';
    }

    static function insertValues($z)
    {
        $values = static::insert4row($z);
        $keys = implode(',', array_keys($z));
        return "($keys) values $values";
    }

    static function updateValues2($t)
    {
        $upd = $params = [];
        foreach ($t as $k => $v) {
            $upd[] = '`' . trim($k, '`') . '`=?';
            $params[] = $v;
        }
        $upd = implode(',', $upd);
        return [$upd, $params];
    }

    static function updateValues($t)
    {
        $upd = [];
        foreach ($t as $k => $v) {
            $k = trim($k, '`');
            if (is_numeric($v) or $v == '?') {
                null;
            } else {
                $v = '"' . str_replace('"', '', $v) . '"';
            }
            $upd[] = "`$k`=" . $v;
        }
        return implode(',', $upd);
    }

    /* simple wrappers */
    static function alertMail($sub = '', $msg = '', $to = null, $head = '', $from = '')
    {
        if (!$to) {
            $to = static::getConf('defaultWebmasterEmail');
        }
        #'alert:'.$data['host'].' disk usage '.$data['v'],'msg',
        $s = "\r\n";
        if (strpos($head, 'From:') === false) {
            if (!$from) {
                $from = static::getConf('defaultWebmasterTitle') . ' <' . $to . '>';
            }#might have been re-injected
            $head .= "From: $from{$s}Reply-To: $from{$s}";
        }
        if (strpos($head, 'text/html') === false) {
            $head .= "Content-type: text/html; charset=utf-8{$s}";
            #$head .= "MIME-Version: 1.0{$s}";#Content-type: text/html; charset=utf-8{$s}            iso-8859-1
        }
        echo "\n Alert :: $to, $sub\n\n";
        return mail($to, $sub, $msg, $head);
    }

    /* nice logs ips instead of ipv6 .. */
    static function ip2hostname($x)
    {
        $ips = static::getConf('ip2hostname');
        if (isset($ips[$x])) {
            return $ips[$x];
        }
        return $x;
    }

    static function friendly_error_type($type)
    {
        static $levels = null;
        if ($levels === null) {
            $levels = [];
            foreach (get_defined_constants() as $key => $value) {
                if (strpos($key, 'E_') !== 0) {
                    continue;
                }
                $levels[$value] = substr($key, 2);
            }
        }
        $out = [];
        foreach ($levels as $int => $string) {
            if ($int & $type) {
                $out[] = $string;
            }
            $type &= ~$int;
        }
        if ($type) {
            $out[] = "Error Remainder [{$type}]";
        }
        return implode(' & ', $out);
    }

    static function var2bash($x, $prefix = '', $lv = 0, $ignoreLevelsUpperThan = 99999)
    {
        $z = [];
        foreach ($x as $k => $v) {
            if (is_array($v)) {
                if ($ignoreLevelsUpperThan >= $lv) {
                    continue;
                }
                $z2 = static::var2bash($v, $prefix . '[' . $k . ']', $lv + 1);
                $z = array_merge($z, $z2);
                continue;
            }
            $z[] = $prefix . '[' . $k . ']=' . str_replace(' ', '%20', $v);#no line breaks
        }
        return $z;
    }

    static function win2unix($x)
    {
        return str_replace('\\', '/', $x);
    }

    /** todo temp timelock based on last version tag .. ou md5 des sommes des migrations effectuées ( non commité ) */
    static function needsMigration()
    {
        $migrated = __DIR__ . '/migrations/migrated.log';
        if (!is_file($migrated)) {
            io::FPCJ($migrated, []);
        }
        $done = io::fgcj($migrated);
        $d = __DIR__ . '/migrations/';
        $migrations = array_merge(glob($d . '*.sql'), glob($d . '*.php'));
        foreach ($migrations as &$f) {
            $f = basename($f);
        }
        unset($f);
        $todo = array_diff($migrations, $done);
        $ok = [];
        if ($todo) {
            foreach ($todo as $f) {
                $basename = basename($f);
                $ext = static::getExtension($f);
                if ($ext == 'sql') {
                    $x = explode("\n", io::fgc($d . $f));
                    foreach ($x as $__line => $v) {
                        $v = rtrim($v, "\t\s\n\r;- ");
                        if (strlen($v) < 10) {
                            continue;
                        }#saut de ligne
                        $ok[] = static::sql($v);
                        if (isset($_ENV['_err']['sql']) and $_ENV['_err']['sql']) {
                            $err = 1;
                            continue 2;#ignore migration, not registered and logged to errors
                        }
                        $a = 1;
                    }
                } elseif ($ext == 'php') {
                    $ok[] = require_once $d . $f;
                }
                $done[] = $basename;
                $a = 1;
            }
            io::FPCJ($migrated, $done);
        }
        #io::fpc(__DIR__.'/migrations/done.lock',);
    }

// $conn=compact('h,u,p,db,names']); static::sql2($conn,$sql,[]);
    static function sql2($sqlConnParameters,$sql, $params=[], $errorCallback = false )
    {
        return static::sql(['sqlConnParameters' => $sqlConnParameters,'sql' => $sql, 'params' => $params,'errorCallback'=>$errorCallback]);
    }

    static function sql3($sql, $params=[])
    {
        return static::sql(['sqlConnParameters' => static::$connection,'sql' => $sql, 'params' => $params, 'errorCallback'=>function($a,$b){ throw new \Exception($a.' / '.$b);}]);
    }
//  static::sql(['sql'=>'request','s'=>compact('h,u,p,db,names']);
    static function sql($sql, $conf = 'mysql', $charset = 0, $port = 3306, $ignoreErrors = 0, $try = 0, $search = 0, $params = [], $intercepts = 0, $allowError = 0, $errorCallback = 0, $connection = 0, $sqlConnParameters = [])
    {
        static $nbr = 0;
        $names = 0;
        $nbr++;
        $start = microtime(true);
        $baseConf = $sql;
        $s = static::getConf($conf);#<==== attention, pleins de trucs ont besoin de cleaalptech utf8 set names as default
        if($sqlConnParameters){$s=$sqlConnParameters;extract($s);}
        //$names = $s['names'];//UTF8 ou
        if (is_array($sql)) {
            extract($sql);
            if (is_array($sql) and !isset($sql['sql'])) {
                $sql = 0;#not within extration
            }
            if($sqlConnParameters){$s=$sqlConnParameters;extract($s);}
            #unset($sql);
            if (isset($s)) {#encore des confs nesteés
                extract($s);
            }
        }#overrides

        if ($try > 3) return;
        $stmt = 0;

        $sql = trim($sql);
        if (!isset($s['h'])) {
            $a = 1;
        }

        if (!$connection) {
            $k = 'sqlc:' . $s['h'] . ':' . $s['db'] . ':' . $names . ':' . $port;
            if (isset($_ENV[$k])) {
                $connection = $_ENV[$k];
            }
            if (!isset($_ENV[$k])) {#mysqlclose on shutdown
                $_c = $_ENV[$k] = $connection = \mysqli_connect($s['h'], $s['u'], $s['p'], $s['db'], $port);
                if (!$_c) {
                    error_log('mysqlconnect:'.$_SERVER['REQUEST_URI']);
                    $_e = \mysqli_connect_error($connection);
                    static::breakpoint('connection error', $_e);
                    #print_r($s);
                    die('connection error');
                }
                if (isset($_c->error) and $_c->error) {
                    static::breakpoint($_c);
                }
                $_ok = \mysqli_select_db($connection, $s['db']);
                if (!$_ok) {
                    $a = 1;
                    #mysqli_select_db($_c,'superadmin');
                }
                if (1 and $names) {
                    $ok = \mysqli_query($connection, "SET NAMES '" . $names . "'");
                    $a = 1;
                }#db encoding
                if ($charset) {
                    $__ok = \mysqli_set_charset($connection, $charset);
                    $a = 1;
                    #mysqli_query($connection,"SET charset '".$charset."'");
                }
            }
            if (0 and $names and $names != $connection) {
                \mysqli_query($connection, 'SET NAMES ' . $names);#db encoding
            }

        }

        if (!$sql) {
            return $connection;#simple connection
        }

        if ($intercepts) {
            $intercepts('sql', $sql);
        }

        if ($params) {
            if ($stmt = \mysqli_prepare($connection, $sql)) {#"SELECT District FROM City WHERE Name=?"
                $types = [];
                foreach ($params as $v) {
                    if (gettype($v) == 'integer') $types[] = 'i';
                    else $types[] = 's';
                }
                if(0){
                #array_unshift(implode('',$types),$params);
//  <b>Warning</b>:  Parameter 3 to mysqli_stmt_bind_param() expected to be a reference, value given call_user_func_array(array($stmt, 'bind_param')
                $ops = array_merge([$stmt, implode('', $types)], static::refValues($params));
                //call_user_func_array(array($stmt, 'bind_param'), $ops));
                //call_user_func_array(array($stmt, 'bind_param'), refValues($params));
                call_user_func_array('mysqli_stmt_bind_param', $ops);#mefiat si plusieurs valeurs == same ..
                }

                mysqli_stmt_bind_param($stmt, implode('', $types), ...$params);

                #mysqli_stmt_bind_param($stmt, implode('',$types), $v);
                \mysqli_stmt_execute($stmt);
            }
        } else {
            if (!$sql) {
                return;
            }
            $x2 = \mysqli_query($connection, $sql);
        }

        $err = \mysqli_error($connection);
        if ($err and !$ignoreErrors) {
            if (stripos($err, 'MySQL server has gone away') !== FALSE) {//  MySQL server has gone away
                unset($_ENV[$k]);
                $x = static::sql($baseConf, $conf, $charset, $port, $ignoreErrors, $try + 1);
                return $x;
            }
            static::breakpoint('sql error', $sql, $err);

            $_ENV['_sql'][$nbr . ' : ' . $sql] = $_ENV['_err']['sql'][$sql] = $err;
            $a = 1;
            $d = debug_backtrace(-2);
            $c = [$_SERVER['REQUEST_URI'], $_COOKIE, $_POST];
            static::dbm(compact('sql', 'err', 'c', 'd'), 'sqlerror');
            if ($errorCallback) {
                return $errorCallback($sql, $err);
            }

            if (isset($_ENV['sqlException']) and $_ENV['sqlException']) {
                throw new \Exception($err . ' - ' . $sql);
            }

            if (!$allowError and isset($_ENV['dieOnFirstError']) and $_ENV['dieOnFirstError']) {
                $d1 = end($d);
                $dies = $d1['file'] . '::' . $d1['line'];
                $_ENV['_die'] = print_r(compact('dies', 'err', 'sql', 'd'), 1);
                echo $_ENV['_die'];
                static::_die('first sql error :: ' . $dies);
            }
            return [];
        }
        if (isset($_ENV['stop']) and $_ENV['stop']) {
            $_ENV['stop'] = 0;
            $a = 1;
        }
        if (Preg_match("~(\*/ |^)(create|update|alter|delete|replace) ~i", $sql)) {
            $_ENV['sqlm'][] = $sql;
            if ($stmt) {
                $nb = $stmt->affected_rows;
                \mysqli_stmt_close($stmt);
            } else
                $nb = \Mysqli_affected_rows($connection);
            $_ENV['_sql'][$nbr . ' : ' . $sql] = $nb;
            if (!$nb) {
                return 0;
            }
            return $nb;
        } elseif (Preg_match("~(\*/ |^)insert ~i", $sql)) {
            $_ENV['sqlm'][] = $sql;
            if ($stmt) {
                $id = $stmt->insert_id;
                \mysqli_stmt_close($stmt);
            } else $id = \Mysqli_insert_id($connection);
            $_ENV['_sql'][$nbr . ' : ' . $sql] = $id;
            if (!$id) {
                return -999;
            }
            return $id;
        }

        if (isset($x2) and is_bool($x2)) {#use
            $_ENV['_sql'][$nbr . ' : ' . $sql] = 1;
            return $sql;
        }

        $res = [];
        if ($stmt) {
            $x2 = \mysqli_stmt_get_result($stmt);
            \mysqli_stmt_close($stmt);
        }// $_c->stat()
        /**
         * MYSQLI_USE_RESULT - returns a mysqli_result object with unbuffered result set. As long as there are pending records waiting to be fetched, the connection line will be busy and all subsequent calls will return error Commands out of sync. To avoid the error all records must be fetched from the server or the result set must be discarded by calling mysqli_free_result().
         * MYSQLI_ASYNC (available with mysqlnd) - the query is performed asynchronously and no result set is immediately returned. mysqli_poll() is then used to get results from such queries. Used in combination with either MYSQLI_STORE_RESULT or MYSQLI_USE_RESULT constant.
         */
        if ($x2) {
            while ($x = @\mysqli_fetch_assoc($x2)) {
                if ($search) {
                    foreach ($x as $k => $v) {
                        if (preg_match('~' . $search . '~i', $v)) {
                            static::breakpoint($x);
                        }
                    }
                }
                $res[] = $x;
            }
            $res = static::sqlClassifier($sql, $res);
        }
        if (isset($_ENV['stop']) and $_ENV['stop']) {
            $_ENV['stop'] = 0;
            $reproductible = json_encode([$res, $sql]);
            $a = 1;
        }
        if (!isset($_ENV['conf']['nostats'])) {
            $_ENV['_sql'][$nbr . ' : ' . $sql] = $res;
            if (isset($_ENV['_sqlT'])) {
                $_ENV['_sqlT'][$sql] = microtime(true) - $start;
            }
        }
        return $res;
    }

    static function refValues($arr){
        if (strnatcmp(phpversion(),'5.3') >= 0)  {$refs = [];foreach($arr as $key => $value) $refs[$key] = &$arr[$key];return $refs;}
        return $arr;
    }

    static function verb($sql)
    {
        $verbs = [
            'select' => (stripos($sql, 'select ') !== FALSE ? 1 + stripos($sql, 'select ') : null),
            'insert' => (stripos($sql, 'insert ') !== FALSE ? 1 + stripos($sql, 'insert ') : null),
            'update' => (stripos($sql, 'update ') !== FALSE ? 1 + stripos($sql, 'update ') : null),
            'delete' => (stripos($sql, 'delete ') !== FALSE ? 1 + stripos($sql, 'delete ') : null),
            'create' => (stripos($sql, 'create ') !== FALSE ? 1 + stripos($sql, 'create ') : null),
            'drop' => (stripos($sql, 'drop ') !== FALSE ? 1 + stripos($sql, 'create ') : null),
            'show' => (stripos($sql, 'show ') !== FALSE ? 1 + stripos($sql, 'show ') : null),
            'alter' => (stripos($sql, 'alter ') !== FALSE ? 1 + stripos($sql, 'alter ') : null),
            'truncate' => (stripos($sql, 'truncate ') !== FALSE ? 1 + stripos($sql, 'truncate ') : null),
        ];
        asort($verbs);
        $verb = array_keys(array_filter($verbs))[0];
        if (in_array($verb, ['drop', 'truncate'])) {
            throw new \Exception($verb . " operations are flagged as insecure ..");
        }
        if (in_array($verb, ['delete', 'update']) && stripos($sql, 'where ') === FALSE) {
            throw new \Exception("no where in update statement, that's hell of a danger -- did you forget something ?");
        }
        return $verb;
    }

    static function sqlite($db, $sql = null, $params = null, $cb = null/*, $search = null, $bindParams = 1, $intercepts = 0, $errorCallback = 0, $retry = 0, $preConnect = [], $options = []*/){
        $kon=null;
        if (is_array($db)) extract($db);// un fichier par table et basta, à moins de vouloir effectuer des jointures ...
        //try {
            if (isset($_ENV['sqlite_' . $db])) {
                $kon=$_ENV['sqlite_' . $db];
            }else{
                $kon = $_ENV['sqlite_' . $db] = new \PDO("sqlite:".$db);
                $kon->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
                $kon->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            }

            $verb=static::verb($sql);
            $sql=trim($sql,"\n\r\t ;").';';

            if($params){
                $res = $kon->prepare($sql);
                $res->execute($params);
            }elseif($verb=='select'){
                $res = $kon->query($sql);//$nbres = count($res);
            }else{
                $res = $kon->exec($sql);
            }

            if ($verb == 'select') {
                $res = $res->fetchAll();
                if ($cb) {
                    $res = $cb($res);
                }
                $res = static::sqlClassifier($sql, $res);
            } elseif ($verb == 'insert') {
                $res = $kon->lastInsertId();
            } elseif ($verb == 'update') {
                $res = $kon->query('SELECT CHANGES()')->fetchAll()[0]['CHANGES()'];
            }

        /*}catch(\throwable $e) {//PDOException;
            return "#exception:" . $e->getMessage();
        }*/
        return $res;
    }

    static function sqlClassifier($sql, $data)
    {// as ARRAYK, beware/
        $arrayk = strpos($sql, 'as ARRAYK');#array_key_exists('ARRAYK', $x);# (isset($x['ARRAYK']) or is_null($x['ARRAYK']));
        $pkid = strpos($sql, 'as pkid');#array_key_exists('pkid', $x);#(isset($x['pkid']) or is_null($x['pkid']));
        $unikk = strpos($sql, 'as unikk');#array_key_exists('unikk', $x);#(isset($x['unikk']) or is_null($x['unikk']));
        $roww = strpos($sql, 'as roww');#array_key_exists('roww', $x);#(isset($x['roww']) or is_null($x['roww']));
        if (!$arrayk && !$pkid && !$unikk && !$roww) return $data;
        $res=[];
        foreach ($data as $x) {
            if ($arrayk and $pkid and $unikk) {// 2 dmin
                $res[$x['ARRAYK']][$x['pkid']] = $x['unikk'];
            } elseif ($arrayk and $unikk) {// 1 dim
                $res[$x['ARRAYK']][] = $x['unikk'];
            } elseif ($arrayk and $pkid) {
                $res[$x['ARRAYK']][$x['pkid']] = array_diff($x, ['ARRAYK' => $x['ARRAYK'], 'pkid' => $x['pkid']]);#multiple res per keys
            } elseif ($arrayk) {
                $res[$x['ARRAYK']][] = array_diff($x, ['ARRAYK' => $x['ARRAYK']]);#multiple res per keys
            } elseif ($unikk) {
                return $x['unikk'];#single expectation return result
            } elseif ($pkid and $roww) {
                $res[$x['pkid']] = $x['roww'];#single expectation return result
            } elseif ($pkid) {
                $res[$x['pkid']] = array_diff($x,['pkid'=>$x['pkid']]);#named pkid row
            } elseif ($roww) {
                $res[] = $x['roww'];#single expectation per row
            } else {
                $res[] = $x;
            }
        }
        if (strpos($sql, ' as unikk') and count($res) == 1 and isset($res[0]['unikk']) and is_null($res[0]['unikk'])) {// id unikk is null
            return null;
        }
        return $res;
    }

    static function shortTrace($bt){
        foreach($bt as &$t){$t=basename($t['file']).'#'.$t['line'];}unset($t);krsort($bt);
        return array_values($bt);
    }

    static function pdo($h, $sql = null, $params = null, $db = null, $u = null, $p = null, $search = null, $bindParams = 1, $intercepts = 0, $errorCallback = 0, $retry = 0, $preConnect = [], $options = [], $cb = null)
    {
        static $nbr = 0;
        //$cn->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        if (!$options) {
            $options = array(
                \PDO::ATTR_EMULATE_PREPARES => true,// keep it fast please , still converts to integer, funny, nope ?
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
                //  \PDO::ATTR_PERSISTENT => TRUE,  // we want to use persistent connections
                //  \PDO::MYSQL_ATTR_COMPRESS => TRUE, // MySQL-specific attribute
            );
            if (isset($_SERVER['pdoOptions'])) {
                foreach ($_SERVER['pdoOptions'] as $k => $v) $options[$k] = $v;
            }
            //  // Memory Saver, performance ?? ..
        }

        //$db = new \DB\SQL('mysql:host=localhost;port=3306;dbname=mysqldb','username','password', $options)
        $port = 3306;
        $names = 0;
        if (is_array($h)) extract($h);
        $verb = static::verb($sql);

        if ($params and is_string($params)) $params = [$params];

        if (isset($_ENV['sqlLog']) and $_ENV['sqlLog'] or (isset($_ENV['sqlTime']) and $_ENV['sqlTime'])) {
            $a = microtime(1);
            file_put_contents($_ENV['sqlLog'], "\n\nRunning:" . $sql, 8);
        }
        $konnektion = $h . $db . $port . $names;
        try {
            if (!isset($_ENV['pdo_' . $konnektion])) {
                $_ENV['pdo_' . $konnektion] = new \PDO('mysql:host=' . $h . ';port=' . $port . ';dbname=' . $db, $u, $p, $options);
                //$_ENV['pdo_' . $konnektion]->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $cnx = $_ENV['pdo_' . $konnektion];
                if ($names) {
                    $cmd = $cnx->prepare("SET NAMES '" . $names . "'");
                    $cmd->execute();
                }
                if ($preConnect) {
                    foreach ($preConnect as $pre) {
                        $cmd = $cnx->prepare($pre);
                        $cmd->execute();
                    }
                }
            } else {
                $cnx = $_ENV['pdo_' . $konnektion];
            }

            if (strpos($sql, 'beginTransaction') !== false) {
                $cnx->beginTransaction();
                return $cnx;
            } elseif (strpos($sql, 'commitTransaction') !== false) {
                $cnx->commit();
                return $cnx;
            } elseif (strpos($sql, 'returnConnection') !== false) {
                return $cnx;
            }

            try {
                if ($params) {
                    $cmd = $cnx->prepare($sql);
                    if ($bindParams) {
                        foreach ($params as $k => $value) {
                            $cmd->bindValue(
                                is_string($k) ? $k : $k + 1, $value,
                                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
                            );
                        }
                        $success = $cmd->execute();
                    } else {
                        $success = $cmd->execute($params);
                    }
                } else {
                    $success = $cmd = $cnx->query($sql);
                }
            } catch (\Throwable $e) {
                $a=$sql;$b=$e->getMessage();
                if (strpos($e->getMessage(), 'Deadlock found when trying') and $retry < 3) {//SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction
                    return static::pdo($h, $sql, $params, $db, $u, $p, $search, $bindParams, $intercepts, $errorCallback, $retry + 1);
                } elseif (strpos($e->getMessage(), 'Lock wait timeout exceeded;') and $retry < 3) {//SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transactionL.
                    return static::pdo($h, $sql, $params, $db, $u, $p, $search, $bindParams, $intercepts, $errorCallback, $retry + 1);
                } elseif (strpos($e->getMessage(), 'MySQL server has gone away') and $retry < 3) {//2006
                    unset($_ENV['pdo_' . $konnektion]);
                    return static::pdo($h, $sql, $params, $db, $u, $p, $search, $bindParams, $intercepts, $errorCallback, $retry + 1);
                }
                throw new \Exception(json_encode(['db'=>static::shortTrace(debug_backtrace(-2)),'msg'=>$e->getMessage()]));
            }#

            if (!$success) {
                //$cnx->errorInfo();;
                $b = 'failure';
            }

            if ($cnx->errorCode() != '00000') {
                $err = implode(' ', $cnx->errorInfo());
                if (strpos($err, 'Y000 2006 MySQL server has gone')) {
                    if ($retry > 3) {
                        if (function_exists('\monolog')) {
                            \monolog('#err#' . $sql . '>' . $err . '>retry:' . $retry . '>Exception');
                        }
                        throw new \Exception($retry . '::' . $sql . " :: " . $err);
                    }
                    unset($_ENV['pdo_' . $konnektion]);
                    if (function_exists('\monolog')) {
                        \monolog('#err#' . $sql . '>' . $err . '>retry:' . $retry);
                    }
                    return static::pdo($h, $sql, $params, $db, $u, $p, $search, $bindParams, $intercepts, $errorCallback, $retry + 1);
                }
                ///*ben*/ insert into shares(uuid,channel_id,target_type,target_id,target_uuid,encoding_id,player_id,created_at,updated_at)values(7304556230388132736,64,'vod_media',7905,7304556230369459242,2390,207,now(),now())
                throw new \Exception($sql . " :: " . $err);
                $err = 'todo -- catch here for sql errors';
            }

            if ($intercepts) {
                $intercepts('sql', $sql);
            }

            if (Preg_match("~(\*/ |^)(create|update|alter|delete|replace) ~i", $sql)) {#
                if (isset($_ENV['sqlTime']) and $_ENV['sqlTime']) {
                    $_ENV['sqlTime'][$sql] = round(microtime(1) - $a, 3);
                }
                $_ENV['sqlm'][] = $sql;
                if (is_bool($cmd)) {// after delete== False => Pas d'effet
                    return $cmd;
                }
                $nb = $cmd->rowCount();
                return $nb;
            } elseif (Preg_match("~(\*/ |^)insert ~i", $sql)) {
                if (isset($_ENV['sqlTime']) and $_ENV['sqlTime']) {
                    $_ENV['sqlTime'][$sql] = round(microtime(1) - $a, 3);
                }
                $_ENV['sqlm'][] = $sql;
                $id = $cnx->lastInsertId();
                if (!$id) {//inserts without primary keys
                    return $cmd->rowCount();//nb inserts
                    return 0;#M2M
                }
                return $id;
            }

            if (is_bool($cmd)) {// and select, why ????
                echo "\nBool:" . $sql;
                return $cmd;
            }

            if (isset($_ENV['sqlLog']) and $_ENV['sqlLog']) {
                file_put_contents($_ENV['sqlLog'], " > exec: " . (round(microtime(1) - $a, 3)), 8);
                $b = microtime(1);
            }
            $res = [];
            while ($x = $cmd->fetch(\PDO::FETCH_ASSOC)) {
                if ($cb) {// for long results or custom array transforms .. spawn processing to child processes ?
                    $x = $cb($x);
                }
                if ($search) {
                    foreach ($x as $k => $v) {
                        if (preg_match('~' . $search . '~i', $v)) {
                            static::breakpoint($x);
                        }
                    }
                }
                $res[]=$x;
            }

            $res = static::sqlClassifier($sql, $res);
            // end pdo assoc

            if (isset($_ENV['sqlLog']) and $_ENV['sqlLog']) {
                file_put_contents($_ENV['sqlLog'], " fetched > " . (round(microtime(1) - $b, 3)), 8);
            }

            if (isset($_ENV['sqlTime']) and $_ENV['sqlTime']) {
                $_ENV['sqlTime'][$nbr . ' : ' . $sql] = round(microtime(1) - $a, 3);
            }
            if (!isset($_ENV['conf']['nostats'])) {
                $_ENV['_sql'][$nbr . ' : ' . $sql] = $res;
            }

            return $res;

        } catch (\Throwable $_e) {
            echo "\n" . $_e->getMessage();
            static::breakpoint('sql error', $sql, $_e);
            if ($errorCallback) {
                return $errorCallback($sql, $_e);
            }
            throw $_e;
        }
    }

    static function nocache()
    {
        header("Expires: on, 23 Feb 1983 19:37:15 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    static function sendMail($to, $sub, $body, $head = null, $from = null, $mid = '')
    {
        $s = "\r\n";
        $sub = '=?UTF-8?B?' . base64_encode($sub) . '?=';
        if (!$mid && preg_match("~Message-ID: ([^\r\n]+)~i", $head, $m) and $m[1]) {
            $mid = $m[1];
        } elseif (!$mid) {
            $mid = preg_replace('~[^a-z0-9]+~i', '', md5(time() . $to . $sub . $body));
            $head .= "Message-ID: " . $mid . $s;
        }#generates messageId if absent
        if (strpos($head, 'text/html') === false) {
            $head .= "MIME-Version: 1.0{$s}Content-type: text/html; charset=utf-8{$s}";
        }#            iso-8859-1   #make html as default :)
        if (!$from and preg_match("~From:[^\r\n]+<([^>]+)>~i", $head, $m) and $m[1]) {#from.$to
            $from = trim($m[1], '> ');
        } elseif (!$from and preg_match("~From: ([^\r\n]+)~i", $head, $m) and $m[1]) {#from.$to
            $from = trim($m[1], '> ');
        }

        if (strpos($head, 'From:') === false) {
            if (!$from) {
                $from = static::getConf('defaultSenderMail');
            }
            $head .= "From: $from{$s}Reply-To: $from{$s}";
        }

        $sp = static::getConf('mailSavePath');
        $sent = mail($to, $sub, $body, $head);
        if ($sp) {#todo:query postfix for messageId
            $f = $_SERVER['DOCUMENT_ROOT'] . $sp . substr(preg_replace('~_+~', '_', preg_replace('~[^a-z0-9@\.\-]~is', '_', $mid . '-_-' . $to . '-_-' . time() . '-_-' . $sub)), 0, 250) . '.json';#
            file_put_contents($f, json_encode(compact('sent', 'to', 'sub', 'body', 'head')));
        }
        return $sent;
    }

    static function stripHtml($x, $violent = 0)
    {
        if ($violent) {
            return preg_replace('~[^a-z0-9,\.:\-_ €\+]~is', '', strip_tags($x));
        }
        return preg_replace('~<[^>]+>~is', '', strip_tags($x));
    }

    static function getBody()
    {
        if (!isset($_SERVER['CONTENT_LENGTH'])) return;
        if (!$_SERVER['CONTENT_LENGTH']) return;
        if (isset($_ENV['phpinput']) and $_ENV['phpinput']) return $_ENV['phpinput'];#once then destroy
        $_ENV['phpinput'] = trim(file_get_contents('php://input', false, stream_context_get_default(), 0, $_SERVER['CONTENT_LENGTH']), "\n\r \0");
        return $_ENV['phpinput'];
    }


    /*}from base{*/
    static function setStatic($a, $b)
    {
        static::${$a} = $b;
    }

    static function getStatic($a)
    {
        static::${$a};
    }

    static function __callStatic($a, $b)
    {
        $i = static::i();
        if (!method_exists($i, $a)) {
            $_ENV['_err']['static class method not found'][] = static::gc() . '::' . $a;
            return;
        }
        return $i->{$a}($b);
        #set singleton value
    }

    static function i($p = null)
    {
        $class = static::gc();
        if (!isset($_ENV['_obj'])) $_ENV['_obj'] = [];
        if (!isset($_ENV['_obj'][$class])) {# creates one
            if (is_array($p) and count($p) == 1 and array_keys($p) == [0]) {
                $p = reset($p);#unpack one dimension
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
        $o->setOrGetKv($p, $o);#Attention : recursivity
        return $o;
    }

    static function gc()
    {
        return get_called_class();
        #return static::class;
    }

    static function setOrGetKv($p = null, $obj = 0)
    {
        if ($obj) {
            $el = $obj;
        }/* elseif (!isset($this)) {# if in object context
            $el = static::i();
        } else {#is declared object
            $el = $this;
        }*/
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

    function set($k, $v = 0, $hydrate = 0, $_newer = 0, $virtual = 0 /* might provide additional contexts */)
    {
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

        if (is_array($k)) {
            foreach ($k as $k2 => $v2) {
                $el->set($k2, $v2, $hydrate, $_newer, $virtual);
            }
            return $el;
        }

        static::$t = 1;
        $el->{$k} = $v;#passe par __set si non défini
        static::$t = 0;
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

    /*}end base methods{*/
    static function stripAccents($str, $utf = 1)
    {#utf0 if opening a windows encoded file
        if ($utf) return strtr($str, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
        #operates is ascii context (latin1)
        return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

#harversine formulae
    static function distance($lat1, $lat2, $lng1, $lng2)
    {
        $pi80 = M_PI / 180;#1 rad
        $lat1 *= $pi80;
        $lng1 *= $pi80;
        $lat2 *= $pi80;
        $lng2 *= $pi80;

        $r = 6372.797; // rayon moyen de la Terre en km
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;
        return $km;
    }

    static function shortDist($olat, $olon, $dist = 1, $maxDistance = null)
    {
        $devVerticaleDistLong = 0;
        $latDegDist = static::distance($olat, $olat + 1, $olon, $olon);# updown::  111 km par deg
        // Combien represente cette distance en degré de latitude ? afin de calculer le rectangle depuis la latitude la plus large proche de l'équateur ...

        if('lonDegDistortion closest to equatorial line for better rectangle inclusion'){
            $devLat=$olat;
            if(!$maxDistance)$maxDistance=$dist;
            $devVerticaleDistLong = $maxDistance / $latDegDist;
            if($olat>0)$devLat-=$devVerticaleDistLong;//north
            else $devLat+=$devVerticaleDistLong;//south
        }

        $lonDegDist = static::distance($devLat, $devLat, $olon, $olon + 1);# rightleft:: 77 km par deg à 45°, se rapprocher de l'équateur ..

        $dlon = $dist / $lonDegDist;// degrees per km
        $dlat = $dist / $latDegDist;
        $rect = [$olat - $dlat, $olat + $dlat, $olon - $dlon, $olon + $dlon];
        return $rect;
    }

    static function addPhotoWaterMark($baseImg = null, $sign = null, $target = null, $position = 'br', $edgeMargin = 10, $quality = 90, $maxW = 100, $maxH = 100)
    {
        if ($maxH) null;//unused ??
        if (is_array($baseImg)) {
            extract($baseImg);
        }
        if (!$baseImg) {
            return;
        }
        $im = imagecreatefromjpeg($baseImg);
        $w = imagesx($im);
        $h = imagesy($im);
        $wr = $w / $h;#1.17
        $stamp = imagecreatefrompng($sign);
        $sx = imagesx($stamp);
        $sy = imagesy($stamp);
        $sr = $sx / $sy;#3.8
        if ($sx > ($w * $maxW / 100)) {
            $nw = round($w * $maxW / 100);
            $nh = round($nw / $sr);
            $tmp = imagecreatetruecolor($nw, $nh);
            $destX = $destY = $srcx = $srcy = 0;
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            imagefilledrectangle($tmp, 0, 0, $nw, $wr, $transparent);
            imagecopyresampled($tmp, $stamp, $destX, $destY, $srcx, $srcy, $nw, $nh, $sx, $sy);
            #imagePng($tmp,uniqid().'.png',9);
            $stamp = $tmp;
            $sx = $nw;
            $sy = $nh;
        }
#todo#¤: others positions, max watermark width %, max watermark height %
        switch ($position) {
            case'br':
                $wp = $w - $sx - $edgeMargin;
                $hp = $h - $sy - $edgeMargin;
                break;
        }

        imagecopy($im, $stamp, $wp, $hp, 0, 0, $sx, $sy);#placer dessus aux coordonnées calculées

        imageJpeg($im, $target, $quality);
        imagedestroy($im);
        return [$target, filesize($target)];
        #header('Content-type: image/png');imagepng($im,$target,$quality);

    }

    static function addBorder($baseImg, $perWidth = 0.5, $prefix = '-', $suffix = '', $save = 1, $qual = 80)
    {
        $target = null;
        if (is_array($baseImg)) extract($baseImg);
        if (gettype($baseImg) == 'resource') {
            $im = $baseImg;
        } else {
            $im = imagecreatefromjpeg($baseImg);
            if (!$target) {
                $fp = explode('/', $baseImg);
                $end = array_pop($fp);
                $fp = implode('/', $fp);
                $target = $fp . $prefix . $end . $suffix;
            }
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $mw = round($w * $perWidth / 100);
        $nw = $w + $mw * 2;
        $nh = $h + $mw * 2;
        $tmp = imagecreatetruecolor($nw, $nh);
        $color_white = ImageColorAllocate($tmp, 255, 255, 255);
        ImageFilledRectangle($tmp, 0, 0, $nw, $nh, $color_white);
        imagecopy($tmp, $im, $mw, $mw, 0, 0, $w, $h);
        if ($save) return imagejpeg($tmp, $target, $qual); else return imagejpeg($tmp, null, $qual);
    }

    static function cropTo($baseImg, $ratio = 16 / 9, $position = 'center', $prefix = '-', $suffix = '', $save = 1, $qual = 80, $target = '', $allowVertical = 0)
    {
        if (is_array($baseImg)) extract($baseImg);
        if (gettype($baseImg) == 'resource') $im = $baseImg; else {
            $im = imagecreatefromjpeg($baseImg);
            if (!$target) {
                $fp = explode('/', $baseImg);
                $end = array_pop($fp);
                $fp = implode('/', $fp);
                $target = $fp . $prefix . $end . $suffix;
            }
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $cropH = $horizontal = 1;
        if ($h > $w) $horizontal = 0;
        if ($ratio < 1) $cropH = 0;
        if ($cropH) null;//unused
        $x = $y = 0;#début:non défini : haut gauche de l'image
        $nh = $h;
        $nw = $ratio * $h;
        if ($allowVertical and !$horizontal) {#on inverse le ratio
            $nw = $w;
            $nh = $ratio * $w;
            if ($nh > $h) {#phpx $td/g.php cropTo verticalNebulae.jpg $r $s
                $nh = $h;
                $nw = $h / $ratio;
            }
        }
        if ($nw > $w) {#nous n'avons pas cette ressource disponible
            $nw = $w;
            $nh = $w / $ratio;
        } else {
            null;#la place existe, le souhait est-il vertical ?
        }

        if ($position == 'center') {
            $y = $h / 2 - $nh / 2;
            $x = $w / 2 - $nw / 2;
        }

        $rect = ['x' => $x, 'y' => $y, 'width' => $nw, 'height' => $nh];
        $res = imagecrop($im, $rect);
        if ($save) return imagejpeg($res, $target, $qual); else return imagejpeg($res, null, $qual);
    }

    static function containIn($baseImg, $ratio = 16 / 9, $position = 'center', $prefix = '-', $suffix = '', $save = 1, $qual = 80, $target = '', $allowVertical = 0)
    {
        if ($position) null;//unused
        if (is_array($baseImg)) extract($baseImg);
        if (gettype($baseImg) == 'resource') $im = $baseImg; else {
            $im = imagecreatefromjpeg($baseImg);
            if (!$target) {
                $fp = explode('/', $baseImg);
                $end = array_pop($fp);
                $fp = implode('/', $fp);
                $target = $fp . $prefix . $end . $suffix;
            }
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $cropH = $horizontal = 1;
        if ($h > $w) $horizontal = 0;
        if ($ratio < 1) $cropH = 0;
        if ($cropH) null;//unused
        $cur = $w / $h;
        $nw = $w;
        $nh = $h;
        $ow = $oh = 0;
        if ($allowVertical and !$horizontal) {
            $ratio = 1 / $ratio;
            $cur = 1 / $cur;/*$nw=$w;$nh=$ratio*$w;*/
        }#inversion ratio du souhait
        if ($ratio > $cur) {#le souhait est plus large que l'image
            $nw = round($h * $ratio);
            $ow = round(($nw - $w) / 2);
        } else {#le souhait est plus haut que l'image
            $nh = round($w / $ratio);
            $oh = round(($nh - $h) / 2);
        }
        #print_r(compact('w','h','nw','nh','target','save','allowVertical'));
        $tmp = imagecreatetruecolor($nw, $nh);
        $color_white = ImageColorAllocate($tmp, 255, 255, 255);
        ImageFilledRectangle($tmp, 0, 0, $nw, $nh, $color_white);
        imagecopy($tmp, $im, $ow, $oh, 0, 0, $w, $h);
        if ($save) return imagejpeg($tmp, $target, $qual); else return imagejpeg($tmp, null, $qual);
    }

#see http://www.asciitable.com/ for references, accents range 128 to 165, weird caracters begins from 169 to 255
    static function asciiToUtf($x)
    {
        $x1 = \str_split($x);
        foreach ($x1 as &$t) {
            $t = \ord($t);
        }
        $max = max($x1);
        if ($max > 195) {
            $x = \utf8_encode($x);
        }
        return $x;
    }

    static function ftpput($ftp_server, $ftp_user_name, $ftp_user_pass, $localFile, $distantFile, $port = 21, $ssl = 0, $timeout = 999, $mode = FTP_ASCII, $passive = 1)
    {
        if (is_array($ftp_server)) extract($ftp_server);
        if (!is_file($localFile)) {
            echo "\n!$localFile";
            return;
        }
        if ($ssl) {
            $conn_id = ftp_ssl_connect($ftp_server, $port, $timeout);
        } else {
            $conn_id = ftp_connect($ftp_server, $port, $timeout);
        }
        if (!$conn_id) {
            echo 'nocon';
            return 0;
        }

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        if (!$login_result) {
            echo 'logfailed';
            return 0;
        }

        #$list=ftp_nlist($conn_id,'.');#rawlist
        $ret = 0;
        if ($passive) ftp_pasv($conn_id, true);
        if ($x = ftp_put($conn_id, $distantFile, $localFile, $mode)) {
            $ret = 1;
        }
        ftp_close($conn_id);
        return $ret;
    }

    static function ftpget($ftp_server, $ftp_user_name, $ftp_user_pass, $distantFile, $localFile, $port = 21, $ssl = 0, $timeout = 999, $mode = FTP_ASCII, $passive = 1)
    {
        if (is_array($ftp_server)) extract($ftp_server);
        if ($ssl) {
            $conn_id = ftp_ssl_connect($ftp_server, $port, $timeout);
        } else {
            $conn_id = ftp_connect($ftp_server, $port, $timeout);
        }
        if (!$conn_id) {
            echo 'nocon';
            return 0;
        }

        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
        if (!$login_result) {
            echo 'logfailed';
            return 0;
        }

        #$list=ftp_nlist($conn_id,'.');#rawlist
        $ret = 0;
        if ($passive) ftp_pasv($conn_id, true);
        if ($x = ftp_get($conn_id, $localFile, $distantFile, $mode)) {
            $ret = 1;
        }
        ftp_close($conn_id);
        return $ret;
    }

    /* } memcached --  redis .. { */
    static function ismemcacheon()
    {
        #return false;
        if (isset($_ENV['memcachedc'])) {
            return $_ENV['memcachedc'];
        }
        if (!function_exists('memcache_connect')) {
            $_ENV['memcachedc'] = false;
            return $_ENV['memcachedc'];
        }
        if (!isset($_ENV['memcachedc'])) {
            $_ENV['memcachedc'] = false;
            #return false;
            try {
                $_ENV['memcachedc'] = @memcache_connect('127.0.0.1', 11211);
            } catch (\Exception $e) {
                $_ENV['memcachedc'] = false;
            }
            return $_ENV['memcachedc'];#false
        }
    }

    static function fgcs($f, $expiration = 0)
    {
        $x = explode('/', $f);
        $x = end($x);
        $memc = static::ismemcacheon();
        if ($memc) {
            $res = memcache_get($memc, $x);
            if ($res) {
                return $res;
            }
        } else {
            rc();
            return rg($x);
        }
#fallback
        if (!$expiration) {
            $expiration = 999999;#10 days for files
        }
        if (!is_file($f)) {
            return null;
        }
        $fmt = filemtime($f);
        if ($fmt < ($_ENV['now'] - $expiration) and 'fallback : filecache is expired') {
            return null;
        }

        if (strpos($f, '.json')) {
            return json_decode(fgc($f), 1);
        }

        if (isset($_ENV['igb']) and $_ENV['igb'] && function_exists('igbinary_unserialize')) {#about 2 to 3 times faster, but can't flush cache based on inner contents then
            return igbinary_unserialize(fgc($f));
        }
        return unserialize(fgc($f));#can grep its contents, memory limit reached no gzip
    }

    static function FPCS($f, $d, $expiration = 0 /*never for opcache*/)
    {
        $memc = static::ismemcacheon();
        if ($memc) {
            $x = explode('/', $f);
            $x = end($x);
            #beware, as he doesnt accept values > 100Mo
            $ok = memcache_set($memc, $x, $d, 0/*MEMCACHE_COMPRESSED?*/, $expiration);
            if ($ok) {
                $d = null;
                return $ok;
            }
        } else {
            rc();
            return rs($f, $d);
        }
        if (strpos($f, '.json')) {
            $r = fpc($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $d = null;#free the ram please
            return $r;
        }
        if ($_ENV['igb'] && function_exists('igbinary_serialize')) {
            $d = igbinary_serialize($d);
        } else {
            $d = serialize($d);
        }
        if (0 && strlen($d) > 9000000 and 'why is this cyclic somehow? something is wrong here ( o2a )') {
            $x = debug_backtrace();
            foreach ($x as &$v) $v = $v['file'] . $v['line'];
            unset($v);
            print_r($x);
            die;
        }
        $r = fpc($f, $d);
        $d = null;#free the ram please
        return $r;
    }

    /** either file or memcache key */
    static function FDM($f)
    {
        $memc = static::ismemcacheon();
        if ($memc) {
            $x = explode('/', $f);
            $x = end($x);
            memcache_delete($memc, $x);
        } else {
            rc();
            return rd($f);
        }
        if (is_file($f)) {
            unlink($f);
        }
    }

    /** sets memcache or file */
    static function FPCM($f, $d, $flag = null)
    {
        $memc = static::ismemcacheon();
        if ($memc) {
            $x = explode('/', $f);
            $x = end($x);
            if ($flag == 8 && 'append') {#https://www.php.net/manual/fr/memcached.append.php, memcache_increment().
                $exists = memcache_get($memc, $x);
                if ($exists) {
                    $d = $exists . $d;
                }
            }
            $ok = memcache_set($memc, $x, $d, 0, 0);
            if ($ok) {
                $d = null;
                return $ok;
            }
        } else {
            rc();
            return rs($f, $d);
        }
        return FPC($f, $d, $flag);
    }

    /** returns memcache or file */
    static function fgcm($f)
    {
        $memc = static::ismemcacheon();
        if ($memc) {
            $x = explode('/', $f);
            $x = end($x);
            $res = memcache_get($memc, $x);
            if ($res) {
                return $res;
            }
        } else {
            rc();
            return rg($f);
        }
        return fgc($f);
    }

    static function rc()
    {
        if (isset($_ENV['rc'])) return;
        if (!class_exists('redis')) {
            $_ENV['rce'] = 'no redis';
            $_ENV['rc'] = new redisfs();
            return;
        }
        try {
            $_ENV['rc'] = new \Redis();
            $_ENV['rc']->connect($_ENV['redis']['h'], $_ENV['redis']['p']);
        } catch (\Exception $e) {
            $_ENV['rce'] = $e;
            $_ENV['rc'] = new redisfs();
        }
        if (isset($_ENV['redis']['pw'])) $_ENV['rc']->auth($_ENV['redis']['pw']);
    }

    static function rs($k, $v)
    {
        static::rc();
        $_ENV['rc']->set($k, $v);
    }

    static function rk($k = '*')
    {
        static::c();
        return $_ENV['rc']->keys($k);
    }

    static function rg($k)
    {
        static::rc();
        return $_ENV['rc']->get($k);
    }

    static function rd($k)
    {
        static::rc();
        return $_ENV['rc']->del($k);
    }

    static function sendPhpMail($to, $sub, $msg, $smtp, $from, $pass, $pk, $dkPass, $domain, $log = null, $smtpPort = 465, $dkSel = 'dk1024-2012', $alt='--nohtml,sorry')
    {
        if (!\is_file($pk)) {
            throw new \exception('npk');
        }
        try {
            if($log){
                file_put_contents($log,"\n}{  ".date('Y-m-d H:i:s').':'.json_encode([$to,$sub,$msg]),8);
            }
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet='UTF-8';
            $mail->DKIM_domain = $domain;
            $mail->DKIM_selector = $dkSel;
            $mail->DKIM_private = $pk;
            $mail->DKIM_identity = $from;
            $mail->DKIM_passphrase = $dkPass;
            $mail->isSMTP();
            $mail->Port = $smtpPort;
            $mail->SMTPAuth = true;
            $mail->Username = $from;
            $mail->Password = $pass;
            $mail->Host = $smtp;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->setFrom($from);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $sub;
            $mail->Body = $msg;
            $mail->AltBody = $alt;
            $mail->send();
            return 'ok';
        } catch (\Throwable $e) {
            return $e->getMessage();
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    static function r304($max, $customEtag=null, $expiration = 900000)
    {// uncheck: network : disable cache, then chrome responds 200 ( inner chrome disk cache ok )
        static $fired;
        if ($fired) {
            return;
        }
        $fired = true;

        if(!is_numeric($max)){
            if(is_file($max)){
                $max=filemtime($max);
            }
        }
        $etag = $max;
        if ($customEtag) $etag = $customEtag;
        $etag = trim(base64_encode((string)$etag),'=');
        $date = gmdate('D, j M Y H:i:s', $max) . ' GMT';
        if (
                (isset($_SERVER['HTTP_IF_NONE_MATCH']) and $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
                or (!$customEtag and isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) and $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $date) // simple mode using last modified
        ) {
            header('HTTP/1.1 304 Not Modified', 1, 304);
            die;
        }
        header('ETag: ' . $etag, 1);
        header('Cache-Control: public, max-age=' . $expiration, 1);
        header('Last-Modified: ' . $date, 1);
        $date2 = gmdate('D, j M Y H:i:s', time() + $expiration) . ' GMT';
        header('Expires: ' . $date2, 1);
    }

    /**
     * static::cachefile('cache/key.php',function(){return ['magic'=>'v1:'.time()];},99);
     *
     * @param $f
     * @param $callback
     * @param $expiration
     * @return mixed|void
     */
    static function cachefile($f, $callback, $expiration = null)
    {
        if($expiration && is_file($f)){
            if(filemtime($f)>(time()-$expiration))$expiration = null;// not expired
        }
        if(is_file($f) and !$expiration){
            return require $f;
        }
        $res = $callback();
        file_put_contents($f,'<?php return '.var_export($res,true).';');
        return $res;
    }

    /**
     * session_start wrapper returns session id
     * @return void
     */
    static function ss()
    {
        if (session_status() === PHP_SESSION_NONE) {session_start();}
        return session_id();
    }

    /**
    * SimpleLogin
    */
    static function simpleLogin($usersPasses){
        static::ss();
        if(isset($_SESSION['logged']) && $_SESSION['logged'])return $_SESSION['logged'];
        foreach($usersPasses as $user=>$pass) {
            if (isset($_COOKIE['log']) and $_COOKIE['log'] == md5($user . $pass)) { $_SESSION['logged'] = $user;return $user;}
            elseif ($_POST['u'] == $user and $_POST['p'] == $pass ) {
                setcookie('log', md5($user . $pass), 3600 * 24 * 365 * 10, '/');
                $_SESSION['logged'] = $user;return $user;
            }
        }
        die("<center>login:<br><form method=post><input name=u placeholder=Username><br><input name=p placeholder=password type=password><br><input type=submit value='Authenticate!' style='cursor:pointer'></form><style>input{width:90vw;} *{font-size:10vh} body{font:10vh 'Avenir Next',sans-serif;background:#000;color:#FFF;}</style>");
    }

    static function str($x,$lv=0){
        if (is_array($x)) {
            foreach ($x as &$v) {
                $v = static::str($x, $lv + 1);
            }unset($v);
        }else{
            $x=str_replace(static::$quotes,static::$unquotes,$x);
        }
        return $x;
    }
    static function unstr($x,$lv=0){
        if (is_array($x)) {
            foreach ($x as &$v) {
                $v = static::unstr($x, $lv + 1);
            }unset($v);
        }else{
            $x=str_replace(static::$unquotes,static::$quotes,$x);
        }
        return $x;
    }

    /**
     * Either resizes and die computed thumbnail or return error
     *
     * imgSource.jpg__w800.webp
     * imgSource.jpg__r.webp
     * pano-pano-visitationChateauAnnecyVueDepuisGrandJeanne_visitationChateauAnnecyVueDepuisGrandJeanne-15b.jpg__r2.webp
     * cropRatioMiddle : pano-pano-visitationChateauAnnecyVueDepuisGrandJeanne_visitationChateauAnnecyVueDepuisGrandJeanne-15b.jpg__max800_r6.2_crm.webp
     *
     * @param $bd   ex : ../imgSources/ ( ends with slash )
     * @param $resizeW
     * @param $resizeH
     * @param $maxW
     * @param $quality
     * @return void
     */
    function tnResizeOn404($bd = '', $max = 800, $resizeW = [50, 100, 400, 800, 1200, 1600], $resizeH = [100, 200], $quality = 70){
        $f = $u= ltrim(static::$uq,'/');//final filename controller relative
        $srcX = $srcY = $fixedW = $fixedH = 0;
        $webp = strpos($u, '.webp') ? true : false;
        $finalExt=explode('.',$u);$finalExt=end($finalExt);

        $exts=explode(',','png,jpg,jpeg,webp,gif,bmp');
        foreach($exts as $ext){
            $s = '.' . $ext . '__';
            if (strpos($u, $s)) {
                $x = explode($s,$u);
                $x[0] = $bd . str_replace(['/tn/'], '/', $x[0]) . '.'.$ext;
                $x[1] = '_' . $x[1];// les paramètres
                if (!is_file($x[0])) {
                    throw new \Exception('h404:nf:' . $u.'->'.$x[0] . ':' . __LINE__);
                }
                break;
            }
        }

        [$ow, $oh, $mime] = getimagesize($x[0]);
        $capX = $w = $ow;
        $capY = $h = $oh;
        $r = $ow / $oh;
        $posData = 0;
        preg_match('~_pd([^_]+)~', $x[1], $m);
        if ($m && $m[1]) {
            $posData = explode(',', $m[1]);
        }
        preg_match('~_w([0-9]+)~', $x[1], $m);
        if ($m && $m[1]) {
            $fixedW = $w = $m[1];
            if (!in_array($w, $resizeW)) throw new \Exception('#resizew:' . __line__);
        }
        preg_match('~_h([0-9]+)~', $x[1], $m);
        if ($m && $m[1]) {
            $fixedH = $h = $m[1];
            if (!in_array($h, $resizeH)) throw new \Exception('#resizeh:' . __line__);
        }
        preg_match('~_max([0-9]+)~', $x[1], $m);
        if ($m && $m[1]) {
            $max = $m[1];
        }
        if (strpos($u, '_crm.webp') or strpos($u, '_crm.jpg')) {
            preg_match('~_r([0-9\.]+)~', $x[1], $m);
            if ($m && $m[1]) $r2 = $m[1];
            if ($w > $max) {
                $w = $max;
            }
            if ($h > $max) {
                $h = $max;
            }
            if ($r2 > $r) {//wider -> déforme ( capY trop faible )
                $h = (int)($w / $r2);
                if ($fixedH) {
                    $h = $fixedH;
                    $w = (int)($h * $r2);
                }
                $capY = (int)($ow / $r2);
                $srcY = $oh / 2 - $capY / 2;// ow:3200,oh:2400,capx:3200,capY:1600, srcY:400
            } else {// vertical plus haut -> limiter la propagation, prend tout la hauteur de l'image
                $w = (int)($h * $r2);
                if ($w > $max) {
                    $w = $max;
                    $h = (int)($w / $r2);
                }
                if ($fixedW) {
                    $w = $fixedW;
                    $h = (int)($w / $r2);
                }
                $capX = (int)($oh * $r2);
                $srcX = $ow / 2 - $capX / 2;
            }
        } elseif (strpos($u, '_r.webp') or strpos($u, '_r.jpg')) {
            if ($fixedW and !$fixedH) $h = (int)($fixedW / $r);
            elseif ($fixedH and !$fixedW) $w = (int)($fixedH * $r);
            if (!$w and !$h) throw new \exception('#' . __line__);
            $r = $ow / $oh;
            if (!$h) $h = (int)($w / $r);
            if (!$w) $w = (int)($h * $r);
        } else {// adaptative h depending on w or vice versa
            if (!$fixedH) $h = (int)($w / $r);// rm tn/IMG_20220920_193718.jpg__w800.webp              http://hp.127.0.0.1.nip.io/tn/IMG_20220920_193718.jpg__w800.webp#gen
            if (!$fixedW) $w = (int)($h * $r);
        }
        $finalW = $w;
        $finalH = $h;
        $tmp = imagecreatetruecolor($finalW, $finalH);
        if ($mime == 2) {
            $image = imagecreatefromjpeg($x[0]);
        } elseif ($mime == 3) {
            $image = imagecreatefrompng($x[0]);
        } elseif ($mime == 32) {
            $image = imagecreatefromwebp($x[0]);
        } else {
            throw new \Exception('#' . __line__);
        }
        if ($posData) {
            $a = 'todo ::';
        }


        if (in_array($finalExt, ['png', 'webp'])) {
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            $transparent = imagecolorallocatealpha($tmp, 255, 255, 255, 127);
            imagefilledrectangle($tmp, 0, 0, $finalW, $finalH, $transparent);
        }

//  function imagecopyresampled($destI,$srcI,$dst_x,$dst_y,$src_x,$src_y,$dst_width,$dst_height,$src_width,$src_height): bool {}
        imagecopyresampled($tmp, $image, 0, 0, $srcX, $srcY, $finalW, $finalH, $capX, $capY);
        if ($webp and function_exists('imagewebp')) {// faster, better than jpeg
            $exists=imagewebp($tmp, $f, $quality);
        } elseif($finalExt == 'png') {
            $exists=imagepng($tmp, $f, 9);
        } else {
            $exists=imagejpeg($tmp, $f, $quality);
        }
        if(!$exists)throw new \exception('cant write '.$f);
        static::r302('/' . $f . '#gen');
        return;
    }
// Strips of Html Tags for Http output ..
    static function cleanHtml($out){
        if(static::$env == 'http'){
            return \str_replace(['<','>'],['&lt;','&gt;'],$out);
            //return \htmlentities($out);
        }
        return $out;
    }

    static function init()
    {
        if (isset($GLOBALS['argv'])) {
            $a = $GLOBALS['argv'];
            $values = [];
            $values['local'] = static::$local = 1;
            $values['ip'] = static::$ip = '127.0.0.1';
            $values['h'] = $values['cli'] = $values['env'] = static::$env = static::$h = static::$ext = 'cli';
            $script = array_shift($a);
            if (strpos($script, '/') === FALSE){
                $script = \getcwd().'/'.$script;
            }
            $values['uq'] = $values['u'] = static::$uq = static::$u = $script;//  $_SERVER['PWD']
            $values['dr'] = static::$dr = \dirname(static::$u);
            $values['q'] = static::$q = implode(',', $a);// php '{"d":{"e":[4,5]}}' a=1 b=2 --c=3;
            foreach($a as $v) {
                if (($decoded = static::jsonValid($v)) && $decoded) {
                    foreach ($decoded as $k => $v) {
                        $values['args'][$k]=static::$args[$k] = $_GET[$k] = $v;
                    }
                }elseif (preg_match('/^--([^=]+)=(.*)/', $v, $m)) {
                    $values['args'][$m[1]]=static::$args[$m[1]] = $_GET[$m[1]] = $m[2];
                } elseif (preg_match('/^([^=]+)=([^=]+)/', $v, $m)) {
                    $values['args'][$m[1]]=static::$args[$m[1]] = $_GET[$m[1]] = $m[2];
                }
            }
            static::conf($values);

        }else{// http forwarded request
            static::$u = $u = $_SERVER['REQUEST_URI'];
            [$uq, $qs] = explode('?', $u);
            static::$q = $qs;
            static::$env = 'http';
            static::$uq = trim($uq,'/');
            static::$ext = (strpos($u,'.') && ($x = explode('.', $u))) ?strtolower(end($x)):'';

            static::$ip = $_SERVER['REMOTE_ADDR'];
            static::$h = $h = $_SERVER['HTTP_HOST'];
            static::$local=(strpos($h,'127.0.0.1')!==FALSE or substr($h,0,4)=='192.');
            static::$dr = $_SERVER['DOCUMENT_ROOT']?rtrim($_SERVER['DOCUMENT_ROOT'], '/'):null;
        }
    }

    static function jsonValid($json, $asArray = true)
    {
        $json = trim($json, "\"'\n\r\t ");
        if (!in_array(substr($json, 0, 1), ['[', '{']) or !in_array(substr($json, -1), [']', '}'])) {
            return null;
        }
        $json = @json_decode($json, $asArray);
        return $json;
    }

    static function on404(){
        if (in_array(static::$ext,['jpg','webp','png','gif'])) {
            try{
                static::tnResizeOn404();
            }catch(\throwable $e){
                static::r404($e->getMessage());
            }
        } elseif(0 and !static::$ext){// Not good, kills current namespace
            $f=trim(static::$uq,'./').'.php';
            if(is_file($f)){
                static::hl('HTTP/1.1 200 OK');
                require_once $f;
                return;
            }
        }
        //static::r404();
    }
/** dev functions wisth Quick And Dirty Development */
    static function printExceptions(){
        \set_exception_handler('\Alptech\Wip\static::exception_handler');
    }

    static function exception_handler(\Throwable $e) {
        echo "\n#exception: " . $e->getMessage();
        return true;
    }

    static function printErrors(){
        \set_error_handler('\Alptech\Wip\static::errorHandler');
    }

    static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        echo"\n#error:".$errno.':'.$errfile.':'.$errline.':'.$errstr;
    }
    static function getTitle($x){return trim(preg_replace('@ +@',' ',preg_replace('@[^0-9a-z]+@',' ',$x)));}

    static function date2fr($x){
        $days=['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
        $months=['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $dow=date('w', strtotime($x));
        [$date,$hour]=explode(' ',$x);
        [$y,$m,$d]=explode('-',$date);//[$h,$i,$s]=explode(':',$hour);
        return ucfirst($days[$dow]).' '.$d.' '.ucfirst($months[$m-1]).' '.$y.' à '.$hour;
    }

    static function noAccents($x)
    {
        $ACCENTS = explode(';', "À;Á;Â;Ã;Ä;Å;à;á;â;ã;ä;å;Ò;Ó;Ô;Õ;Ö;Ø;ò;ó;ô;õ;ö;ø;È;É;Ê;Ë;è;é;ê;ë;Ç;ç;Ì;Í;Î;Ï;ì;í;î;ï;Ù;Ú;Û;Ü;ù;ú;û;ü;ý;ÿ;Ñ;ñ;€"); #â¬;ã©
        $ACCENTS[] = chr(227) . chr(169);
        $NACCENTS = str_split('AAAAAAaaaaaaOOOOOOooooooEEEEeeeeCcIIIIiiiiUUUUuuuuyyNnEe'); #e
        #foreach($ACCENTS as $k=>$v){$r[$v]=$NACCENTS[$k];}unset($k,$v);
        //$weirdChars = "(){}[]$%@!?:,/\|+_'~*^¨°`´²§µ£=<>&;?%";
        return str_replace($ACCENTS, $NACCENTS, $x);
    }

    static function h()
    {
        static $t;
        if ($t) return;
        $t = 1;
        header('Content-Type: text/html; charset=utf-8', 1, 200);
    }

    // postNoReturn($url, ['a'=>1],'application/x-www-form-urlencoded');
    static function postNoReturn($url, $bodyOrParams, $type = 'application/json', $to = 30, $wait = true)
    {
        $parts = parse_url($url);
        if ($bodyOrParams && is_array($bodyOrParams)) {
            $post_params = [];
            foreach ($bodyOrParams as $key => &$val) {
                if (is_array($val)) $val = implode(',', $val);
                $post_params[] = $key . '=' . urlencode($val);
            }
            $bodyOrParams = implode('&', $post_params);
        }
        if(!$bodyOrParams)$bodyOrParams='';// no post, nor body
        if($parts['scheme']=='https')$parts['port']=443;
        $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, $to);
        if ($errno || $errstr) {
            throw new \Exception($url . ':' . $errno . ':' . $errstr);
        }
        if (!$fp) {
            throw new \Exception('no connection to ' . $url);
        }

        $data=[
            'POST ' . $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '') . ' HTTP/1.1',
            'Host: ' . $parts['host'],
            'Content-Type: ' . $type,
            'Content-Length: ' . \strlen($bodyOrParams),
            'Connection: Close',
            '',// \r\n\r\n is the boundary
            $bodyOrParams,
        ];
        fwrite($fp, implode("\r\n", $data));
        $response = '';
        while ($wait && !feof($fp)) { //     otherwise, wont get body
            $response .= fgets($fp, 1024);
        }
        fclose($fp);
        return $response;
    }
    static function args()
    {
        $argvs=$GLOBALS['argv'];
        array_shift($argvs);// script name
        $args = array();
        foreach ($argvs as $argv) {
            if (($decoded = static::jsonValid($argv)) && $decoded) {
                $args = array_merge($args, $decoded);
            } elseif (preg_match('/^--([^=]+)=(.*)/', $argv, $match)) {
                $args[$match[1]] = $match[2];
            } elseif (preg_match('/^([^=]+)=([^=]+)/', $argv, $match)) {
                $args[$match[1]] = $match[2];
            }
        }
        return $args;
    }
}

fun::init();
return; ?>
