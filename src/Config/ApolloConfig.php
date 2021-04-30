<?php


namespace SwooleSidecar\Config;


class ApolloConfig
{
    /**
     * @var string
     */
    public $host = '127.0.0.1';

    /**
     * @var int
     */
    public $port = 8080;

    /**
     * @var string
     */
    public $appId = 'demo';

    /**
     * @var string
     */
    public $clusterName = 'default';

    /**
     * Seconds
     *
     * @var int
     */
    public $pullTimeout = 6;

    /**
     * holdTimeout
     *
     * @var int
     */
    public $holdTimeout = 63;

    /**
     * timer
     *
     * @var int
     */
    public $timer = 120000;

    /**
     * clientip
     *
     * @var string
     */
    public $clientip="127.0.0.1";

    /**
     * namespaces
     * @var array
     */
    public $namespace =["application"];

    /**
     * notifications
     * @var array
     */
    public $notifications=[];

    /**
     * timer process title
     * @var string
    */
    public $timer_process_title ='SwooleSideCar-Apollo-Timer-Process';
    /**
     * listen process title
     * @var string
    */
    public $listen_process_title ='SwooleSideCar-Apollo-Listen-Process';

}