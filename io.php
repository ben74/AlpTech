<?php
namespace Alptech\Wip;

/* simplest I/O wrapper */
if(!defined('DEV'))define('DEV',1);
class io
{
    static function fap($file, $contents)
    {
        return io::fpc($file, "\n" . $contents, 8);
    }

    static function fgc($file, $include_path = false, $context = null)
    {
        $return = @file_get_contents($file, $include_path, $context);
        return $return;
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
        return io::isJson(io::fgc($f), $as_array);
    }

    static function FPCJ($f, $d)
    {
        if (in_array(gettype($d), ['object', 'array']) or 1) {
            $d = json_encode($d);
        }#logiquement, mais si déjà encodé ?
        return io::FPC($f, $d);
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
            io::fap(fun::getConf('logs') . '.json.log', "\n" . print_r($exception, 1), 8);
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
            io::fap(fun::getConf('logs') . '.json.log', "\n" . implode(',', $chars), 8);
            return [];
        }

        if (json_last_error() != JSON_ERROR_NONE) {
            return [];
        }
        return $x;
    }
}

return; ?>
