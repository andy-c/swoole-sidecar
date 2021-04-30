<?php


namespace SwooleSidecar\Config;

class EurekaConfig
{
    /**
     * @var string
    */
    public $process_title = "SwooleSideCar-Eureka-Client-Process";
    /**
     * @var string
     */
    public $version_delta = 'APP_VERSION_DELTA';

    /**
     * @var string
     */
    public $app_prefix = "PREFIX";
    /**
     * @var string
     */
    public $status = "UP";

    /**
     * @var string
     */
    public $hostName="127.0.0.1";
    /**
     * @var string
     */
    public $ipAddr="127.0.0.1";
    /**
     * @var int
     */
    public $port=8089;

    /**
     * @var int
     */
    public $securePort=443;

    /**
     * @var string
     */
    public $homePageUrl="info";
    /**
     * @var string
     */
    public $statusPageUrl="status";

    /**
     * @var string
     */
    public $heathCheckUrl="health";
    /**
     * @var string
     */
    public $vipAddress="127.0.0.1";
    /**
     * @var string
     */
    public $secureVipAddress="127.0.0.1";
    /**
     * @var string
     */
    public $overriddenstatus="UNKNOWN";
    /**
     * @var int
     */
    public $countryId=1;
    /**
     * @var int
     */
    public $renewalIntervalInSecs=15000;

    /**
     * @var array
     */
    public $eurekaHost=[
        [
            'host'=>'127.0.0.1',
            'port' =>8901,
            'prefix' =>'eureka'
        ],
        [
            'host'=>'127.0.0.1',
            'port' =>8900,
            'prefix' =>'eureka'
        ]
    ];
    /**
     * @var int
    */
    public $retryRegisterSecs = 2000;

    /**
     * @var int
    */
    public $durationInSecs=90000;

    /**
     * @var bool
     */
    public $isCoordinatingDiscoveryServer =false;

    /**
     * @var int
    */
    public  $updateAllAppsTimeInterval = 5000;


    /**
     * fetch instance
     */
    public function getInstance(){
        return [
            'instanceId' => $this->hostName.':'.APP_NAME.':'.$this->port,
            'hostName' =>$this->hostName ?? swoole_get_local_ip(),
            'app' => APP_NAME,
            'ipAddr' => $this->ipAddr ?? swoole_get_local_ip(),
            'status' => $this->status,
            'overriddenstatus' => $this->overriddenstatus ?? 'UNKNOWN',
            'port' =>[
                '$' => $this->port,
                '@enabled' => 'true'
            ],
            'securePort' =>[
                '$' => $this->securePort,
                '@enabled' =>'false'
            ],
            'countryId' =>$this->countryId,
            'dataCenterInfo' =>[
                '@class' => 'com.netflix.appinfo.InstanceInfo$DefaultDataCenterInfo',
                'name' =>'MyOwn'
            ],
            'leaseInfo' =>[
                'renewalIntervalInSecs' => $this->renewalIntervalInSecs,
                'durationInSecs' =>$this->durationInSecs,
                'registrationTimestamp' => round(microtime(true)*1000),
                'lastRenewalTimestamp' => 0,
                'evictionTimestamp' =>0,
                'serviceUpTimestamp' =>round(microtime(true)*1000)
            ],
            'metadata'=>[
                '@class' =>''
            ],
            'homePageUrl' => $this->ipAddr.'/'.$this->homePageUrl,
            'statusPageUrl'=>$this->ipAddr.'/'.$this->statusPageUrl,
            'healthCheckUrl'=>$this->ipAddr.'/'.$this->heathCheckUrl,
            'vipAddress' =>$this->vipAddress,
            'secureVipAddress'=>$this->secureVipAddress,
            'isCoordinatingDiscoveryServer' => $this->isCoordinatingDiscoveryServer,
            'lastUpdatedTimestamp' => (string)(round(microtime(true)*1000)),
            'lastDirtyTimestamp' =>(string)(round(microtime(true) * 1000))
        ];
    }
}