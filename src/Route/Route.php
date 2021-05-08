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
           $r->post('/proxy',function($request,$response){
               return ProxyController::once()->proxy($request,$response);
           });
           //check app status
           $r->get('/health',function($request,$response){
               return ProxyController::once()->health($request,$response);
           });
           //check eureka instance
           $r->get('/instances',function($request,$response){
               return ProxyController::once()->instances($request,$response);
           });
           //eureka info
           $r->get('/info',function($request,$response){
               return ProxyController::once()->info($request,$response);
           });
       });
   }
}