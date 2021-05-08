<?php


namespace SwooleSidecar\Controllers;

use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Config\Config;
use SwooleSidecar\Contract\ControllerContract;
use SwooleSidecar\EurekaClient\EurekaClient;
use Swoole\Http\{
    Response,
    Request
};


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
    public function proxy(Request $request):string {
        return flushString('test');
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