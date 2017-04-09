<?php

if( $argc < 2 ){
	exit("Usage tests/test.php [name]\n");
}

$action = $argv[1];


include "vendor/autoload.php";

$name = "php Daemon";

longmon\Php\Daemon::$action($name);