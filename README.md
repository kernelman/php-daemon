# php-daemon
A PHP daemon lib with swoole

## sample
```php
include "vendor/autoload.php";
$name = "Daemon Test";

//启动进程
Daemon::start($name);

//重载
Daemon::reload($name);

//停止
Daemon::stop($name);

//重启
Daemon::restart($name);
```
