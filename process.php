<?php

class Process{
	public function __construct(){
		swoole_process::daemon();
		swoole_set_process_name("phpProcess master");
		$this->create_proc();
		swoole_process::signal(SIGCHLD, function($sig){
			$ret = swoole_process::wait();
			print_r($ret);
		});
	}
	
	public function create_proc(){
		$proc = new swoole_process(function(swoole_process $worker){
			$worker->name("phpProcess worker");
			for(;;){
				echo "Hi,i am child proc\n";
				sleep(3);
			}
		}, false);
		$pid = $proc->start();
		return $pid;
	}
	
	public function wait(){
		$ret = swoole_process::wait();
		var_dump($ret);
	}
}

new Process;