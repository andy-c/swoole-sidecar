<?php


namespace SwooleSidecar;

use Swoole\Http\Server;
use SwooleSidecar\ApolloClient\ApolloClient;
use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Config\ServerConfig;
use SwooleSidecar\EurekaClient\EurekaClient;
use SwooleSidecar\Event\SwooleEvent\SwooleHttpEvent;
use SwooleSidecar\Logger\Logger;
use SwooleSidecar\Request\Request;
use Throwable;
use Swoole\Process;

/**
 * sidecar
*/
class AppServer
{
    use Singleton;
    /**
     * @version
     * @var float
    */
    const VERSION=1.1;

    /**
     * server object
     * @var \swoole_http_server
    */
    private $httpServer;

    /**
     * server config
     * @var ServerConfig
    */
    private $serconf;

    /**
     * _construct
    */
    public function __construct()
    {
        try{
            //check server status
            $this->checkEnv();
            //pull config from apollo
            $this->pullConfig();
            //parse command
            $this->parseCommand();
            //register shutdown function
            $this->handleShutdownFunction();
            //init http server
            $this->initHttpServer();
            //init eureka and apollo
            $this->initAppClient();
        }catch (Throwable $ex){
            error_log("server error :".$ex->getMessage());
        }
    }

    /**
     * check server status
     *
     * @return void
    */
    private function checkEnv():void{
        //run in cli
        if(php_sapi_name()!="cli"){
            exit("run in cli mode");
        }
        if(!extension_loaded("swoole")||
          !extension_loaded("apcu")){
            exit("need swoole and apcu extension");
        }
    }
    /**
     *parse command
     *
     * @return void
    */
    private function parseCommand(){
        global $argv;
        // Check argv;
        if (!isset($argv[1])) {
            $argv[1] = 'start';
        }

        // Get command.
        $command = trim($argv[1]);
        $command2 = isset($argv[2]) ? $argv[2] : '';
        $pidinfo = false;
        if(file_exists(PID_FILE)){
            $pidinfo = file_get_contents(PID_FILE);
        }
        if($pidinfo=== false){
            if($command!='start'){
                echo "server has not been started";
                exit;
            }
        }else{
            if($command =='start'){
                echo 'server is running';
                exit;
            }
        }
        $pidArr =$pidinfo ? explode(',',$pidinfo):[];
        $masterPid = $pidArr[0] ?? '';
        $managerPid = $pidArr[1] ?? '';
        // execute command.
        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    $this->serconf['daemonize']= true;
                }else{
                    $this->serconf['daemonize']=false;
                }
                break;
            case 'stop':
                $this->stopServer($masterPid);
                exit(0);
                break;
            case 'reload':
                Process::kill($managerPid, SIGUSR1);
                echo  "server reload";
                exit;
            case 'restart':
                self::stopServer($masterPid);
                $this->serconf['daemonize'] = true;
                break;
            default:
        }
    }

    /**
     * stop server
     *
     * @return void
    */
    private function stopServer($masterPid):void{
        if(empty($masterPid)) return;
        @unlink(PID_FILE);
        Logger::once()->info("server is stopping");
        $masterPid && Process::kill($masterPid,SIGTERM);
        $timeout=10;
        $startTime = time();
        while(true){
            $serverRunning = $masterPid && Process::kill($masterPid,0);
            if($serverRunning){
                if(time() - $startTime >= $timeout){
                    Logger::once()->info("server can't be stopped");
                    exit;
                }
                usleep(10000);
                continue;
            }
            Logger::once()->info("server has been stopped");
            break;
        }
    }

    /**
     * init http server
     *
     * @return void
    */
    private  function initHttpServer():void{
       $this->httpServer = new Server($this->serconf['host'],$this->serconf['port']);
       $this->httpServer->set([
           'worker_num'       => $this->serconf['worker_num'],
           'task_worker_num'  => $this->serconf['task_worker_num'],
           'reactor_num'      => $this->serconf['reactor_num'],
           'dispatch_mode'    => $this->serconf['dispatch_mode'],
           'log_file'         => SWOOLE_LOG,
           'max_request'      => $this->serconf['max_request'],
           'enable_coroutine' => $this->serconf['enable_coroutine'],
           'hook_flags'       => $this->serconf['hook_flags'],
           'max_coroutine'    => $this->serconf['max_coroutine'],
           'daemonize'        => $this->serconf['daemonize'] == 'false',
           'backlog'          => $this->serconf['backlog']
       ]);
       $httpEvent = SwooleHttpEvent::once();
       $this->httpServer->on("request",[$httpEvent,"onRequest"]);
       $this->httpServer->on('workerStart',[$httpEvent,'onWorkerStart']);
       $this->httpServer->on('start',[$httpEvent,'onStart']);
       $this->httpServer->on('managerStart',[$httpEvent,'onManagerStart']);
       $this->httpServer->on('workerStop',[$httpEvent,'onWorkerStop']);
       $this->httpServer->on('Shutdown',[$httpEvent,'onShutdown']);
    }

    /**
     * init apollo client
     *
     * @return void
    */
    private function initAppClient():void{
        EurekaClient::once()->init($this->httpServer);
        ApolloClient::once()->init($this->httpServer);
    }

    /**
     * handle error
     *
     * @return void
     */
    private  function handleShutdownFunction():void {
        //register shutdown handler
        register_shutdown_function(function(){
            $errors = error_get_last();
            if($errors && (
                    $errors['type'] === \E_ERROR ||
                    $errors['type'] === \E_PARSE ||
                    $errors['type'] === \E_CORE_ERROR ||
                    $errors['type'] === \E_COMPILE_ERROR ||
                    $errors['type'] === \E_RECOVERABLE_ERROR
                )){
                $mess = $errors['message'];
                $file = $errors['file'];
                $line = $errors['line'];
                $errMsg = "worker process error :".$errors["type"].":".$mess."in ".$file."on the ".$line;
                Logger::once()->error($errMsg);
            }
        });
    }

    /**
     * run app server
     * @return void
    */
    public function run():void{
        $this->httpServer->start();
    }

    /**
     * firstly we can pull the config from apollo server
     * then we can use that config,for example http server config,
     * eureka config,log config ,and so on
     *
     * @return void
    */
    private function pullConfig():void {
        if(!is_dir(APOLLO_DIR)){
            \co\run(function(){
                go(function()  {
                    $apollo = ApolloClient::once();
                    $apollo->request = Request::once();
                    $apollo->conf = Config::apollo();
                    $apollo->fetchConfig(explode(',',$apollo->conf['namespaces']));
                    $this->serconf = apcu_fetch('server');
                    //for main process ,we dont need this
                    $apollo = null;
                });
            });
        }else{
            $this->serconf = Config::get('server');
        }
    }
}
