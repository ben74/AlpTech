<?php
namespace Alptech\Wip;

class spark
{
    static function init()
    {
        if (static::$init) {
            return;
        }#already done
        static::$init = 1;
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE & ~E_WARNING);
        spark::registerAutoloader();#above all, could be individual and flexiblee !
        $_ENV['stopOthersShutdowns'] = 1;
        register_shutdown_function(__CLASS__ . '::shutdown');#Above,__NAMESPACE__ .'\
        static::$oldExceptionHandler = set_exception_handler(__CLASS__ . '::exception'); #restore_error_handler();#préférence pour le dernier déclaré
        static::$oldErrorHandler = set_error_handler(__CLASS__ . '::error');#['class','function'] ini_set('log_errors',0);

#$_ENV=[];
        define('CLI', isset($GLOBALS['argv']));
        define('HTML', !CLI);
#phpx ~/home/d9/vendor/alptech/wip/cli.php "?host=yo&url=a&body={\"pop\":1}&dr="fr"&ip=127&cookies[a]=1&cookies[b]=2&post[a]=1&get[b]=2&request[c]=3"
        if (CLI) {
            $qs = null;
            $_SERVER['HTTP_HOST'] = fun::getConf('defaultHost');
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            $_SERVER['REQUEST_URI'] = implode(',', $GLOBALS['argv']);
            $_SERVER['DOCUMENT_ROOT'] = getcwd();
            if (count($GLOBALS['argv']) == 2 and (parse_str($GLOBALS['argv'][1], $qs) or 1) and $qs) {
                $_SERVER['QUERY_STRING'] = $GLOBALS['argv'][1];
                foreach ($qs as $k => $v) {
                    if ($k == 'body') {
                        $_ENV['phpinput'] = trim($v);
                    } elseif ($k == 'dr') {
                        $_SERVER['DOCUMENT_ROOT'] = $v;
                    } elseif ($k == 'host') {
                        $_SERVER['HTTP_HOST'] = $v;
                    } elseif ($k == 'url') {
                        $_SERVER['REQUEST_URI'] = $v;
                    } elseif ($k == 'get') {
                        $_GET = $v;
                    } elseif ($k == 'post') {
                        $_POST = $v;
                    } elseif ($k == 'request') {
                        $_REQUEST = $v;
                    } elseif ($k == 'cookies') {
                        $_COOKIE = $v;
                    } else {
                        $_GET[$k] = $v;
                        continue;#légitimate other get parameters
                    }
                    #$_SERVER['QUERY_STRING'] = str_replace(['?'.$k])
                }#?host=yo&url=a&body={"pop":1}&dr=fr&ip=127&cookies[a]=1&cookies[b]=2&post[a]=1&get[b]=2&request[c]=3
                $_SERVER['QUERY_STRING'] = http_build_query($_GET);
                #then launch the router with all those parameters if it ever crosses a route
                #$a='rebuild $_SERVER[\'QUERY_STRING\'] from $_GET only';
            }
        } elseif ('isHTMLrequest') {
            $c = [];
            foreach ($_COOKIE as $k => $v) {
                $c[] = "$k=$v";
            }
            $_ENV['__replay'] = "cuj \"https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\" '' '" . str_replace(["'", '&', '!'], [' ', '\&', '\!'], json_encode($_POST)) . "' 1 \"" . implode(';', $c) . "\"";
        }

        if ('parseBodyRequest, caution, clears it afterwards ..') {
            if (isset($_ENV['phpinput']) and $_ENV['phpinput']) {
                $inputJSON = $_ENV['phpinput'];
            } else {
                $_ENV['phpinput'] = $inputJSON = trim(file_get_contents('php://input'),"\s\n\r\0 ");
            }#could be regular postdata .. what for cli ?
            if (in_array(substr($inputJSON, 0, 1), ['[', '{'])) {
                $x = io::isJson($inputJSON);
                if ($x) {
                    if (!$_POST) {
                        $_POST = [];
                    }
                    $_POST = array_merge($x, $_POST);
                    if (!$_REQUEST) {
                        $_REQUEST = [];
                    }
                    $_REQUEST = array_merge($_REQUEST, $x);
                }
                if (isset($_POST[$inputJSON])) {
                    unset($_POST[$inputJSON]);
                }
            }
        }

        $_ENV['dr'] = $_SERVER['DOCUMENT_ROOT'];
        $_ENV['h'] = $_SERVER['HTTP_HOST'];
        $_ENV['exc'] = [];#exceptions within functions
        $_ENV['ext'] = fun::getExtension();
        $_ENV['IP'] = $_SERVER['REMOTE_ADDR'];
        define('DEV', in_array($_ENV['IP'], fun::getConf('devIps')));
        define('LOCAL', in_array($_ENV['IP'], fun::getConf('localhostIps')));
        #$_ENV['dr']=__DIR__.'../../..';
        fun::needsMigration();
        return;
    }

    static function shutdown()
    {
        $_e = error_get_last();
        if ($_e and !in_array($_e['type'], explode(',', '8'))) {
            $a = 1;
        }
    }

    static function registerAutoloader()
    {
        #spl_autoload_register(['\Alptech\Wip\autoloader', 'autoload'], 1, 1);#above all
        spl_autoload_register(__CLASS__ . '::autoload', 1, 1);
    }

    static function autoload($name)
    {
        if (stripos($name, __NAMESPACE__) === false) {
            return;
        }
        $name = str_replace('\\', '/', $name);
        $x = explode('/', $name);
        $f = __DIR__ . '/' . end($x) . '.php';
        if (is_file($f)) {
            require_once $f;
            return $f;
        }
        $_ENV['err']['classNotFound'][] = $f;
        return;
    }

    /*
    register_shutdown_function(
        function () use ($h, $u, $phpVersion, $tmp, $xdf) {
    */
    static function error($no = null, $msg = null, $file = null, $line = null, $plus = null)
    {#2,: warnings
        $a = 1;
        if (in_array($no, [8, 16384]) and 'ignores notices:8, warnings:2, deprecation warnings:16384') {
            return;
        }
        #2-> is notice
        static $call = 0, $errors = [], $memo = [], $reported = [];
        $call++;
        #$file = win2unix($file);
        $f = explode('/', str_replace('\\', '/', $file));
        $f = end($f);
        $err = fun::friendly_error_type($no);
        $u = $_ENV['h'] . $_ENV['u'];
        $e = ['_f' => $f, '_e' => $err, '_l' => $line, 'u' => $u, 'msg' => $msg];
        #if(isset($_GET)and $_GET)$e['g']=$_GET;if(isset($_POST)and $_POST)$e['p']=$_POST;if(isset($_COOKIE)and $_COOKIE)$e['c']=$_COOKIE;#added by dbm
        $a = 'php500£_myError£' . $f . '£' . $err . '£' . $line . '£' . $msg . '£' . $u;#les clés successives, conserver une cohérence entre les deux, afin de pouvoir grouper par la suite les logs
        $f2 = ini_get('error_log');#$f2='phperror.log';
        fun::dbm('', $a, $f2, 0, $e);

        if (in_array($no, [E_ERROR, E_PARSE, E_CORE_ERROR, E_FATAL])) {#E_FATAL
            fun::r404('erreur 500 - désolé pour la gène occassionée');
        }
        return;#not fatal error ;)
    }

    static function exception($exception)
    {
        #get_class($exception)=='ParseError';
        /*
        if (isset($infos[1])) {$this->setModule(array_shift($infos));$message = trim(implode('',$infos));}parent::__construct($message);*/
        try {
            $_type = strtolower(get_class($exception));#parseerror,exception
            $_m = $exception->getMessage();
            #Maximum function nesting level of '500' reached, aborting!
            $_f = $exception->getFile();
            $_l = $exception->getLine();
            /* todo: provide more debug context: $_ENV['replay']*/
            if ($_type == 'parseerror') {
                fun::dbm([$_ENV['h'] . $_ENV['u'], $_m, $_f, $_l], 'erreur500', fun::getConf('errorLog'));
            }

            io::fap(fun::getConf('exceptionsLog'), "}" . date('YmdHis') . "{\t" . $_ENV['h'] . $_ENV['u'] . "\t" . $_m . ' ' . $_f . ' ' . $_l);
            $a = 1;
            #throw new \Exception($_m);#is Fatal or not ?
            #throw new fwException($exception->getMessage());
        } catch (\Exception $e) {
            $a = 1;
            echo $e->getMessage();
        }
    }

    static $oldErrorHandler = false, $oldExceptionHandler = false, $init = false;
}

return; ?>
