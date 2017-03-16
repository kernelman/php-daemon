<?php

/**
 * 配置规则
 * array(
 *     "subproc_name" => [$class, $method, $params], 
 *     "subproc_name2" => function(){} #闭包
 * )
 */
return array(
	"task1" => [
		"task" => function(){
			file_put_contents("./log/task1","halo task1, longmon\n", FILE_APPEND);
			sleep(10);
		},
		"proc_num" => 3
	],
	"task2" => [
		"task" => ["\longmon\php\\tests\\task2","main","longmon"],
		"proc_num"=>5
	]
);
