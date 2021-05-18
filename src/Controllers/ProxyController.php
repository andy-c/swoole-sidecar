<?php


namespace SwooleSidecar\Controllers;

use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Contract\ControllerContract;
use SwooleSidecar\EurekaClient\EurekaClient;
use Swoole\Http\{
    Response,
    Request
};
use SwooleSidecar\Logger\Logger;
use SwooleSidecar\Request\Request as ServiceRequest;


class ProxyController implements ControllerContract
{
    use Singleton;
    /**
     * proxy service
     * this function can handler service request
     * @param Request $request
     *
     * @return string
    */
    public function proxy(Request $request,Response $response):string {
        $result = new \stdClass();
        $result->code = 200;
        $result->msg = "";
        $result->data = "";
        $scheme = $port = $host = "";

        $method = $request->get["method"];
        $serviceName = $request->get["serviceName"];
        //get the service instance
        $instance = apcu_fetch(EurekaClient::APP_PREFIX.$serviceName);
        $instance = json_decode($instance,true);
        if(empty($instance)){
            $result->code = 404;
            $result->msg= "service not found";
        }

        if($instance["status"]!="UP"){
            $result->code = 503;
            $result->msg = "service unavailable";
        }

        if($instance["securePort"]["Enabled"] == 'true'){
            $scheme = "https://";
            $port = $instance['securePort']['$'];
        }else{
            $scheme = "http://";
            $port = $instance['port']['$'];
        }

        if($instance['ipAddr']){
            $host = $instance['ipAddr'];
        }else{
            $host = $instance['hostName'];
        }

        if($host == ''||$port==''){
            $result->code = 503;
            $result->msg = "service unavailable";
        }

        $options['host'] = $scheme.$host;
        $options['port'] = $port;
        $options['header'] = $request->header;
        $options['body'] = $request->post;
        $res = ServiceRequest::once()->request('/'.$request->get['query'],$options,$method);
        if($res->getCode()!=200){
            $result->code =500;
            $result->msg = "proxy request failed";
        }
        if(empty($res->getBody())){
            $result->code=500;
            $result->msg="proxy request failed";
        }else{
            $result->data = $res->getBody();
        }
        return flushJson($response,$result);

    }
    /**
     * manual health check
     * @param Request $request
     *
     * @return string
     */
    public function health(Request $request,Response $response):string {
        return flushJson($response,"ok");
    }

    /**
     * get eureka instances
     * @param Request $request
     * @return string
    */
    public function instances(Request $request,Response $response):string {
        $appId = $request->get("appId");
        $appId = strtolower($appId);
        $data = EurekaClient::once()->instance($appId);
        return flushJson($response,$data);
    }

    /**
     * eureka info
     * @return string
    */
    public function info(Request $request,Response $response):string {
        $data = EurekaClient::once()->instanceInfo();
        $data['leaseInfo']['lastRenewalTimestamp'] = apcu_fetch(EurekaClient::LAST_RENEW_TIME);
        return flushJson($response,$data);
    }
}