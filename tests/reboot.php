<?php
include __DIR__."/../vendor/autoload.php";
$daemon1 = longmon\php\Daemon::reboot("phpdaemon", 3, function(){
	echo "This is a reboot process\n";
	sleep(10);
});