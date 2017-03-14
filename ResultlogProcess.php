<?php

class ResultlogProcess{
    public $master_pid = 0;
    public $works = array();
    public $max_process_num = 10;
    public $now_process_num = 0;
    public $index = 0;
    public static $instance = NULL;

    private function __construct(){
        if( !function_exists("swoole_set_process_name") ){
            echo "no swoole installed";
            exit(1);
        }
        //读取配置
        $processNum = 10;

        swoole_process::daemon( true, false ); //蜕变成守护进程
        $this->max_process_num = intval($processNum);
        swoole_set_process_name("ResultlogProcess Master");
        $this->master_pid = posix_getpid();
        $this->start();
        $this->async_wait_process();
    }

    public function __clone(){
        return false;
    }

    public static function boot(){
        if( self::$instance ){
            echo "return an exists ResultlogProcess\n";
            return self::$instance;
        }
        echo "create ResultlogProcess\n";
        self::$instance = new self;
    }

    public function start(){
        for($i = 0; $i < $this->max_process_num; $i++ ){
            $this->create_process($i);
        }
    }

    private function create_process( $index = NULL ){
        $process = new swoole_process( function( swoole_process $worker)use($index){
            echo "create child process:{$index}\n";
            swoole_set_process_name("ResultlogProcess Worker.{$index}");//设置程序名
            $this->do_process($index);
            $this->check_master_pid( $worker );
        }, false );
        $pid = $process->start(); //子进程开始启动
        $this->works[$index] = $pid;
        return $pid;
    }

    /**
     * 检测父进程状态
     * @param  swoole_process $worker [description]
     * @return [type] [description]
     */
    public function check_master_pid( swoole_process &$worker){
        if( !swoole_process::kill($this->master_pid, 0 ) ){//如果父进程不存在了，子进程在完成任务后也退出
            $worker->exit();
        }
    }

    /**
     * 阻塞回收进程
     * @return [type] [description]
     */
    public function waitProcess(){
        for(;;){
            if( count($this->works ) ){
                $death_process = swoole_process::wait();
                if( is_array($death_process) ){
                    $this->reboot_process($death_process);
                }
            }else{
                break;
            }
        }
    }

    public function async_wait_process(){
        swoole_process::signal(SIGTERM, function(){
            while( $retval = swoole_process::wait(false) ){
                echo "Child process died:{$retval['pid']}\n";
            }
        });
    }

    /**
     * 重启子进程
     * @param  [type] $process [description]
     * @return [type] [description]
     */
    public function reboot_process($process){
        $pid = $process['pid'];
        $index = array_search($pid, $this->works);
        if( $index !== FALSE ){
            $index = intval($index);
            $new_pid = $this->create_process($index);
            $this->works[$index] = $new_pid;
            return $new_pid;
        }
        return FALSE;
    }

    /**
     * 杀死子进程
     * @param  [type] $pid [description]
     * @return [type] [description]
     */
    public function kill_process( $pid ){
        return swoole_process::kill($pid);
    }

    /**
     * 监控
     * @return [type] [description]
     */
    public function monitor(){}


    /**
     * 实际业务逻辑
     * @return [type] [description]
     */
    public function do_process($index){
        while(1){
            file_put_contents("log/".__METHOD__."-{$index}","{$index}\n");
            sleep(10);
        }
    }
}

ResultlogProcess::boot();
