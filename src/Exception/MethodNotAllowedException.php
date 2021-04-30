<?php


namespace SwooleSidecar\Exception;


class MethodNotAllowedException extends \RuntimeException
{
    protected $code = 100004;
    protected $message="method not allowed!";
}