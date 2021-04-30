<?php


namespace SwooleSidecar\Config;


/**
 * log config
*/
class LogConfig
{

    /**
     * log path
     * @var string
     */
    public $log_path = LOG_DIR;
    /**
     * channel
     * @var array
     */
    public $channel_list = ['info', 'error'];
    /**
     * log format
     * @var string
     */
    public $log_format = '{"date": "%datetime%", "level": "%level_name%", "channel": "%channel%", "extra": "%extra%", "msg": {%message%}}' . "\n";

    /**
     * log buffer
     * @var int
    */
    public $log_buffer = 10;
    /**
     * info log level list
     * @var array
    */
    public $info_level_list=['INFO','DEBUG','NOTICE'];
    /**
     * error log level list
     * @var array
    */
    public $error_level_list=['ERROR','WARNING','ALERT','CRITICAL','EMERGENCY'];
}