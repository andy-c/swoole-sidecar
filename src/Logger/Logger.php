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
     * @var string
     *
    */
    private $log_format = '{"date": "%datetime%", "level": "%level_name%", "channel": "%channel%", "extra": "%extra%", "msg": {%message%},"context":{%context%}}' . "\n";

    /**
     * logger init
     */
    public function __construct($name = APP_NAME,array $handlers = array(), array $processors = array())
    {
        parent::__construct($name, $handlers, $processors);
        //set log channel
        $logConfig = Config::get('log');
        $logConfig['channel_list'] = explode(',',$logConfig['channel_list']);
        foreach($logConfig['channel_list'] as $logname){
            $stream = new StreamHandler(RUNTIME_DIR."/".$logname.".log");
            //format
            $stream->setFormatter(new LineFormatter($this->log_format));
            //buffer
            $stream = new BufferHandler($stream, $logConfig['log_buffer'], Logger::DEBUG, true, true);
            //filter
            $stream = new FilterHandler($stream, explode(',',$logConfig[$logname.'_level_list']));
            $this->pushHandler($stream);
        }
    }

}