<?php

return array(
	"proc_num" => 5,
	"log_dir" => "./log",
	"pid_file" => "./log/php-daemon.pid",
	"task" => function(){
		echo "php daemon echo\n";
		sleep(10);
	}
);
