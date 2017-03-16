<?php

include __DIR__."/../vendor/autoload.php";

$daemon1 = longmon\php\Daemon::multiTaskInstance("multiTask");
$daemon1->run();
