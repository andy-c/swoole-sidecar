<?php


namespace SwooleSidecar\Dispatcher;

use SwooleSidecar\Concern\Singleton;
use Swoole\Http\Response;
use Swoole\Http\Request;
use FastRoute\Dispatcher\GroupCountBased;
use SwooleSidecar\Exception\ {
    RouteNotFoundException,
    MethodNotAllowedException
};
use SwooleSidecar\Logger\Logger;
use Throwable;

class Dispatcher
{

    use Singleton;

    /**
    * dispatch to controller
    * @param Request $request
    * @param GroupCountBased $dispatcher
    * @param Response $response
    *
    * @return void
   */
   public function dispatch(Request $request,Response $response,GroupCountBased $dispatcher):void{
       try{
           $routeHandler= $this->matchRoute($request,$dispatcher);
           if($routeHandler && is_callable($routeHandler)){
              $data = $routeHandler($request,$response);
              //record request log
              Logger::once()->info(sprintf("server info %s,http packet %s, response data %s ",
                  json_encode($request->server),
                  json_encode($request->getData()),
                  $data
              ));
              $response->header('time',microtime(true)-APP_START_TIME);
           }
       }catch (RouteNotFoundException $ex){
           $response->status(405);
           $data = $ex->getMessage();
       }catch(MethodNotAllowedException $ex){
           $response->status(404);
           $data = $ex->getMessage();
       }catch(Throwable $ex){
           $response->status(500);
           $data = "server error";
           Logger::once()->error("server error info ".$ex->getMessage());
       } finally {
           $response->end($data);
       }
   }

   /**
    * match route
    * @param Request $request
    * @param GroupCountBased $dispatcher
    * @return $routehandler
   */
   private function matchRoute(Request $request,GroupCountBased $dispatcher){
       $uri = $request->server['request_uri'];
       $method = $request->server['request_method'];
       if (false !== $pos = strpos($uri, '?')) {
           $uri = substr($uri, 0, $pos);
       }
       $uri = rawurldecode($uri);
       $routerInfo = $dispatcher->dispatch($method,$uri);
       if($routerInfo[0] == GroupCountBased::FOUND){
           return $routerInfo[1];
       }elseif ($routerInfo[0] == GroupCountBased::METHOD_NOT_ALLOWED){
           throw new MethodNotAllowedException();
       }else if($routerInfo[0] == GroupCountBased::NOT_FOUND){
           throw new RouteNotFoundException();
       }
   }

}