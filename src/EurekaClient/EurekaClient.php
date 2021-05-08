<?php


namespace SwooleSidecar\EurekaClient;


use Swoole\Process;
use Swoole\Timer;
use Swoole\Http\Server;
use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Contract\RegisterCenterInterface;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Logger\Logger;
use SwooleSidecar\Request\Request;
use SwooleSidecar\Exception\EurekaException;

class EurekaClient implements RegisterCenterInterface
{
    use Singleton;

    /**
     * @var array
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
      * @var string
     */
     const APP_PREFIX='APP_PREFIX';

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
        $this->conf= Config::get('eureka');
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
        setProcessTitle($this->conf['process_title']);
        $this->register();
        $this->heartbeat();
        $this->instances();
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        Timer::tick($this->conf['retryRegisterSecs'],function($timer_id){
            if($this->status){
                Timer::clear($timer_id);
                return true;
            }
            //options for request
            $options['body'] = json_encode(['instance' => $this->instanceInfo()],JSON_UNESCAPED_SLASHES);
            $options['headers'] = $this->buildHeader();
            $options['headers']['Content-Type'] ="application/json;charset=utf-8";
            $options['timeout'] = 10;
            //split eureka client info
            $eurekas = explode(',',$this->conf['eurekaHost']);
            //loop register
            foreach($eurekas as $k => $v){
                $address = explode(':',$v);
                $options['host'] = $address[0];
                $options['port'] = $address[1];
                $uri = '/eureka/apps/'.APP_NAME;
                $res = $this->request->post($uri,$options);
                if($res->getCode() !=204){
                    Logger::once()->info("eureka-register-status-code is ".$res->getCode());
                    continue;
                }else{
                    Logger::once()->info("eureka-register-result success,register-address is ".$address[0].':'.$address[1]);
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
        $eurekas = explode(',',$this->conf['eurekaHost']);
        $options['timeout'] = 10;
        //loop deregister
        foreach($eurekas as $k=>$v){
            $address = explode(':',$v);
            $options['host'] = $address[0];
            $options['port'] = $address[1];
            $uri = '/eureka/apps/'.APP_NAME.'/'.$this->conf['hostName'].':'
                .APP_NAME.':'.$this->conf['port'];
            $options['headers'] = $this->defaultHeader;
            $deregisterResult = $this->request->delete($uri,$options);
            Logger::once()->info("eureka-deregister-status is ".$deregisterResult->getCode()." eureka-client-info is ".$address[0].':'.$address[1]);
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
        Timer::tick($this->conf['renewalIntervalInSecs'],function($timer_id){
            if(!$this->status){
                return;
            }
            //rand a instance
            $heartBeatStatus = false;
            $eurekaHostArr = explode(',',$this->conf['eurekaHost']);
            $instance = array_rand($eurekaHostArr);
            $heartBeatQuery = [
                'status' => 'UP',
                'lastDirtyTimestamp' => (string)(round(microtime(true) * 100))
            ];

            //check application heath
            $options['host'] = $this->conf['ipAddr'];
            $options['port'] = $this->conf['port'];
            $options['timeout'] = 5;
            $appHealthy = $this->request->get('/'.$this->conf['healthCheckUrl'],$options);
            if($appHealthy->getCode()!= 200){
                $heartBeatQuery['status'] = 'DOWN';
            }

            Logger::once()->info("application-health-check-status is ".$heartBeatQuery['status']);

            //send the result to eureka server
            $options['headers'] = $this->buildHeader();
            $address = explode(':',$eurekaHostArr[$instance]);
            $options['host'] = $address[0];
            $options['port'] = $address[1];
            $instanceId = $this->conf['hostName'].':'.APP_NAME.':'.$this->conf['port'];
            $options['query'] = $heartBeatQuery;
            $heartbeatUri = '/eureka/apps/'.APP_NAME.'/'.$instanceId;
            $eurekaResponse = $this->request->put($heartbeatUri,$options);
            if($eurekaResponse->getCode() == 404){
                //application has't been register ,need to register
                Logger::once()->info("application-heartbeat-info code is 404");
            }else if($eurekaResponse->getCode()!=200){
                Logger::once()->info("heartbeat-error is ".$eurekaResponse->getCode());
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
         Timer::tick($this->conf['updateAllAppsTimeInterval'],function($timer_id){
            try{
                if(!$this->status){
                    return;
                }
                $version_delta = apcu_fetch($this->conf['version_delta']);
                //rand a eureka server to check apps version
                $eurekaHostArr = explode(',',$this->conf['eurekaHost']);
                $eurekaServInfo = array_rand($eurekaHostArr);
                $address = explode(':',$eurekaHostArr[$eurekaServInfo]);
                //get version_delta
                $options['host'] = $address[0];
                $options['port'] = $address[1];
                $options['timeout'] = 10;
                $versionDeltaResult = $this->request->get('/eureka/apps/delta',$options);
                if($versionDeltaResult->getCode() != 200){
                    Logger::once()->info("eureka-version-delta-status is ".$versionDeltaResult->getCode());
                    return false;
                }
                $lastestVersionDelta = $versionDeltaResult->getBody() ? json_decode($versionDeltaResult->getBody(),true)['applications']['versions_delta'] : '';
                if($version_delta && $version_delta == $lastestVersionDelta){
                    return [];
                }
                $version_delta = $lastestVersionDelta;

                //pull the all apps
                $options['headers'] = $this->defaultHeader;
                $fullApps = $this->request->get('/eureka/apps',$options);
                if($fullApps->getCode() !=200){
                    Logger::once()->info("eureka-pull-full-apps-status is ".$fullApps->getCode());
                    return false;
                }
                $apps = $fullApps->getBody() ? json_decode($fullApps->getBody(),true):[];
                if(!is_array($apps) && !empty($apps)){
                    return false;
                }
                //cache apps
                foreach($apps['applications']['application'] as $app){
                    apcu_store(self::APP_PREFIX.md5($app['name']),$app['instance']);
                }
                //cache version delta
                apcu_store($this->conf['version_delta'] ,$version_delta);
                return true;
            }catch(EurekaException $ex){
                Logger::once()->error("eureka-fetch-instances-error ".$ex->getMessage());
            }
        });
    }

    /**
     * @inheritDoc
     */
    public function instance(string $appId): array
    {
        $info = apcu_fetch(self::APP_PREFIX.md5($appId));
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
            $options['host'] = $this->conf['ipAddr'];
            $options['port'] = $this->conf['port'];
            $options['timeout'] = 5;
            $appHealthy = $this->request->get('/'.$this->conf['healthCheckUrl'],$options);
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
            'DiscoveryIdentity-Id' => $this->conf['hostName'],
            'Connection' => 'Keep-Alive',
        ];
    }

    /**
     * get eureka instance
     *
     * @return array
    */
    private function instanceInfo(){
        return [
            'instanceId' => $this->conf['hostName'].':'.APP_NAME.':'.$this->conf['port'],
            'hostName' =>$this->conf['hostName'] ?? swoole_get_local_ip(),
            'app' => APP_NAME,
            'ipAddr' => $this->conf['ipAddr'] ?? swoole_get_local_ip(),
            'status' => $this->conf['status'],
            'overriddenstatus' => $this->conf['overriddenstatus'] ?? 'UNKNOWN',
            'port' =>[
                '$' => $this->conf['port'],
                '@enabled' => 'true'
            ],
            'securePort' =>[
                '$' => $this->conf['securePort'],
                '@enabled' =>'false'
            ],
            'countryId' =>$this->conf['countryId'],
            'dataCenterInfo' =>[
                '@class' => 'com.netflix.appinfo.InstanceInfo$DefaultDataCenterInfo',
                'name' =>'MyOwn'
            ],
            'leaseInfo' =>[
                'renewalIntervalInSecs' => $this->conf['renewalIntervalInSecs'],
                'durationInSecs' =>$this->conf['durationInSecs'],
                'registrationTimestamp' => round(microtime(true)*1000),
                'lastRenewalTimestamp' => 0,
                'evictionTimestamp' =>0,
                'serviceUpTimestamp' =>round(microtime(true)*1000)
            ],
            'metadata'=>[
                '@class' =>''
            ],
            'homePageUrl' => $this->conf['ipAddr'].'/'.$this->conf['homePageUrl'],
            'statusPageUrl'=>$this->conf['ipAddr'].'/'.$this->conf['statusPageUrl'],
            'healthCheckUrl'=>$this->conf['ipAddr'].'/'.$this->conf['healthCheckUrl'],
            'vipAddress' =>$this->conf['vipAddress'],
            'secureVipAddress'=>$this->conf['secureVipAddress'],
            'isCoordinatingDiscoveryServer' => $this->conf['isCoordinatingDiscoveryServer'],
            'lastUpdatedTimestamp' => (string)(round(microtime(true)*1000)),
            'lastDirtyTimestamp' =>(string)(round(microtime(true) * 1000))
        ];
    }
}