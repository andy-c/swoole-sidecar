<?php


namespace SwooleSidecar\Contract;

use SwooleSidecar\Exception\EurekaException;

/**
 * register center
 */
interface RegisterCenterInterface
{

    /**
     * register
     *
     * @return void
     *
     * @throws EurekaException
     */
    public function register():void;

    /**
     * deregister
     *
     *
     * @return boolean
     * @throws EurekaException
     */
    public function deregister():bool;

    /**
     * heartbeat
     *
     * @return boolean
     * @throws EurekaException
     */
    public function heartbeat():void;

    /**
     * query for all instances
     *
     * @return array
     * @throws EurekaException
     */
    public function instances():void;

    /**
     * query for appID
     *
     * @param $appId
     *
     * @return array
     * @throws EurekaException
     */
    public function instance(string $appId):array;
}