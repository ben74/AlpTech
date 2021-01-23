<?php

namespace Alptech\Wip;

class fun /* extends base */
{
    static $t=0,$conf = [],$_shared = [];#collectif tant

    static function main()
    {
        return __FILE__ . __LINE__;#
    }

    static function firewall($url = null, $rawBody = null, $req = null, $lp = null, $files = null)
    {
        if (!$lp) {
            $lp = fun::getConf('logs');
        }
        if (!$url) {
            $url = $_SERVER['REQUEST_URI'];
            if(preg_match('~accesson.php~i',$url,$m)){return 'injection pattern ' . $m[0] . ' in url ' . $url;}#and querystring}
        }
        if (!$rawBody) {
             $data = fun::getBody();
             $b=1;
        }
        if (!$req and $_REQUEST) {
            $req = $_REQUEST;
        }
        if (!$files and $_FILES) {
            $files = $_FILES;
        }
        if ($url) {
            $x = fun::injectionPattern($url);#check the uri along with query string .. avoiding injection via rewriting within where like requests ..
            if ($x) {
                return 'injection pattern ' . $x . ' in url ' . $url;#and querystring
            }
        }

        if ($rawBody) {
            $x = fun::injectionPattern($rawBody,'rawbody');#check the uri alondg with query string
            if ($x) {
                return 'injection pattern ' . $x . ' in rawBody';
            }
        }

        if ($req && 'query string parameters goes here ..') {
            foreach ($req as $k => $v) {
                if (in_array($k, ['contacts_societe', 'contacts_message'])) {
                    continue;
                }#skip those
                $x = fun::injectionPattern($v);
                if ($x) {
                    return 'injection pattern k:' . $x . ' in ' . $v;
                }
                $x = fun::injectionPattern($k);
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
            $foundUploads = fun::searchInArrayDepths($files, ['name'], '~\.php~');
            if ($foundUploads) {
                return 'deep file upload: ' . json_encode($foundUploads);
            }*/
        }
        return false;#clear :)
    }

    static function injectionPattern($x,$type='')
    {
        /* recursive returns first positive match */
        if (is_array($x)) {
            foreach ($x as $v) {
                $res = fun::injectionPattern($v);
                if ($res && 'returns first found') {
                    return $res;
                }
            }
            return false;
        }

        /* most common possible injection patterns '--', '||',  'grant ','create ',  */
        $sqlInjectionPatterns = [ 'sleep(', 'GET_HOST_NAME', 'drop ', 'truncate ', ' delete ', 'cast(', 'ascii(', 'char(', '<script', '<ifram', '<img'];
        if($type != 'rawbody'){$sqlInjectionPatterns+=['/*', '*/', '@@',];}#plupl
        foreach ($sqlInjectionPatterns as $v) {
            if (stripos($x, $v) !== false) {
                return $v;
            }
        }

        if (Preg_Match("~' *or|\" *or|or *1 *= *1|union *all~i", $x, $m) && !Preg_Match("~[l|d]' *or~i", $x, $m) && 'pas anodin ..') {
            return $m[0];
        }

        if (Preg_Match("~url\(|data:image|/png;|base64,|option=com_xmap&view=xml&tmpl=component~i", $x, $m)) {
            return $m[0];
        }
        if (Preg_Match("~_users|\~root|print-439573653|/RK=|/RS=|concat\(|0x3a,password,usertype\)|http://http://|\*!union\*|plugin=imgmanager|w00tw00t|zologize/axa|HNAP1/|admin/file_manager|%63%67%69%2D%62%69%6E|%70%68%70?%2D%64+|cash+loans+|webdav/|cgi-bin|php?-d|union%20all%20select|convert%28int%2C~i", $x, $m)) {
            return $m[0];
        }
        $phps=explode(';;','<?php ;;$_SERVER[\'DOCUMENT_ROOT\'];;accesson.php');
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
        fun::gt('_die, breakpoint here is a good idea');#caught at shutdown function, is neat :)
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
        header('HTTP/1.0 404 Not Found', 1, 404);
        fun::_die('/* <a href="/">not found : ' . trim($x, ' */') . ' </a><script>location.href="/#' . str_replace('"', '', $x) . '";</script>*/');
    }

    static function hl($a = '', $b = true, $c = null)
    {
        return header($a, $b, $c);
    }

    static function r302($x = '', $virtual = 0)
    {
        if ($virtual) {
            return "r302::$x";
        }
        fun::hl('Location: ' . $x, 1, 302);
        fun::_die();
    }

    static function dbm($x, $sub = null, $f = null)
    {#todo:if config send debug to url ....
        if (!fun::getConf('sendLogs')) {
            return;
        }
        #return;
        if (0 and (DEV or LOCAL)) {
            return;
        }#$a=1;DEVBREAKPOINT
        $bt = fun::bt(1);
        if (!$sub) {
            $sub = $_ENV['h'] . ' debug';
        }

        $json = ['host' => fun::getConf('host'), 'type' => 'debug', 'k' => $sub, 'k2' => $_ENV['h'] . $_ENV['u'], 'v' => $x];
        $pk = md5(fun::getConf('logCollectorSecret') . date(str_replace("%", "", fun::getConf('logCollectorSeed'))));
        $headers = ["Cookie: pk=" . $pk . ';XDEBUG_SESSION=1'];
        $url = fun::getConf('logCollectorUrl');
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
        $_sent = fun::cuo($opt);
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
        $_sent = fun::cup($url, $opt, $post, $headers, 1);
        return;
        /*
        foreach($_ENV['debugMails'] as $mail){
            wmail($mail, $sub, '<pre>' . $sub . ' -- ' . date('YmdHis') . ' ' . $_ENV['h'].$_ENV['u'] . "  {\n" . print_r(compact('x', 'bt') + ['host' => $_ENV['h'], 'post' => $_POST, 'get' => $_GET, 'cook' => $_COOKIE, 'ip' => $_ENV['IP']], 1));
        }*/
        fun::db($x, $f);
    }

    static function db($x, $f = null)
    {
        if (!$f) {
            $f = ini_get('error_log');
        }
        if (strpos($f, $_ENV['lp']) === false) {#anom.log
            $f = $_ENV['lp'] . $f;
        }
        $bt = fun::bt(1);
        io::fpc($f, "\n\n}" . date('YmdHis') . ' ' . $_ENV['h'] . '/' . $_ENV['u'] . "{" . print_r(compact('x', 'bt'), 1) . json_encode(array_filter(['post' => $_POST, 'get' => $_GET, 'cook' => $_COOKIE, 'ip' => $_ENV['IP']]), 1) . "\n\n", 8);
    }

    static function arrayContains($array, $contains = 0, $lv = 0, $bk = [])
    {
        $found = [];
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $found = array_merge($found, fun::arrayContains($v, $contains, $lv + 1, array_merge($bk, [$k])));
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
                    $c1 = count(fun::arrayContains($array[$key], $contains, 0, $key));
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
                $e = fun::searchInArrayDepths($v, $keys, $contains, $lv + 1, array_merge($bk, [$k]));#search deeper
                if ($e) {
                    $found = array_merge($found, $e);
                    #_die("found::".$found);
                }
            }
        }
        return $found;
    }

    static function curlFile($url, $file, $name = '', $headers = [])
    {
        #die(realpath($file));
        if (!$name) {
            $name = basename($file);
        }#enctype : multipoart
        #$files=['file' => '@' . realpath($file).';filename='.$name];#does not sends files
        $files = ['file' => curl_file_create($file, '.jpg', $name)];#gives : error: operation aborted by callback
        return fun::cup(['url' => $url, 'post' => $files, 'headers' => ['content-type: multipart/form-data'], 'headers' => $headers]);
    }

    static function cup($url, $opt = [], $post = [], $headers = [], $timeout = 10, $unsecure = 1, $forcePort = 0)
    {
        if (is_array($url)) {
            extract($url);
        }
        $ch = \curl_init();
        $headers[] = 'Expect:';/*100 header*/
        if (isset($opt[CURLOPT_URL]) and $opt[CURLOPT_URL]) {
            $url = $opt[CURLOPT_URL];
        }
        $opts = [CURLOPT_URL => $url, CURLOPT_HEADER => 1, CURLINFO_HEADER_OUT => 1, CURLOPT_VERBOSE => 1, CURLOPT_RETURNTRANSFER => 1, CURLOPT_AUTOREFERER => 1, CURLOPT_FOLLOWLOCATION => 1, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_HTTPHEADER => $headers];
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
        \curl_setopt_array($ch, $opts);
        $result = \curl_exec($ch);
        $info = \curl_getinfo($ch);
        $error = \curl_error($ch);
        \curl_close($ch);
        $header = substr($result, 0, $info['header_size']);
        $contents = substr($result, $info['header_size']);
        return compact('contents', 'header', 'info', 'error', 'opts');
    }

    static function cuo($opts)
    {
        $curl = curl_init();
        curl_setopt_array($curl, $opts);
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
        return debug_backtrace(2);
    }

    static function getConf($k = null)
    {
        if (!static::$conf) {
            #if (!isset($_ENV['alpTechConf'])) {
            $conf=[];
            if(1 and isset($GLOBALS['argv'])/* and!isset($_SERVER['DOCUMENT_ROOT'])*/){
                $_SERVER['DOCUMENT_ROOT']= __DIR__ . '/../../app/';
                $_SERVER['HTTP_HOST']='superwebsite.com';
                $_SERVER['REQUEST_SCHEME']='https';

                $f = __DIR__ . '/cli.conf.php';
                if (!is_file($f)) {
                    copy(__DIR__ . '/default.cli.conf.php', $f);#is setup
                }
                $conf=require_once $f;
                if($conf['cliHost'])$_SERVER['HTTP_HOST']=$conf['cliHost'];
                if($conf['cliDocRoot'])$_SERVER['DOCUMENT_ROOT']=$conf['cliDocRoot'];
            }

            $f = __DIR__ . '/conf.php';
            if (!is_file($f)) {
                copy(__DIR__ . '/default.conf.php', $f);#is setup
            }
            $new=require_once $f;
            $conf+=$new;
            fun::setStatic('conf', $conf);
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
        $isThumbnailRoute = fun::isThumbnailRoute($url, $virtual);
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
        $mediaTypes = explode(',', fun::getConf('mediaTypes'));
        $ext = fun::getExtension($url);
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
        $s = fun::getConf('pathSeparator');
        $url = fun::firstBefore($url, ['?', '#']);
        if (fun::isMedia($url) and strpos($url, $s) and preg_match('~' . fun::getConf('thumbnailsDir') . '(.*)~i', $url, $m)) {
#purge querystring, then hashtag
            $filepath = $m[1];
            $target = $_ENV['dr'] . ltrim($url, '/');

            $width = $height = null;#final public path, is expected target for thumbnail

            preg_match('~' . $s . 'w([0-9]+)~', $m[1], $w);
            preg_match('~' . $s . 'h([0-9]+)~', $m[1], $h);

            if ($w && $w[1] && (int)$w[1]) {
                if (!in_array((int)$w[1], fun::getConf('thumbAuthorizedWidths'))) {
                    #return;
                }
                $filepath = str_replace($w[0], '', $filepath);#strip out parameter
                $width = (int)$w[1];
            }
            if ($h && $h[1] && (int)$h[1]) {
                if (!in_array((int)$w[1], fun::getConf('thumbAuthorizedHeights'))) {
                    #return;
                }
                $filepath = str_replace($h[0], '', $filepath);#strip out
                $height = (int)$h[1];
            }

            if (!$width and !$height) {
                return fun::r302('/' . $url . '#original picture : as no width, nor height specified', $virtual);
            }

            $originalFile = ltrim(str_replace($s, '/', $filepath), '/');
            $finalExt = fun::getExtension($originalFile);

            $opaths = array_filter(array_unique([$originalFile, str_replace('.webp', '', $originalFile)]));#trick is to append .webp at the end of the path ..
            foreach ($opaths as $opath) {
                $opath = $_ENV['dr'] . $opath;
                if (is_file($opath)) {#générer la thumb ici - once and for all !!
                    #$b = thumbnail($opath, $width, $height);
                    try {
                        $b = fun::resizeImage(['ext2' => $finalExt, 'filename' => $opath], $width, $height, $target);
                        if ($b) {
                            return fun::r302($url . '#?generated=' . date('YmdHis') . '#', $virtual);
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
            return fun::r404m($virtual);
        }
        return;
        #return fun::thumbnailFileName($file, 0, 0);
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
            return __function__ . '/' . fun::getConf('defaultImage');
        }
        header('Content-type: image/png');
        header('HTTP/1.0 404 Not Found', 1, 404);
        readfile(fun::getConf('defaultImage'));
        fun::_die();
        #fun::die("/*$x*/");
    }

#fun::resizeImage(['ext2'=>'webp','filename'=>])..
    static function resizeImage($filename, $w = null, $h = null, $target = null)
    {
        #was thumbgen::main(compact('filename','target','h','w'));#list($cuwidth, $cuheight) = getimagesize($filename);
        #global $debug;
        $ts = 2;
        $quality = 70;#jpeg
        $pngq = 9; //0 : no compression, 9 :best
        $owidth = $oheight = $ext = $posy = $posx = $srcx = $srcy = $ext2 = null;

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

        if($target){
            $ext2=fun::getExtension($target);
            $ext=$ext2;
            switch ($ext2) {
                case 'webp':$image_save_func = 'imagewebp';$quality = 80;break;
                case 'jpg':$image_save_func = 'imagejpeg';$quality = 70;break;
                case 'png':$image_save_func = 'imagepng';$quality = $pngq;break;
                case 'gif':$image_save_func = 'imagegif';break;
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
                    $hex2rgb = $this->hex2rgb2($background_color);
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
            $count = getPixelCountByColor($tmp, 16777215);
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
                $count = getPixelCountByColor($tmp, 16777215);
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

        fun::makereps($target);#construire l'arboresence si manquante
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
        $td = fun::getconf('thumbnailsDir');#y/thumbs
        $defaultImage = fun::getconf('defaultImage');#y/default.png

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
        parse_str('image=' . $img, $m);
        $m['width'] = (isset($m['width'])) ? $m['width'] : 0;
        $m['height'] = (isset($m['height'])) ? $m['height'] : 0;
        return fun::thumbnailFileName($m['image'], $m['width'], $m['height']);
    }

    /*accessed via __callStatic*/
    private function privateMethod($a)
    {
        return json_encode([__function__, $a]);
    }

    /*
    $privateClass=new privateClass();
    list($reflect,$methods,$props,$values)=fun::privateAccess($privateClass);
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
            $privateVariablesAndMethods['vars'][$prop->getName()] = $prop->getValue($object);
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
            } elseif ($v and in_array($v, ['NOW()', 'now()'])) {
                ;
            }#keep as
            elseif ($v and !is_numeric($v)) {
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
        $values = fun::insert4row($z);
        $keys = implode(',', array_keys($z));
        return "($keys) values $values";
    }

    /* simple wrappers */
    static function alertMail($sub = '', $msg = '', $to = null, $head = '')
    {
        if (!$to) {
            $to = fun::getConf('defaultWebmasterEmail');
        }
        #'alert:'.$data['host'].' disk usage '.$data['v'],'msg',
        $s = "\r\n";
        if (strpos($head, 'From:') === false) {
            if (!$from) {
                $from = fun::getConf('defaultWebmasterTitle') . ' <' . $to . '>';
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
        $ips = fun::getConf('ip2hostname');
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
                $z2 = fun::var2bash($v, $prefix . '[' . $k . ']', $lv + 1);
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
                $ext = fun::getExtension($f);
                if ($ext == 'sql') {
                    $x = explode("\n", io::fgc($d . $f));
                    foreach ($x as $__line => $v) {
                        $v = rtrim($v, "\t\s\n\r;- ");
                        if (strlen($v) < 10) {
                            continue;
                        }#saut de ligne
                        $ok[] = fun::sql($v);
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

#fun::sql(['sql'=>'request','s'=>compact('h,u,p,db,names']);
    static function sql($sql,$conf='mysql')
    {
        $s = fun::getConf($conf);
        $names = $s['names'];
        if (is_array($sql)) {
            extract($sql);
        }#overrides
        $k = 'sqlc:' . $s['h'] . ':' . $s['db'] . ':' . $names;
        if (!isset($_ENV[$k])) {#mysqlclose on shutdown
            $_ENV[$k] = mysqli_connect($s['h'], $s['u'], $s['p']);
            mysqli_select_db($_ENV[$k], $s['db']);
            if ($names) {
                $ok = mysqli_query($_ENV[$k], 'SET NAMES ' . $names);
                $a = 1;
            }#db encoding
        }
        if (0 and $names and $names != $_ENV[$k]) {
            mysqli_query($_ENV[$k], 'SET NAMES ' . $names);#db encoding
        }

        $x2 = mysqli_query($_ENV[$k], $sql);
        $err = \mysqli_error($_ENV[$k]);
        if ($err) {
            $_ENV['_err']['sql'][$sql] = $err;
            $a = 1;
            $d=debug_backtrace(-2);
            $c=[$_SERVER['REQUEST_URI'],$_COOKIE,$_POST];
            fun::dbm(compact('sql','err','c','d'),'sqlerror');
            if (isset($_ENV['dieOnFirstError'])) {
                print_r(compact('err','sql','d'));
                fun::_die('first sql error');
            }
            return [];
        }
        if(isset($_ENV['stop']) and $_ENV['stop']){$_ENV['stop']=0;
            $a=1;
        }
        if (Preg_match("~(create|update|alter|delete|replace) ~i", $sql)) {
            $_ENV['sqlm'][] = $sql;
            $nb = Mysqli_affected_rows($_ENV[$k]);
            if (!$nb) {
                return -999;
            }
            return $nb;
        } elseif (Preg_match("~insert ~i", $sql)) {
            $_ENV['sqlm'][] = $sql;
            $id = Mysqli_insert_id($_ENV[$k]);
            if (!$id) {
                return -999;
            }
            return $id;
        }

        if (is_bool($x2)) {#use
            return $sql;
        }

        $res = [];
        if ($x2) {
            while ($x = @mysqli_fetch_assoc($x2)) {
                $res[] = $x;
            }
        }
        if(isset($_ENV['stop']) and $_ENV['stop']){$_ENV['stop']=0;
            $reproductible=json_encode([$res,$sql]);
            $a=1;
        }
        return $res;
    }

    static function nocache()
    {
        header("Expires: on, 23 Feb 1983 19:37:15 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    static function sendMail($to,$sub,$body,$head=null,$from=null,$mid=''){
        $s="\r\n";
        $sub='=?UTF-8?B?' . base64_encode($sub) . '?=';
        if (preg_match("~Message-ID: ([^\r\n]+)~i",$head,$m) and $m[1]){$mid=$m[1];}
        else{$mid=preg_replace('~[^a-z0-9]+~i','',md5(time().$to.$sub.$body));$head .="Message-ID: ".$mid.$s;}#generates messageId if absent
        if (strpos($head, 'text/html') === false){$head .= "MIME-Version: 1.0{$s}Content-type: text/html; charset=utf-8{$s}";}#            iso-8859-1   #make html as default :)
        if (!$from and preg_match("~From:[^\r\n]+<([^>]+)>~i",$head,$m) and $m[1]) {#from.$to
            $from=trim($m[1],'> ');
        } elseif (!$from and preg_match("~From: ([^\r\n]+)~i",$head,$m) and $m[1]) {#from.$to
            $from=trim($m[1],'> ');
        } elseif (strpos($head, 'From:') === false) {
            if (!$from) {
                $from = fun::getConf('defaultSenderMail');
            }
            $head .= "From: $from{$s}Reply-To: $from{$s}";
        }

        $sp=fun::getConf('mailSavePath');
        $sent=mail($to,$sub,$body,$head);
        if($sp){#todo:query postfix for messageId
            $f=$_SERVER['DOCUMENT_ROOT'].$sp.substr(preg_replace('~_+~','_',preg_replace('~[^a-z0-9@\.\-]~is','_',$mid.'-_-'.$to.'-_-'.time().'-_-'.$sub)),0,250).'.json';#
            $_written=file_put_contents($f,json_encode(compact('sent','to','sub','body','head')));
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

    static function getBody(){
        if(isset($_ENV['phpinput']) and $_ENV['phpinput'])return $_ENV['phpinput'];#once then destroy
        $_ENV['phpinput'] = trim(file_get_contents('php://input', false, stream_context_get_default(), 0, $_SERVER['CONTENT_LENGTH']), "\n\r \0");
        return $_ENV['phpinput'];
    }


/*}from base{*/
    static function setStatic($a, $b)
    {
        static::${$a}=$b;
    }

    static function getStatic($a)
    {
        static::${$a};
    }
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
/*}end base methods{*/
    static function stripAccents($str,$utf=1) {#utf0 if opening a windows encoded file
        if($utf) return strtr($str, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
        #operates is ascii context (latin1)
        return strtr(utf8_decode($str), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
    }

    static function distance($lat1,$lat2,$lng1,$lng2)
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

    static function shortDist($olat,$olon,$dist){
        $latDegDist=fun::distance($olat,$olat+1,$olon,$olon);#updown::  111 km par deg
        $lonDegDist=fun::distance($olat,$olat,$olon,$olon+1);#rightleft:: 77 km
        $dlon=$dist/$lonDegDist;
        $dlat=$dist/$latDegDist;
        $rect=[$olat-$dlat,$olat+$dlat,$olon-$dlon,$olon+$dlon];
        return $rect;
    }

    static function addPhotoWaterMark($baseImg = null, $sign = null, $target = null, $position = 'br', $edgeMargin = 10, $quality = 90, $maxW = 100, $maxH = 100)
    {
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
            $res = imagecopyresampled($tmp, $stamp, $destX, $destY, $srcx, $srcy, $nw, $nh, $sx, $sy);
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

}

return; ?>

TODO: myError, myException, découpage io::fpc
