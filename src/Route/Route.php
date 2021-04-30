<?php


namespace SwooleSidecar\Route;


use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use SwooleSidecar\Controllers\ProxyController;
use function FastRoute\simpleDispatcher;

class Route
{
   /**
    * load route
    * @return void
   */
   public static function loadRule():GroupCountBased{
       return simpleDispatcher(function(RouteCollector $r){
           //handle the service request
           $r->post('/proxy',function($request){
               return ProxyController::once()->proxy($request);
           });
           //check app status
           $r->get('/health',function($request){
               return ProxyController::once()->health($request);
           });
           //check eureka instance
           $r->get('/instances',function($request){
               return ProxyController::once()->instances($request);
           });
           //eureka info
           $r->get('/info',function($request){
               return ProxyController::once()->info($request);
           });
       });
   }
}