<?php


namespace SwooleSidecar\Exception;

use RuntimeException;

class ClassNotFoundException extends RuntimeException
{
   protected $code = 100002;
   protected $message = "class not found!";
}