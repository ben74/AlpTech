<?php
/*
 * $_ENV['conf']['nostats']=1;//reduces memory usage : no mysql results keps
 */
namespace Alptech\Wip;

class user2 extends model /* extends base */
{
    static $table = 'user2', $attributes = ['id', 'name'];
    static function table(){return static::$table;}//$path = explode('\\', __CLASS__);return array_pop($path);
}

return; ?>
