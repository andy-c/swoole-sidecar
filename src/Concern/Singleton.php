<?php


namespace SwooleSidecar\Concern;


trait Singleton
{
    /**
     * @var self
    */
    private static $instance;

    public static function once(){
        if(!self::$instance){
            self::$instance = new self();
        }
        return self::$instance;
    }
}