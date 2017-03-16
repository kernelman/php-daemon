<?php

include __DIR__."/../vendor/autoload.php";

$daemon1 = longmon\php\Daemon::start_multi_task("multiTask");
$daemon1->run();
