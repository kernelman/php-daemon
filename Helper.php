<?php namespace longmon\php;
/**
 * Helper助手类
 * @author longmon <1307995200@qq.com>
 * @copyright (c) 2017
 */
class Helper{
	
	private static $err_log = "./log";
	
	/**
	 * 日志记录
	 * @param  string
	 * @param  string
	 * @param  boolean
	 * @return [type]
	 */
	public static function log( $data, $file = "error.log" , $append = false ){
		
		if( !$append ){
			$data = date("Y/m/d H:i:s")." : ".$data;
		}
		
		if( !self::check_writable( $file ) ) 
		{	
			if( !self::check_writable( dirname($file) ) )
			{
				trigger_error("File {$file} is unwritable!",  E_USER_WARNING);
				return false;
			} 
		}
		if( !file_put_contents( $file, $data, FILE_APPEND | LOCK_EX )  )
		{
			return false;
		}
		
		return true;
		
	}

	public static function warning( $data, $errno = E_USER_WARNING )
	{
	if( defined("DEBUG") && DEBUG ) trigger_error( $errno, $data);

		$data = "[WARNING] ". $data;
		Helper::log( $data, self::$err_log);
		return true;
	}
	
	public static function check_writable( $file )
	{
		return (self::check_file_exists($file)||is_dir($file)) && is_writable( $file );
	}

	public static function check_file_exists($file){
		clearstatcache();
		return is_file( $file );
	}

	/**
	 * @param  string $file 
	 * @return array
	 */
	public static function import($file){
		if( self::check_file_exists($file) ){
			return include($file);
		}else{
			return array();
		}
	}

	/**
	 * 从文件中读取内容 - 
	 * @param  string
	 * @return string | false
	 */
	public static function get_file_contents( $file ){
		if( !self::check_file_exists( $file ) ){
			return false;
		}
		return file_get_contents($file);
	}

	public static function put_contents_to_file( $file, $data){
		if( !self::check_file_exists($file)  ){
			if(!self::make_dir(dirname($file))){
				return false;
			}
		}
		return file_put_contents( $file, $data, LOCK_EX);
	}
	
	/**
	 * 递归创建目录
	 */
	public static function make_dir($dir)
	{
		if( is_dir($dir) ) return true; 
		$parent_dir = dirname($dir);
		if(is_dir($parent_dir) ){
			if( self::check_writable($parent_dir) ){
				return mkdir($dir);
			}else{
				trigger_error("{$dir} is unwriable!", E_USER_WARNING);
				return false;
			}
		}else{
			return self::make_dir($parent_dir);
		}
	}
	
	/**
	 * 
	 * 返回一个数据
	 */
	public static function get_pid_from_file($file)
	{
		return self::get_file_contents($file);
	}
	
	/**
	 * 只支持写两维数组
	 */
	public function write_pid_file($file, $pid)
	{
		if( $pid === NULL ){
			return touch($file);
		}
		return self::put_contents_to_file($file, $pid);
	}
	
}
