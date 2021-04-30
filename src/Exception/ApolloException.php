<?php


namespace SwooleSidecar\Exception;

use RuntimeException;

class ApolloException extends RuntimeException
{
    protected $code = 100001;
    protected $message = "Apollo exception";
}