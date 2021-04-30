<?php

namespace SwooleSidecar\ApolloClient;


use Swoole\Http\Server;
use Swoole\Timer;
use SwooleSidecar\Concern\Singleton;
use Swoole\Process;
use SwooleSidecar\Config\ApolloConfig;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Contract\ConfigCenterInterface;
use SwooleSidecar\Request\Request;
use SwooleSidecar\Helper\Helper;
use SwooleSidecar\Response\Response;
use Swoole\Coroutine\System;

class ApolloClient implements ConfigCenterInterface
{
    use Singleton;

    /**
     * @var ApolloConfig
     */
    private $conf;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var boolean
     */
    public $status = false;

    /**
     * init apollo
     *
     * @return void
    */
    public function init(Server $server){
        $this->conf = Config::once()->getApolloConfig();
        $this->request = Request::once();
        $timer_process= new Process(function(){
            Helper::setProcessTitle($this->conf->timer_process_title);
            $this->Timer();
        });
        $long_pull_process = new Process(function(){
            Helper::setProcessTitle($this->conf->listen_process_title);
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
    public function pullWithCache(string $namespace, string $clientip): Response
    {
        $appid       = $this->conf->appId;
        $clusterName = $this->conf->clusterName;
        $options['timeout']     = $this->conf->pullTimeout;
        $options['host'] = $this->conf->host;
        $options['port'] = $this->conf->port;

        if (empty($clientIp)) {
            $clientIp = $this->getServerIp();
        }

        $options['query']= [
            'clientIp' => $clientIp
        ];

        $uri = sprintf('/configfiles/json/%s/%s/%s', $appid, $clusterName, $namespace);
        return $this->request->get($uri, $options);
    }

    /**
     * @inheritDoc
     */
    public function pullWithOutCache(string $namespace, string $releaseKey = '', string $clientip): Response
    {
        $appid       = $this->conf->appId;
        $clusterName = $this->conf->clusterName;
        $options['timeout']     = $this->conf->pullTimeout;
        $options['host'] = $this->conf->host;
        $options['port'] = $this->conf->port;

        if (empty($clientIp)) {
            $clientIp = $this->getServerIp();
        }

        // Client ip and release key
        $query['clientIp'] = $clientIp;
        if (!empty($releaseKey)) {
            $query['releaseKey'] = $this->getReleaseKey($namespace);
        }

        $options['query'] = $query;

        $uri = sprintf('/configs/%s/%s/%s', $appid, $clusterName, $namespace);
        return $this->request->get($uri, $options);
    }

    /**
     * @inheritDoc
     */
    public function pullBatch(array $namespaces, string $clientip): array
    {
        $requests = [];
        foreach ($namespaces as $namespace) {
            $requests[$namespace] = function () use ($namespace, $clientip) {
                return $this->pullWithOutCache($namespace, '', $clientip);
            };
        }
        return $this->request->requestBatch($requests);
    }

    /**
     * @inheritDoc
     */
    public function Listen(): void
    {
        $appid       = $this->conf->appId;
        $clusterName = $this->conf->clusterName;
        $options['host'] = $this->conf->host;
        $options['port'] = $this->conf->port;
        $namespaces = $this->conf->namespace;
        $clientip = $this->conf->clientip;
        $options['timeout'] = $this->conf->holdTimeout;
        // Client ip and release key
        $query['appId']   = $appid;
        $query['cluster'] = $clusterName;
        $this->status = true;//start to run

        // Init $notifications
        if (empty($this->conf->notifications)) {
            foreach ($namespaces as $namespace) {
                $this->conf->notifications[$namespace] = [
                    'namespaceName'  => $namespace,
                    'notificationId' => -1
                ];
            }
        }

        // start Long poll
        while ($this->status) {
            $query['notifications'] = json_encode(array_values($this->conf->notifications));

            $options['query'] = $query;
            $result = $this->request->get('/notifications/v2', $options);
            if (empty($result->getBody())||$result->getCode() == 304) {
                continue;
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
            $this->conf->notifications = $notifications;
            $updateConfigs = $this->pullBatch($updateNamespaceNames, $clientip);
            //update file and cache
            $this->updateFileAndCache($updateConfigs,'listen');
        }
        return;
    }

    /**
     * @inheritDoc
     */
    public function Timer(): void
    {
        Timer::tick($this->conf->timer,function($timer_id){
            $appid       = $this->conf->appId;
            $clusterName = $this->conf->clusterName;
            $options['host'] = $this->conf->host;
            $options['port'] = $this->conf->port;
            $namespaces = $this->conf->namespace;
            $clientip = $this->conf->clientip;
            $options['timeout'] = $this->conf->holdTimeout;

            // Client ip and release key
            $query['appId']   = $appid;
            $query['cluster'] = $clusterName;

            // Init $notifications
            if (empty($this->conf->notifications)) {
                foreach ($namespaces as $namespace) {
                    $this->conf->notifications[$namespace] = [
                        'namespaceName'  => $namespace,
                        'notificationId' => -1
                    ];
                }
            }

            $query['notifications'] = json_encode(array_values($this->conf->notifications));

            $options['query'] = $query;
            $result = $this->request->get('/notifications/v2', $options);

            if (empty($result->getBody())) {
                return ;
            }
            $updateNamespaceNames = [];
            $r = json_decode($result->getBody(),true);
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
            $this->conf->notifications = $notifications;
            $updateConfigs = $this->pullBatch($updateNamespaceNames, $clientip);
            //update file and cache
            $this->updateFileAndCache($updateConfigs,"timer");
        });
    }

    /**
     * set config to file
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
     */
    private function getConfigPath(string $namespaceName):string{
        return APOLLO_DIR.DIRECTORY_SEPARATOR.$this->conf->appId.'_'.
            $this->conf->clusterName.'_apollo_cache_'.$namespaceName.'.json';
    }

    /**
     * get config from file
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
     */
    private function updateFileAndCache(array $updateConfigs,string $type):void{
        $res =[];
        foreach($updateConfigs as $key => $val){
            $res = json_decode($val->getBody(),true);
            $fileRes = $this->setConfigToFile($res['namespaceName'],@json_encode($res['configurations']));
            $cacheRes = apcu_store($res['namespaceName'],$res['configurations']);
            $res[$res['namespaceName']]['file'] = $fileRes;
            $res[$res['namespaceName']]['cache'] = $cacheRes;
        }
        Helper::getLogger()->info("{$type} process: update file and cache success ");
    }
}