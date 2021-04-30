<?php


namespace SwooleSidecar\Helper;

use SwooleSidecar\Logger\Logger;
use Swoole\Http\Request;

/**
 * helper class
*/
class Helper
{
   /**
    * set process title
   */
   public static function setProcessTitle(string  $title){
       if(function_exists("cli_set_process_title")){
           cli_set_process_title($title);
       }else{
           swoole_set_process_name($title);
       }
   }

   /**
    * get logger
    * @return Logger
   */
   public static function getLogger(){
       return Logger::once(APP_NAME);
   }

   /**
    * json formate
    *
    * @return json
   */
   public static function Json($response){
       return json_encode($response,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   }

   /**
    * plain text
    *
    * @return plain
   */
   public static function Plain($response){
       return $response;
   }
}