<?php
include __DIR__."/../vendor/autoload.php";
$daemon1 = longmon\php\Daemon::singleTaskInstance("phpdaemon", 3);
$daemon1->run(function(){
	echo "This is single task!\n";
	sleep(10);
});