<?php

/**
 * 配置规则
 * array(
 *     "subproc_name" => "class::method", 
 *     "subproc_name2" => function(){} #闭包
 * )
 */
return array(
	"resultlog" => "longmon\php\example\\resultlog::main",
	"async" => function(){
		echo "Hi, i am in closure!";
	}
);
