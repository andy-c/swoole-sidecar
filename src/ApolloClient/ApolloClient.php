<?php

namespace SwooleSidecar\ApolloClient;


use Swoole\Http\Server;
use Swoole\Timer;
use SwooleSidecar\Concern\Singleton;
use Swoole\Process;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Contract\ConfigCenterInterface;
use SwooleSidecar\Logger\Logger;
use SwooleSidecar\Request\Request;
use SwooleSidecar\Response\Response;
use Swoole\Coroutine\System;

class ApolloClient implements ConfigCenterInterface
{
    use Singleton;

    /**
     * @var array
     */
    public $conf;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var string
    */
    const TIMER_PROCESS_TITLE='SwooleSideCar-Apollo-Timer-Process';

    /**
     * @var string
     */
    const LISTEN_PROCESS_TITLE='SwooleSideCar-Apollo-Listen-Process';

    /**
     * @var array
    */
    private $notifications=[];
    /**
     * @var int
    */
    private $pullTimeout = 6;
    /**
     * @var int
    */
    private $holdTimeout = 65;
    /**
     * @var int
    */
    private $tickTimeout = 120000;


    /**
     * init apollo
     *
     * @param Server $server
     * @return void
     */
    public function init(Server $server){
        $this->conf = Config::apollo();
        $this->request = Request::once();
        $timer_process= new Process(function(){
            setProcessTitle(self::TIMER_PROCESS_TITLE);
            $this->Timer();
        });
        $long_pull_process = new Process(function(){
            setProcessTitle(self::LISTEN_PROCESS_TITLE);
            $this->Listen();
        });
        $long_pull_process->set(['enable_coroutine' => true]);
        $server->addProcess($timer_process);
        $server->addProcess($long_pull_process);
    }

    /**
     * get server ip
     */
    private function getServerIp(){
        $list = swoole_get_local_ip();
        return $list['eth0'] ?? '127.0.0.1';
    }

    /**
     * @inheritDoc
     */
    public function pullWithCacheOrNot(string $namespace, string $releaseKey = ''): Response
    {
        $appId       = $this->conf['appId'];
        $clusterName = $this->conf['clusterName'];
        $options['timeout']     = $this->pullTimeout;
        $options['host'] = $this->conf['host'];
        $options['port'] = $this->conf['port'];

        // Client ip and release key
        $query['clientIp'] = $this->getServerIp();
        if (!empty($releaseKey)) {
            $query['releaseKey'] = $this->getReleaseKey($namespace);
        }

        $options['query'] = $query;

        $uri = sprintf('/configs/%s/%s/%s', $appId, $clusterName, $namespace);
        return $this->request->get($uri, $options);
    }

    /**
     * @inheritDoc
     */
    public function pullBatch(array $namespaces): array
    {
        $requests = [];
        foreach ($namespaces as $namespace) {
            $requests[$namespace] = function () use ($namespace) {
                return $this->pullWithCacheOrNot($namespace, '');
            };
        }
        return $this->request->requestBatch($requests);
    }

    /**
     * @inheritDoc
     */
    public function Listen(): void
    {
        while(true){
           $this->checkAndSave();
        }
    }

    /**
     * @inheritDoc
     */
    public function Timer(): void
    {
        Timer::tick($this->tickTimeout,function(){
            $this->checkAndSave();
        });
    }

    /**
     * check update and save file
     * @return bool
     */
    public function checkAndSave():bool{
        $appId       = $this->conf['appId'];
        $clusterName = $this->conf['clusterName'];
        $options['host'] = $this->conf['host'];
        $options['port'] = $this->conf['port'];
        $namespaces = explode(',',$this->conf['namespaces']);
        $options['timeout'] = $this->holdTimeout;
        // Client ip and release key
        $query['appId']   = $appId;
        $query['cluster'] = $clusterName;

        // Init $notifications
        if (empty($this->notifications)) {
            foreach ($namespaces as $namespace) {
                $this->notifications[$namespace] = [
                    'namespaceName'  => $namespace,
                    'notificationId' => -1
                ];
            }
        }

        $query['notifications'] = json_encode(array_values($this->notifications));

        $options['query'] = $query;
        $result = $this->request->get('/notifications/v2', $options);
        if (empty($result->getBody())||$result->getCode() == 304) {
            return false;
        }
        $r = json_decode($result->getBody(),true);
        $updateNamespaceNames = $notifications =  [];
        foreach ($r as $nsNotification) {
            $namespaceName  = $nsNotification['namespaceName'];
            $notificationId = $nsNotification['notificationId'];

            // Update notifications
            $notifications[$namespaceName] = [
                'namespaceName'  => $namespaceName,
                'notificationId' => $notificationId
            ];
            $updateNamespaceNames[] = $namespaceName;
        }
        $this->notifications = $notifications;
        $updateConfigs = $this->pullBatch($updateNamespaceNames);
        //update file and cache
        $this->updateFileAndCache($updateConfigs,'listen');
        return true;
    }

    /**
     * set config to file
     * @param string $namespaceName
     * @param string $content
     * @return bool
     */
    private function setConfigToFile(string $namespaceName,string $content):bool{
        $configFile = $this->getConfigPath($namespaceName);
        $dir = dirname($configFile);
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $res = System::writeFile($configFile.'.tmp',$content);
        if($res){
            return copy($configFile.'.tmp',$configFile) && unlink($configFile.'.tmp');
        }else{
            return false;
        }
    }

    /**
     * get config path
     * @param string $namespaceName
     * @return string
     */
    private function getConfigPath(string $namespaceName):string{
        return APOLLO_DIR.DIRECTORY_SEPARATOR.$this->conf['appId'].'_'.
            $this->conf['clusterName'].'_apollo_cache_'.$namespaceName.'.json';
    }

    /**
     * get config from file
     * @param string $namespaceName
     * @return array
     */
    private function getConfigFromFile(string $namespaceName):array{
        $file = $this->getConfigPath($namespaceName);
        $config = [];
        if(file_exists($file)){
            $content = System::readFile($file);
            $config = $content ? json_decode($content,true) :[];
        }
        return $config;
    }

    /**
     * get releasekey
     * @param string $namespace
     * @return string
     */
    private function getReleaseKey(string $namespace):string{
        $releaseKey = '';
        $config = $this->getConfigFromFile($namespace);
        if(is_array($config) && isset($config['releaseKey'])){
            $releaseKey = $config['releaseKey'];
        }
        return $releaseKey;
    }

    /**
     * update file and cache
     * for cache pls use apcu store
     * @param array $updateConfigs
     * @param string $type
     * @return void
     */
    private function updateFileAndCache(array $updateConfigs,string $type):void{
        foreach($updateConfigs as $key => $val){
            $res = json_decode($val->getBody(),true);
            if(isset($res['status']) && $res['status'] ==404) continue;
            $fileRes = $this->setConfigToFile($res['namespaceName'],@json_encode($res['configurations']));
            $cacheRes = apcu_store($res['namespaceName'],$res['configurations']);
            $res[$res['namespaceName']]['file'] = $fileRes;
            $res[$res['namespaceName']]['cache'] = $cacheRes;
        }
        //for server type ,we dont need log
        if($type !='server'){
            Logger::once()->info("{$type} process: update file and cache success ");
        }
    }

    /**
     * check the update and fetch all config
     * and save
     * @param array $namespace
     * @return void
     */
    public function fetchConfig(array $namespace):void{
        $updateConfigs = $this->pullBatch($namespace,'');
        $this->updateFileAndCache($updateConfigs,'server');
    }
}