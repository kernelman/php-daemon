# php-daemon

A PHP daemon lib with swoole

## sample
```php
include "vendor/autoload.php";

$name = "DaemonTest"; 

//启动进程
Daemon::start($name);

//安全重载 - 重新加载任务配置 kill -10 master_pid 工作进程完成本轮任务后安全退出
Daemon::reload($name);

//安全停止 - 停止任务 kill -15 master_pid 工作进程完成本轮任务后安全退出
Daemon::stop($name);

//重启
Daemon::restart($name);
```
