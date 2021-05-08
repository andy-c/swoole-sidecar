<?php

use Swoole\Http\Response;

/**
 * set process title
 * @param string $title
 * @return void
*/
function setProcessTitle(string $title):void{
    if(function_exists("cli_set_process_title")){
        cli_set_process_title($title);
    }else{
        swoole_set_process_name($title);
    }
}

/**
 * json output
 * @param Response $response
 * @param mixed $data
 *
 * @return string
*/
function flushJson(Response $response,$data):string{
    $response->header('Content-Type','application/json');
    return json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}

/**
 * string output
 * @param mixed $response
 * @return string
*/
function flushString($response):string{
    if(is_object($response)&&method_exists($response,'toString')){
        return $response->toString();
    }else if (is_array($response)||!empty($response)){
        return flushJson($response);
    }else{
        return (string)$response;
    }
}

/**
 * get param from env
 *
 * @param string $name
 * @return mixed
*/
function env($name,$default=null){
    $value = getenv($name);

    if($value === false){
        return $default;
    }

    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return;
    }
    return $value;
}
