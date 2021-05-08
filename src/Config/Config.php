<?php


namespace SwooleSidecar\Config;

use function apcu_fetch;
use function apcu_store;

class  Config
{

    /**
     * get config from cache
     * @param string $name
     * @return mixed
     */
    public static function get(string $name=''){
       if(empty($name)){
           return false;
       }
       $config = apcu_fetch($name);
       if(!$config){
           $cacheFile = file_get_contents(APOLLO_DIR.'/swoole-sidecar_'.self::apollo()['clusterName'].'_apollo_cache_'.$name.'.json');
           $config = json_decode($cacheFile,true);
           apcu_store($name,$config);
       }
       return $config ?? [];
    }

    /**
     * get apollo config
     * @return array
     */
    public static function apollo():array {
        return [
            "host" => env("APOLLO__HOST",'apollo-api.dev.chinawayltd.com'),
            'port' => env('APOLLO__PORT','8080'),
            'clusterName' => env('APOLLO__CLUSTER','default'),
            'appId' => env('APOLLO__ID','swoole-sidecar'),
            'namespaces' => env('APOLLO__NAMESPACES','server,eureka,log')
        ];
    }


}