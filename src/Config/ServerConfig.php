<?php


namespace SwooleSidecar\Config;

/**
 * server config file
 * see swoole doc
*/
Class ServerConfig
{
    /**
     * @var int
    */
    public $worker_num=2;
    /**
     * @var int
    */
    public $task_worker_num=0;
    /**
     * @var int
    */
    public $reactor_num=2;
    /**
     * @var int
    */
    public $dispatch_node=2;
    /**
     * @var string
    */
    public $log_file=RUNTIME_DIR.'swoole.log';
    /**
     * @var int
    */
    public $max_request = 10000;
    /**
     * @var string
    */
    public $enable_coroutine = 'on';
    /**
     * @var string
    */
    public $host="127.0.0.1";
    /**
     * @var int
    */
    public $port=8089;
    /**
     * @var string
    */
    public $master_process_title = "SwooleSideCar-Server-Process";
    /**
     * @var string
    */
    public $manage_process_title = "SwooleSideCar-Manage-Process";
    /**
     * @var string
    */
    public $worker_process_title = "SwooleSideCar-Worker-Process";
    /**
     * @var string
    */
    public $hook_flags = SWOOLE_HOOK_ALL;
    /**
     * @var int
    */
    public $max_coroutine = 3000;
    /**
     * @var string
    */
    public $server_name = "SWOOLE-SIDECAR-V1";
    /**
     * @var bool
    */
    public $daemonize = false;
    /**
     * @var backlog
    */
    public $backlog = 65535;
}