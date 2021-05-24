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
           $requestStarttime = microtime(true);
           $context['traceId'] = $request->get['traceId'] ?? $this->generateTraceId();
           $context['spanId'] =$request->get['spanId']?? "0";
           $context['upSpan'] =$request->get['upSpan'] ?? '';
           $routeHandler= $this->matchRoute($request,$dispatcher);
           $request->context = $context;
           if($routeHandler && is_callable($routeHandler)){
              $data = $routeHandler($request,$response);
               $context['rt'] = microtime(true)-$requestStarttime;
              //record request log
              Logger::once()->info(sprintf("server info %s,http packet %s, response data %s ",
                  json_encode($request->server),
                  json_encode($request->getData()),
                  $data
              ),$context);
              $response->header('rt',$context['rt']);
              $response->header('traceId',$context['traceId']);
           }
       }catch (RouteNotFoundException $ex){
           $response->status(405);
           $data = $ex->getMessage();
       }catch(MethodNotAllowedException $ex){
           $response->status(404);
           $data = $ex->getMessage();
       }catch(Throwable $ex){
           $response->status(500);
           $data = "server error:".$ex->getMessage();
           Logger::once()->error("server error info ".$ex->getMessage(),$context);
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

   /**
    * generate id
    * return string
   */
   private function generateTraceId():string {
       if(function_exists(com_create_guid)){
           $traceId = com_create_id();
       }else{
           $i           = mt_rand(1, 0x7FFFFF);
           $traceId = sprintf(
               "%08x%06x%04x%06x",
               time() & 0xFFFFFFFF,
               crc32(substr((string)gethostname(), 0, 256)) >> 8 & 0xFFFFFF,
               getmypid() & 0xFFFF,
               $i = $i > 0xFFFFFE ? 1 : $i + 1
           );
       }
       return $traceId;
   }

}