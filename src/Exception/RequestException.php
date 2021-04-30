<?php


namespace SwooleSidecar\Exception;

use RuntimeException;

class RequestException extends RuntimeException
{
   protected $code=100005;
   protected $message="request exception!";
}