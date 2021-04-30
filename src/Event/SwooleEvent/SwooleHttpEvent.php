<?php


namespace SwooleSidecar\Event\SwooleEvent;

use FastRoute\Dispatcher\GroupCountBased;
use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Dispatcher\Dispatcher;
use SwooleSidecar\EurekaClient\EurekaClient;
use SwooleSidecar\Helper\Helper;
use Swoole\Http\Request;
use Swoole\Http\Response;
use SwooleSidecar\Route\Route;

/**
 * swoole http event
*/
class SwooleHttpEvent extends SwooleEvent
{

    use Singleton;

    /**
     * disptacher
     * @var GroupCountBased
    */
    private $dispatcher;

    /**
     * onWorkerStart
     * @return void
    */
    public function onWorkerStart():void
    {
        parent::onWorkerStart();
        $this->dispatcher = Route::loadRule();
    }

    /**
     * on request
     * @param Request $request
     * @param Response $response
     * @return void
    */
    public function onRequest(Request $request,Response $response):void{
        Dispatcher::once()->dispatch($request,$response,$this->dispatcher);
    }

    /**
     * onShutdown
     * @return void
    */
    public function onShutdown():void
    {
        parent::onShutdown();
        \co\run(function(){
            //deregister
            $i=0;
            while(1){
                $deregisterStatus = EurekaClient::once()->deregister();
                if($deregisterStatus){
                    break;
                }
                if($i > 30){
                    Helper::getLogger()->info("eureka deregister failed,pls check the network");
                    break;
                }
                $i++;
            }
        });
    }
}