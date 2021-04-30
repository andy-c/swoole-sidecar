<?php


namespace SwooleSidecar\Concern;


trait Singleton
{
    /**
     * @var self
    */
    private static $instance;

    public static function once($config = null){
        if(!self::$instance){
            self::$instance = new self($config);
        }
        return self::$instance;
    }
}