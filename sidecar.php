<?php

require_once "./vendor/autoload.php";

use SwooleSidecar\AppServer;
use SwooleSidecar\Config\Config;

define('RUNTIME_DIR',"/data/app/");
define('PID_FILE',RUNTIME_DIR.'process/serv.pid');
define('APP_START_TIME',microtime(true));
define('APP_NAME','SWOOLE-SIEDCAR-V1');
define('APOLLO_DIR',RUNTIME_DIR.'apollo');
define('SWOOLE_LOG',RUNTIME_DIR.'/swoole.log');

//run server
AppServer::once()->run();