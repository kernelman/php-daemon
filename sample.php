<?php
include "Helper.php";
include "daemon2.php";
//longmon\php\Helper::put_contents_to_file("./log/sa.run",["name"=>"longmon"]);exit;


$daemon = longmon\php\Daemon::singleTaskInstance("Sample", 10);
$daemon->run(function(){
	echo "sample\n";
	sleep(10);
});
