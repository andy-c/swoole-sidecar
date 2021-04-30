<?php


namespace SwooleSidecar\Config;


use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Exception\ClassNotFoundException;
use function spl_object_hash;

class Config
{
    use Singleton;

    /**
     * base config path
     * @var string
    */
     private $base_config_path = 'SwooleSidecar\Config\\';

     /**
      * config ext
      * @var string
     */
     private $confExt= 'Config';

    /**
     * global config class
     * @var array
    */
    private  $configClassArray = [];

    public  function get($config) {
       if(empty($config)){
           return;
       }

       if(isset($this->configClassArray[$config])){
           return $this->configClassArray[$config];
       }
       //find the config
       $class =  $this->base_config_path.ucfirst($config).$this->confExt;
       if(class_exists($class)){
           $this->configClassArray[$config] = new $class();
       }else{
           throw new ClassNotFoundException($config."config not exist");
       }

       return $this->configClassArray[$config];
    }

    /**
     * @return ServerConfig
    */
    public  function getServerConfig(){
       return $this->get('server');
    }
    /**
     * @return ApolloConfig
    */
    public  function getApolloConfig(){
        return $this->get('apollo');
    }

    /**
     * @return EurekaConfig
    */
    public  function getEurekaConfig(){
        return $this->get('eureka');
    }

    /**
     * @return LogConfig
    */
    public function getLogConfig(){
        return $this->get('log');
    }
}