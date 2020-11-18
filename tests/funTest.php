<?php
/*
./vendor/bin/phpunit vendor/alptech
./vendor/bin/phpunit security2commit
 */
namespace Alptech\Wip;
#declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class funTest extends TestCase
{
    /** @test */
    public function areBlocked(): void
    {
        $isblocked=fun::blockMaliciousRequests('/GET_HOST_NAME?truncate');
        $this->assertEquals('injection pattern GET_HOST_NAME in url /GET_HOST_NAME?truncate', $isblocked);
        $isblocked=fun::blockMaliciousRequests('','Body contains GET_HOST_NAME');
        $this->assertEquals('injection pattern GET_HOST_NAME in rawBody', $isblocked);

        $isblocked=fun::blockMaliciousRequests('','',['a'=>'GET_HOST_NAME']);
        $this->assertEquals('injection pattern k:GET_HOST_NAME in GET_HOST_NAME', $isblocked);
        $isblocked=fun::blockMaliciousRequests('','',['GET_HOST_NAME'=>1]);
        $this->assertEquals('injection pattern v:GET_HOST_NAME in GET_HOST_NAME', $isblocked);

        $files=json_decode('{"file":{"name":"shell.php","type":".jpg","tmp_name":"phpA0BD.tmp","error":0,"size":2661}}',1);
        $isblocked=fun::blockMaliciousRequests('',null,null,'',$files);
        $this->assertEquals('file upload: "name":"shell.php"', $isblocked);
    }
}
return;?>
