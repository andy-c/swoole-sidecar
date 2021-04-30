<?php


namespace SwooleSidecar\Contract;

use SwooleSidecar\Exception\ApolloException;
use SwooleSidecar\Response\Response;


interface ConfigCenterInterface
{

    /**
     * pull with cache
     *
     * @param string $namespace
     * @param string $clientip
     *
     * @return array
     * @throws ApolloException
     */
    public function pullWithCache(string $namespace,string $clientip):Response;


    /**
     * pull without cache
     *
     * @param string $namespace
     * @param string $clientip
     * @param string $releaseKey;
     *
     * @return array
     * @throws ApolloException
     */
    public function pullWithOutCache(string $namespace,string $releaseKey = '',string $clientip):Response;

    /**
     * pull batch
     *
     * @param array $namespaces
     * @param string $clientip
     *
     * @return array
     * @throws ApolloException
     */
    public function pullBatch(array $namespaces,string $clientip):array;

    /**
     * listen
     *
     * @param callable $callback
     *
     * @return void
     * @throws ApolloException
     */
    public function Listen():void;

    /**
     * timer
     *
     * @param callable $callback
     *
     * @return void
     * @throws ApolloException
     */
    public function Timer():void;
}