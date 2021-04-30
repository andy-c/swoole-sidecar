<?php


namespace SwooleSidecar\Response;


class Response
{
    /**
     * @var string|false
     */
    private $body;

    /**
     * @var  int|false
     */
    private $code;

    /**
     * @var array|false
     */
    private $header;

    /**
     * @var array|false
     */
    private $cookie;

    /**
     * @return false|string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return false|int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return array|false
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @return array|false
     */
    public function getCookie()
    {
        return $this->cookie;
    }



    public function __construct(int $responseCode,string $responseBody,array $responseCookie=[],array $responseHeader=[])
    {
        $this->code = $responseCode;
        $this->body = $responseBody;
        $this->cookie = $responseCookie;
        $this->header = $responseHeader;
    }
}