<?php


namespace SwooleSidecar\Exception;

use RuntimeException;

class EurekaException extends RuntimeException
{
    protected $code=100003;
    protected $message="eureka exception";
}