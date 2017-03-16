<?php namespace longmon\php;
/**
 * 常驻进程任务处理。
 * 实现原理：一个主进程进行控制高度，子进程处理实际任务。
 * 子进程数量，处理任务可在进程启动时指定（单任务实例）或使用配置文件（多任务实例）
 * 可实现无停机重启，(多任务实例）重载任务配置
 * 
 * 单例模式 - 同一进程空间不得启动一个以上实例，否则将出现不可预知错误！
 * 
 * 开发需知：任务处理的代码不得出现无限死循环，必须有退出机制，否则，当主进程退出时，子进程可能无法平滑退出
 *
 * @author longmon<1307995200@qq.com>
 * @copyright 2017
 * @license MIT 
 */
use \swoole_process;
class Daemon
{
	private $index 		= 0;
	private $master_pid = 0;
	private $proc_name 	= "php-daemon";
	private $worker 	= array();
	private $logdir		= "./log";
	private $runtime_dir= "./run";
	private $pid_file 	= "./run/php-daemon.pid";
	private $proc_num	= 1;
	private $reboot 	= 0;
	
	private static $task= NULL;   //处理任务
	private static $run_mode = 0; //0表示单任务版本， 1表示多任务版本
	private static $instance = NULL;
	
	/**
	 * 克隆魔术函数
	 * @return [type]                   [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:17:36+0800
	 */
	public function __clone(){
		return NULL;
	}

	/**
	 * 构造函数
	 * @param  string                   $proc_name [description]
	 * @param  integer                  $proc_num  [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:18:17+0800
	 */
	private function __construct( $proc_name, $proc_num = 1 )
	{
		define("DAEMON_PATH", __DIR__);
		
		self::load_config();
		
		if( !Helper::make_dir($this->logdir) || !Helper::make_dir($this->runtime_dir) ){
			exit();
		}
		if( !function_exists("swoole_set_process_name") )
		{
			trigger_error("swoole module dose not installed!", E_USER_WARNING );
			Helper::warning("swoole module dose not installed!", E_USER_WARNING );
			exit;
		}
		swoole_process::daemon(); //  set daemon mode

		$this->proc_num = $proc_num?$proc_num:$this->proc_num;
		
		$this->proc_name = $proc_name?$proc_name:$this->proc_name;
		
		$this->pid_file = $this->runtime_dir."/".$this->proc_name.".pid";
		
		$this->master_pid = posix_getpid();
		
		$this->concurrent_control(); //并发控制
		
		swoole_set_process_name( $this->proc_name ." master process" );

		$this->register_signal_handler(); //信号控制注册
	}

	/**
	 * 开始执行
	 * @param  [type]                   $task [description]
	 * @return [type]                         [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:18:39+0800
	 */
	public function run( $task = NULL )
	{
		if($task && !self::$task ) { self::$task = $task;}
		if( self::$run_mode == 0 ){
			return $this->single_task_run();
		}else{
			return $this->multi_task_run();
		}
	}
	
	/**
	 * 单任务模式入口
	 * @return [type]                   [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:18:53+0800
	 */
	public function single_task_run()
	{
		if( count($this->worker) > 0 ){
			return true;
		}
		if( !self::$task ){return false;};
		for( $i = 0; $i < $this->proc_num; $i++ ){
			$this->create_sub_process($i);
		}
		return true;
	}
	
	/**
	 * 多任务模式入口
	 * @return [type]                   [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:19:08+0800
	 */
	public function multi_task_run()
	{
		if( count($this->worker) > 0 ){
			return true;
		}
		$jobArray = Helper::import( DAEMON_PATH."/job.php" ); //job文件必须返回指定格式的数组
		if(empty($jobArray) || !is_array($jobArray) ){
			return false;
		}
		$no = 0;
		foreach( $jobArray as $name => $job ){
			if(!isset($job['task'])){
				continue;
			}
			$task = $job['task'];
			$proc_num = isset($job['proc_num'])?intval($job['proc_num']):1;
			for( $m = 0; $m < $proc_num; $m++ ){
				$this->create_sub_process($no, $name, $task);
				$no++;
			}
		}
		return true;
	}
	
	/**
	 * 创建子进程
	 * @param  [type]                   $index         [description]
	 * @param  [type]                   $sub_proc_name [description]
	 * @param  [type]                   $sub_proc_task [description]
	 * @return [type]                                  [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:19:24+0800
	 */
	private function create_sub_process( $index, $sub_proc_name = NULL, $sub_proc_task = NULL )
	{
		$worker_name = $this->proc_name.( $sub_proc_name?" ".$sub_proc_name:" ")." worker";
		$task = $sub_proc_task?$sub_proc_task:self::$task;
		
		$proc = new swoole_process(function( swoole_process $worker)use($index, $worker_name, $task){
			
			swoole_set_process_name($worker_name);
			
			$this->register_subproc_signal_handler(); //子进程内监听信号

			$this->task_loop($task, $worker );
			
		}, false, false );
		
		$pid = $proc->start();
		$this->worker[$index] = ["pid"=>$pid, "name"=>$sub_proc_name, "task"=>$sub_proc_task];
		return $pid;
	}
	
	/**
	 * 循环处理任务
	 * 要求任务体内不得出现无限循环的逻辑, 否则子进程将无法退出
	 */
	private function task_loop($task, &$worker )
	{
		for(;;){
			$this->process_task( $task );
			if($this->reboot == 1){
				exit(0);
			}
			$this->detect_master_alive( $worker );
		}
	}
	
	/**
	 * 任务处理
	 */
	private function process_task($callFunc)
	{
		if(is_callable($callFunc) )
		{
			return call_user_func($callFunc);
		}
		else if(is_array($callFunc) && count($callFunc) >= 2 ){
			list($obj, $method, $param ) = $callFunc;
			if( !$param ){
				$param = [];
			}elseif(!is_array($param)){
				$param = [$param];
			}
			if( $obj ){
				return call_user_func_array([$obj, $method], $param);
			}else{
				return call_user_func_array($method, $param);
			}
		}else{
			return false;
		}
	}

	
	/**
	 * 检查主进程是否存活
	 */
	public function detect_master_alive(&$worker)
	{
		if( !swoole_process::kill($this->master_pid, 0) ){
			$worker->exit();
		}
	}
	
	/**
	 * 并发控制处理
	 */
	public function concurrent_control()
	{
		Helper::log("php-daemon starting ...", $this->logdir."/run.log");
		if($pid = Helper::get_pid_from_file($this->pid_file)){
			if( swoole_process::kill($pid, 0) ){
				Helper::log(" failed!(master process has been started with pid:{$pid})\n", $this->logdir."/run.log", true );
				exit(0);
			}
		}
		if( !Helper::write_pid_file($this->pid_file, $this->master_pid) ){
			Helper::log(" failed!(write to pid file failed)\n", $this->logdir."/run.log", true);
			exit(0);
		}
		Helper::log(" Sucess!\n", $this->logdir."/run.log", true);
		return;
	}
	
	public function register_signal_handler()
	{
		swoole_process::signal(SIGUSR1, function($signo){
			Helper::log("Caught a SIGUSR1({$signo}) signal\n", $this->logdir."/run.log");
			$this->reboot_all_process();
		});
		
		swoole_process::signal(SIGUSR2, function($signo){
			Helper::log("Caught a SIGUSR2({$signo}) signal\n", $this->logdir."/run.log");
			$this->kill_all_sub_process();
		});
		
		swoole_process::signal(SIGTERM, function($signo){
			Helper::log("Caught a SIGTERM({$signo}) signal\n", $this->logdir."/run.log");
			Helper::remove_file($this->pid_file);
			swoole_process::kill($this->master_pid, SIGKILL);
		});
		
		swoole_process::signal(SIGCHLD, function($signo){
			Helper::log("Caught a SIGCHLD({$signo}) signal\n", $this->logdir."/run.log");
			while($ret = swoole_process::wait(false)){
				$this->reboot_process($ret['pid']);
			}
		});
	}
	
	private function reboot_all_process()
	{
		foreach($this->worker as $index=>$worker){
			swoole_process::kill($worker['pid'], SIGUSR1);
		}
	}
	
	private function kill_all_sub_process()
	{
		foreach($this->worker as $index=>$worker){
			swoole_process::kill($worker['pid'], SIGTERM);
		}
		$this->worker = [];
		return;
	}
	
	public function reboot_process($pid)
	{
		foreach($this->worker as $index => $worker ){
			if( $pid == $worker['pid'] ){
				$this->create_sub_process($index, $worker['name'], $worker['task']);
			}
		}
	}
	
	public function load_config()
	{
		$conf = Helper::import("conf/php-daemon.php");
		foreach($conf as $k => $v ){
			$this->$k = $v;
		}
		return $conf;
	}
	
	public static function static_get_config()
	{
		return Helper::import("conf/php-daemon.php");
	}
	
	public function register_subproc_signal_handler()
	{
		swoole_process::signal(SIGUSR1, function($sig){
			$this->reboot = 1;
		});
	}
	
	/************************************* 操作API *****************************
	
	/**
	 * 单任务入口
	 */
	public static function start_single_task($proc_name, $proc_num = 1){
		self::$run_mode = 0;
		if( self::$instance == NULL ){
			self::$instance = new self($proc_name, $proc_num);
		}
		return self::$instance;
	}
	
	/**
	 * 多任务入口
	 */
	public static function start_multi_task($proc_name, $proc_num = 1){
		self::$run_mode = 1;
		if( self::$instance == NULL ){
			self::$instance = new self($proc_name, $proc_num);
		}
		return self::$instance;
	}
	
	/**
	 * 重启服务
	 */
	public static function reload($name){
		$conf = self::static_get_config();
		$pid_file = $conf['runtime_dir']."/".$name.".pid";
		$pid = Helper::get_pid_from_file($pid_file);
		swoole_process::kill($pid, SIGUSR1);
	}
	
	/**
	 * 停止服务
	 * @param  [type]                   $name [description]
	 * @return [type]                         [description]
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:48:45+0800
	 */
	public static function stop($name){
		$conf = self::static_get_config();
		$pid_file = $conf['runtime_dir']."/".$name.".pid";
		if($pid = Helper::get_pid_from_file($pid_file))
		{
			swoole_process::kill($pid, SIGTERM);
			return true;
		}else{
			trigger_error("try to kill a unavailable process named {$name}\n", E_USER_WARNING);
			return false;
		}
	}

	/**
	 * 检测主进程是否存活
	 * @param  string $name 启动的时候设置的进程名称 
	 * @return int|bool  存活返回主进程ID，否则返回FALSE
	 *
	 * @author:longmonHou
	 * @since  2017-03-16T15:49:54+0800
	 */
	public static function detect_master_life($name){
		$conf = self::static_get_config();
		$pid_file = $conf['runtime_dir']."/".$name.".pid";
		if($pid = Helper::get_pid_from_file($pid_file))
		{
			if(swoole_process::kill($pid, 0)){
				return $pid;
			}
		}else{
			return false;
		}
	}
}

/**
 * 同时创建两个实例，并且注册信号句柄的时候会出现不确定的错误
 * 任务不能出现无限循环的实现代码
 */