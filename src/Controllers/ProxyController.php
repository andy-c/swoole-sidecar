<?php


namespace SwooleSidecar\Controllers;

use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Config\Config;
use SwooleSidecar\EurekaClient\EurekaClient;
use SwooleSidecar\Helper\Helper;
use Swoole\Http\Request;

class ProxyController
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
        return Helper::Json('test');
    }
    /**
     * manual health check
     * @param Request $request
     *
     * @return string
     */
    public function health(Request $request):string {
        return Helper::Json("ok");
    }

    /**
     * get eureka instances
     * @param Request $request
     * @return string
    */
    public function instances(Request $request):string {
        $appId = $request->get("appId");
        $appId = strtolower($appId);
        $data = EurekaClient::once()->instance($appId);
        return Helper::Plain($data);
    }

    /**
     * eureka info
     * @return string
    */
    public function info(Request $request):string {
        $data = Config::once()->getEurekaConfig()->getInstance();
        $data['leaseInfo']['lastRenewalTimestamp'] = apcu_fetch(EurekaClient::once()::LAST_RENEW_TIME);
        return Helper::Json($data);
    }
}