<?php


namespace SwooleSidecar\EurekaClient;


use Swoole\Process;
use Swoole\Timer;
use SwooleSidecar\Concern\Singleton;
use Swoole\Http\Server;
use SwooleSidecar\Config\EurekaConfig;
use SwooleSidecar\Contract\RegisterCenterInterface;
use SwooleSidecar\Helper\Helper;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Request\Request;
use SwooleSidecar\Exception\EurekaException;

class EurekaClient implements RegisterCenterInterface
{
    use Singleton;

    /**
     * @var EurekaConfig
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
     * @var string
    */
     const LAST_RENEW_TIME = 'LAST_RENEW_TIME' ;

    /**
     * @var array
    */
    private $defaultHeader=[
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ];

    /**
     * init eureka
     * @return void
    */
    public function init(Server $server){
        $this->conf= Config::once()->getEurekaConfig();
        $this->request = Request::once();
        $process = new Process(function (){
            $this->run();
        });
        $server->addProcess($process);
    }

    /**
     * run eureka
     * @return void
    */
    public function run(){
        Helper::setProcessTitle($this->conf->process_title);
        $this->register();
        $this->heartbeat();
        $this->instances();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        Timer::tick($this->conf->retryRegisterSecs,function($timer_id){
            if($this->status){
                Timer::clear($timer_id);
                return true;
            }
            //options for request
            $options['body'] = json_encode(['instance' => $this->conf->getInstance()],JSON_UNESCAPED_SLASHES);
            $options['headers'] = $this->buildHeader();
            $options['headers']['Content-Type'] ="application/json;charset=utf-8";
            $options['timeout'] = 10;
            //split eureka client info
            $eurekas = $this->conf->eurekaHost;
            //loop register
            foreach($eurekas as $k => $v){
                $options['host'] = $v['host'];
                $options['port'] = $v['port'];
                $uri = '/'.$v['prefix'].'/apps/'.APP_NAME;
                $res = $this->request->post($uri,$options);
                if($res->getCode() !=204){
                    Helper::getLogger()->info("eureka-register-status-code is ".$res->getCode());
                    continue;
                }else{
                    Helper::getLogger()->info("eureka-register-result success,register-address is ".$v['host'].':'.$v['port']);
                }
                $this->status = true;
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function deregister(): bool
    {
        $deregisterStatus = false;
        //split eureka client info
        $eurekas = $this->conf->eurekaHost;
        $options['timeout'] = 10;
        //loop deregister
        foreach($eurekas as $k=>$v){
            $options['host'] = $v['host'];
            $options['port'] = $v['port'];
            $uri = '/'.$v['prefix'].'/apps/'.APP_NAME.'/'.$this->conf->hostName.':'
                .APP_NAME.':'.$this->conf->port;
            $options['headers'] = $this->defaultHeader;
            $deregisterResult = $this->request->delete($uri,$options);
            Helper::getLogger()->info("eureka-deregister-status is ".$deregisterResult->getCode()." eureka-client-info is ".$v['host'].':'.$v['port']);
            if($deregisterResult->getCode()==200){
                $deregisterStatus = true;
            }else{
                $deregisterStatus = false;
            }
        }
        return $deregisterStatus;
    }

    /**
     * @inheritDoc
     */
    public function heartbeat(): void
    {
        Timer::tick($this->conf->renewalIntervalInSecs,function($timer_id){
            if(!$this->status){
                return;
            }
            //rand a instance
            $heartBeatStatus = false;
            $instance = array_rand($this->conf->eurekaHost);
            $heartBeatQuery = [
                'status' => 'UP',
                'lastDirtyTimestamp' => (string)(round(microtime(true) * 100))
            ];

            //check application heath
            $options['host'] = $this->conf->ipAddr;
            $options['port'] = $this->conf->port;
            $options['timeout'] = 5;
            $appHealthy = $this->request->get('/'.$this->conf->heathCheckUrl,$options);
            if($appHealthy->getCode()!= 200){
                $heartBeatQuery['status'] = 'DOWN';
            }

            Helper::getLogger()->info("application-health-check-status is ".$heartBeatQuery['status']);

            //send the result to eureka server
            $options['headers'] = $this->buildHeader();
            $options['host'] = $this->conf->eurekaHost[$instance]['host'];
            $options['port'] = $this->conf->eurekaHost[$instance]['port'];
            $instanceId = $this->conf->hostName.':'.APP_NAME.':'.$this->conf->port;
            $options['query'] = $heartBeatQuery;
            $heartbeatUri = '/'.$this->conf->eurekaHost[$instance]['prefix'].'/apps/'.APP_NAME.'/'.$instanceId;
            $eurekaResponse = $this->request->put($heartbeatUri,$options);
            if($eurekaResponse->getCode() == 404){
                //application has't been register ,need to register
                Helper::getLogger()->info("application-heartbeat-info code is 404");
            }else if($eurekaResponse->getCode()!=200){
                Helper::getLogger()->info("heartbeat-error is ".$eurekaResponse->getCode());
            }else{
                $heartBeatStatus = true;
            }
            unset($eurekaResponse);
            apcu_store(self::LAST_RENEW_TIME,round(microtime(true) * 1000));
            return $heartBeatStatus;
        });
    }

    /**
     * @inheritDoc
     */
    public function instances(): void
    {
         Timer::tick($this->conf->updateAllAppsTimeInterval,function($timer_id){
            try{
                if(!$this->status){
                    return;
                }
                $version_delta = apcu_fetch($this->conf->version_delta);
                //rand a eureka server to check apps version
                $eurekaServInfo = array_rand($this->conf->eurekaHost);
                //get version_delta
                $options['host'] = $this->conf->eurekaHost[$eurekaServInfo]['host'];
                $options['port'] = $this->conf->eurekaHost[$eurekaServInfo]['port'];
                $options['timeout'] = 10;
                $versionDeltaResult = $this->request->get('/'.$this->conf->eurekaHost[$eurekaServInfo]['prefix'].'/apps/delta',$options);
                if($versionDeltaResult->getCode() != 200){
                    Helper::getLogger()->info("eureka-version-delta-status is ".$versionDeltaResult->getCode());
                    return false;
                }
                $lastestVersionDelta = $versionDeltaResult->getBody() ? json_decode($versionDeltaResult->getBody(),true)['applications']['versions_delta'] : '';
                if($version_delta && $version_delta == $lastestVersionDelta){
                    return [];
                }
                $version_delta = $lastestVersionDelta;

                //pull the all apps
                $options['headers'] = $this->defaultHeader;
                $fullApps = $this->request->get('/'.$this->conf->eurekaHost[$eurekaServInfo]['prefix'].'/apps',$options);
                if($fullApps->getCode() !=200){
                    Helper::getLogger()->info("eureka-pull-full-apps-status is ".$fullApps->getCode());
                    return false;
                }
                $apps = $fullApps->getBody() ? json_decode($fullApps->getBody(),true):[];
                if(!is_array($apps) && !empty($apps)){
                    return false;
                }
                //cache apps
                foreach($apps['applications']['application'] as $app){
                    apcu_store($this->conf->app_prefix.md5($app['name']),$app['instance']);
                }
                //cache version delta
                apcu_store($this->conf->version_delta ,$version_delta);
                return true;
            }catch(EurekaException $ex){
                Helper::getLogger()->error("eureka-fetch-instances-error ".$ex->getMessage());
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function instance(string $appId): array
    {
        $info = apcu_fetch($this->conf->app_prefix.md5($appId));
        if(!is_array($info)){
            return [];
        }
        return $info;
    }

    /**
     * health check
     * @return bool
    */
    public function health():bool{
        //check application heath
        $status =false;
        \co\run(function() use(&$status){
            $options['host'] = $this->conf->ipAddr;
            $options['port'] = $this->conf->port;
            $options['timeout'] = 5;
            $appHealthy = $this->request->get('/'.$this->conf->heathCheckUrl,$options);
            if($appHealthy->getCode()==200){
                $status = true;
            }
        });
        return $status;
    }

    /**
     * build eureka request header
     *
     * @return void
    */
    private function buildHeader(){
        return  [
            'Accept-Encoding' => 'gzip',
            'DiscoveryIdentity-Name' => 'DefaultClient',
            'DiscoveryIdentity-Version' => '1.4',
            'DiscoveryIdentity-Id' => $this->conf->hostName,
            'Connection' => 'Keep-Alive',
        ];
    }
}