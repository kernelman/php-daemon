<?php namespace longmon\php;

class Daemon
{
	private $index 		= 0;
	private  $master_id 	= 0;
	private $proc_name 	= "php-daemon";
	private $worker 	= array();
	private $logdir		= "/var/log/php-daemon";
	private $pid_file	= "/var/run/";

	private static $conf 	= array(
		"proc_num"	=> 10,
		"task"		=> array($this, "demo_task"),
		"log_file"	=> "/var/log/php-daemon/"	
	);

	public function __construct( $proc_name, array $opt )
	{
		if( !function_exists("swoole_set_process_name") )
		{
			trigger_error("swoole module dose not installed!", E_USER_WARNING );
			exit;
		}
		self::$conf = $opt;

		$this->proc_name = $proc_name?$proc_name:$this->proc_name;

		swoole_process::daemon(); //  set daemon mode

		$this->master_id = posix_getpid();

		swoole_set_process_name( $this->proc_name ." master" );

		$this->concurrent_control(); //并发控制

		$this->register_signal_handler(); //信号控制注册
	}

	public function run(){

	}

}
