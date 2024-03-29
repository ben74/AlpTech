<?php
namespace Alptech\Wip;

/* simplest I/O wrapper */
if(!defined('DEV'))define('DEV',1);
class io
{
    static function fap($file, $contents)
    {
        return static::fpc($file, "\n" . $contents, 8);
    }

    static function fgc($file, $include_path = false, $context = null)
    {
        $return = @file_get_contents($file, $include_path, $context);
        return $return;
    }

    static function FPP($f,$data){
        return file_put_contents($f,'<?php return '.static::var_export_min($data, true).';');
    }

    static function var_export_min($var, $return = true) {
        if (is_array($var)) {
            $toImplode = [];
            $ak=array_keys($var);$linear=false;if($ak[0]===0 && end($ak)==count($ak)-1){
                $linear=true;
            }
            foreach ($var as $key => $value) {
                if($linear){
                    $toImplode[] = static::var_export_min($value, true);
                } else{
                    $toImplode[] = var_export($key, true) . '=>' . static::var_export_min($value, true);
                }
                //$toImplode[] = var_export($key, true).'=>'.static::var_export_min($value, true);
            }
            $code = '['.implode(',', $toImplode).']';
            if ($return) return $code;
            else echo $code;
        } else {
            return var_export($var, $return);
        }
    }

    static function FPC($f, $d, $o = null)
    {
        $f = str_replace('c:/home/', '', $f);#loclahost
        static $rec;
        $rec++;
        if (DEV and $rec > 2) {
            $_bt = debug_backtrace(-2);
            $err = 'recursivity';
        }
        $path = explode('/', $f);
        $end = array_pop($path);
        $folder = implode('/', $path);
        if ($folder and !is_dir($folder)) {#/logs/c:/home/
            $ok = mkdir($folder, 0777, 1);
            if (!$ok) {#caution ::: LLOOPSSS !!!
                fun::db('cant mkdir ' . $folder, 'anom.log');
            }
        }
        $rec--;
        return file_put_contents($f, $d, $o);
    }

    static function fgcj($f, $as_array = 1)
    {
        return static::isJson(static::fgc($f), $as_array);
    }

    static function FPCJ($f, $d)
    {
        if (in_array(gettype($d), ['object', 'array']) or 1) {
            $d = json_encode($d);
        }#logiquement, mais si déjà encodé ?
        return static::FPC($f, $d);
    }

    static function isJson($string = '', $asArray = 1)
    {
        if (!trim($string)) {
            return [];
        }
        try {
            $x = @json_decode($string, $asArray);
            $a=1;
        } catch (\JsonException $exception) {
            static::fap(fun::getConf('logs') . '.json.log', "\n" . print_r($exception, 1), 8);
            $_ENV['_err']['jsondecode'][] = $exception;
            return [];
            echo $exception->getMessage(); // displays "Syntax error"
        }
        if (!$x) {#Control character error, possibly incorrectly encoded[
            $e = json_last_error();
            $m = json_last_error_msg();
            $x = substr($string, 7, 10);
            $chars = str_split($x);
            foreach ($chars as &$v) {
                $v = ord($v);
            }
            unset($v);
            $_ENV['_err']['jsondecode'][] = compact('e', 'm', 'chars', 'string');
            static::fap(fun::getConf('logs') . '.json.log', "\n" . implode(',', $chars), 8);
            return [];
        }

        if (json_last_error() != JSON_ERROR_NONE) {
            return [];
        }
        return $x;
    }
}

return; ?>
