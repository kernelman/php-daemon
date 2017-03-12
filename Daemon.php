<?php namespace longmon\php;

/**
  * php-daemon
  * A lib for php daemon in cli mode;
  * License MIT
  * copyright (c) 2017 longmon <1307995200@qq.com>
  * Link : www.spwktg.com
  */
  
use longmon\php\Helper;

class Daemon{

	private $index = 0;
	private $works = array();
	private $proc_num = 10;
	private $master_pid  = 0;

	private $log_file = "./log/run.log";
	private $name = "php-daemon";
	private $conf_dir = "./conf";

	public static $conf = array();
	
	public static function newInstance( $name = NULL, $debug = true ){
		return new self( $name, $debug );
	}

	/**
	 * @param string $name  main process name
	 * @param boolean $debug set debug mode
	 */
	private function __construct( $name = NULL, $debug = true ){
		//detect swoole module if exsists
		if( !function_exists("swoole_set_process_name") ){
			Helper::warning( "swoole module dosen't exists!" );
			exit(1);
		}

		$debug = $debug?true:false;

		define("DEBUG", $debug);
		
		$name && $this->name = $name;

		$this->proc_control($this->name); //process controller
		
		swoole_process::daemon(); //  set daemon mode
		
		swoole_set_process_name(  $this->name. " master" ); //set master process name

		$this->master_pid = posix_getpid(); // get current process id
		
		$this->proc_signal_register();//register signal handlers
		
	}

	public function setopt( array $opt ){
		self::$conf = array_merge( self::$conf, $opt );
	}

	public function run(){
		$this->load_config(); //load configure

		$this->proc_create_worker();
	}

	public function proc_signal_register(){

		swoole_process::signal( SIGUSR2, function($sig){
			Helper::log("Caught a SIGUSR2[{$sig}] signal");
			$this->signal_handler_sigusr2();
		});

		swoole_process::signal( SIGTERM, function($sig){
			Helper::log("Caught a SIGUSR2[{$sig}] signal");
			$this->signal_handler_sigterm();
		});

		swoole_process::signal( SIGCHLD, function($sig){
			Helper::log("Caught a SIGUSR2[{$sig}] signal");
			$this->signal_handler_sigchld();
		});
	}

	public function load_config(){

		$conf = Helper::import( $this->conf_dir."/php-daemon.php");

		self::$conf = array_merge($conf, self::$conf);

		if( isset($self::$conf ['proc_num']) ) {
			$this->proc_num = intval(self::$conf['proc_num']);
		}

		if( isset(self::$conf['log_dir']) ){
			$this->log_file = trim(self::$conf['log_dir']);
		}
	}

	public function proc_create_worker(){
		for( $i = 0; $i < $this->proc_num; $i++ ){
			$this->create_proc( $i );
		}
		return true;
	}

	public function create_proc($index){
		$proc = new swoole_process( function( swoole_process $woker )use($index){

			swoole_set_process_name( $this->name . " worker.{$index}" ); //set subprocess name

			$this->proc_task( $worker ); //doing jobs
		}, false );
		if( ($pid = $proc->start()) > 0 ){
			$this->works[$index] = $pid;
			return $pid;
		}
		return false;
	}

	public function proc_control( $name ){

		Helper::log( __CLASS__." starting ......", $this->log_file );

		$pid_file = dirname($this->log_file)."/{$name}.pid";

		if( $pid = Helper::get_file_contents($pid_file) ){
			if( swoole_process::kill($pid, 0 ) ){
				Helper::log(" failed! ({$name} master process( {$pid}) has been started)\n" $this->log_file, true);
				exit(1);
			}
		}
		Helper::put_file_contents($this->log_file, $this->master_pid );
		Helper::log(" Success!", $this->log_file, true);
		return true;
	}

	public function proc_task( &$worker ){
		for(;;){
			$this->do_task();
			$this->detect_master_proc( $worker );
		}
	}

	public function detect_master_proc( &$worker ){
		if( !swoole_process::kill( $this->master_pid, 0 ) ){
			$worker->exit();
		}
	}

	public function kill_all_proc(){
		foreach ( $this->works as $index => $pid ) {
			swoole_process::kill( $pid, SIGTERM );
		}
	}

	/************************* signal handler list ********************/

	public function signal_handler_sigusr2(){
		$this->kill_all_proc();
		$this->proc_create_worker();
	}

	public function signal_handler_sigterm(){}

	public function signal_handler_sigchld(){}
	
	
}
