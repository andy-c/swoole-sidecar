<?php

require_once "./vendor/autoload.php";

use SwooleSidecar\AppServer;
use SwooleSidecar\Config\Config;

define('RUNTIME_DIR',"/data/");
define('LOG_DIR','/data/app/');
define('PID_FILE','/data/app/process/serv.pid');
define('APP_START_TIME',microtime(true));
define('APP_NAME','testcyl-v1');
define('APOLLO_DIR','/data/app/apollo');

//init server config
$serConf = Config::once()->getServerConfig();
//run server
AppServer::once($serConf)->run();