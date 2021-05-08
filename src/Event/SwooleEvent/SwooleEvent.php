<?php


namespace SwooleSidecar\Event\SwooleEvent;

use Swoole\Server;
use SwooleSidecar\Helper\Helper;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Logger\Logger;

/**
 * swoole event
*/
class SwooleEvent
{

    /**
     *onMasterStart
     * @return void
    */
    public function onStart(Server $server){
          file_put_contents(PID_FILE,$server->master_pid,FILE_APPEND);
          setProcessTitle(Config::get('server')['master_process_title']);
    }
    /**
     * onManageStart
     * @return void
    */
    public function onManagerStart(Server $server){
        file_put_contents(PID_FILE,','.$server->manager_pid,FILE_APPEND);
        setProcessTitle(Config::get('server')['manage_process_title']);
    }

    /**
     * onWorkerStart
     * @return void
    */
    public function onWorkerStart(){
        if(function_exists('opcache_reset')){
            opcache_reset();
        }
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        setProcessTitle(Config::get('server')['worker_process_title']);
    }
    /**
     * onWorkerError
     * @return void
    */
    public function onWorkerError(Server $server, int $worker_id, int $worker_pid, int $exit_code, int $signal){
        $info =[
            'worker_id'=>$worker_id,
            'worker_pid'=>$worker_pid,
            'exit_code'=>$exit_code,
            'signal' =>$signal
        ];
        Logger::once()->error("worker_error info :".json_encode($info));
    }

    /**
     * onWorkerStop
     * @return void
    */
    public function onWorkerStop(){

    }

    /**
     * onShutdown
     * @return void
    */
    public function onShutdown(){

    }
}