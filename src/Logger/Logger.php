<?php


namespace SwooleSidecar\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MLogger;
use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Config\Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\FilterHandler;


class Logger extends MLogger
{

    use Singleton;

    /**
     * logger init
     */
    public function __construct($name,array $handlers = array(), array $processors = array())
    {
        parent::__construct($name, $handlers, $processors);
        //set log channel
        $logConfig = Config::once()->getLogConfig();
        foreach($logConfig->channel_list as $logname){
            $stream = new StreamHandler($logConfig->log_path."/".$logname.".log");
            //format
            $stream->setFormatter(new LineFormatter($logConfig->log_format));
            //buffer
            //$stream = new BufferHandler($stream, $logConfig->log_buffer, Logger::DEBUG, true, true);
            //filter
            $stream = new FilterHandler($stream, $logConfig->{$logname."_level_list"});
            $this->pushHandler($stream);
        }
    }
}