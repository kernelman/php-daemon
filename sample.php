<?php
include "Helper.php";
include "Daemon.php";
//longmon\php\Helper::put_contents_to_file("./log/sa.run",["name"=>"longmon"]);exit;


$daemon1 = longmon\php\Daemon::singleTaskInstance("phpdaemon", 3);
$daemon1->run(function(){
	echo "phpdaemon\n";
	sleep(10);
});

$daemon2 = longmon\php\Daemon::singleTaskInstance("phpdaemon", 3);
$daemon2->run(function(){
	echo "daemon2\n";
	sleep(10);
});
exit;
$daemon3 = longmon\php\Daemon::singleTaskInstance("hahahaha", 3);
$daemon3->run(function(){
	echo "hahahaha\n";
	sleep(10);
});

$daemon4 = longmon\php\Daemon::singleTaskInstance("hahahaha", 3);
$daemon4->run(function(){
	echo "damndamndamn\n";
	sleep(10);
});
