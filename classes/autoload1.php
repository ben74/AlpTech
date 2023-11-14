<?php

class autoload1
{
    function __construct($args = [])
    {
        echo"\n".json_encode([__CLASS__ => $args]);
    }
}
