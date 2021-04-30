<?php


namespace SwooleSidecar\Exception;


class RouteNotFoundException extends \RuntimeException
{
    protected $code=100006;
    protected $message="route not found exception!";
}