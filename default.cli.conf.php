<?php

namespace Alptech\Wip;

if(isset($_SERVER['WINDIR'])){
    return ['cliHost' => 'https://superwebsite.home/', 'cliDocRoot' => 'C:\Users\ben\home\superwebsite/'];
}#else is production
return ['cliHost' => 'https://superWebsite/', 'cliDocRoot' => '/home/superwebsite/'];
