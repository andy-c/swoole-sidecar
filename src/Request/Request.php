<?php


namespace SwooleSidecar\Request;

use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\WaitGroup;
use SwooleSidecar\Concern\Singleton;
use SwooleSidecar\Exception\RequestException;
use SwooleSidecar\Concern\RequestTrait;
use SwooleSidecar\Logger\Logger;
use SwooleSidecar\Response\Response;
use function socket_strerror;

class Request
{
    use RequestTrait;
    use Singleton;

    /**
     * @inheritDoc
     */
    public function request(string $uri, array $options=[],string $method): Response
    {
        try {
            $query   = $options['query']   ?? [];
            $headers = $options['headers'] ?? [];
            $data    = $options['body'] ?? "";
            if (!empty($query)) {
                $query = is_array($query) ?  http_build_query($query) : $query;
                $uri   = sprintf('%s?%s', $uri, $query);
            }
            // Request
            $client = new Client($options['host'], $options['port']);
            $client->set(['timeout' => $options['timeout']?? 10]);
            $client->setMethod($method);
            $client->setHeaders($headers);
            $client->setData($data);
            $client->execute($uri);
            $body   = $client->getBody();
            $status = $client->getStatusCode();
            $header = $client->getHeaders() ?? [];
            $cookie = $client->getCookies() ?? [];
            $client->close();
            //handle the abnormal code
            if ($status == -1 || $status == -2 || $status == -3) {
                throw new RequestException(
                    sprintf(
                        'Request timeout!(host=%s, port=%d timeout=%d),status is %d,status info is %s',
                        $options['host'],
                        $options['port'],
                        $options['timeout'],
                        $status,
                        socket_strerror($client->errCode)
                    )
                );
            }
        } catch (RequestException $e) {
            Logger::once()->error(sprintf('request (%s)  fail!(%s)', $uri, $e->getMessage()));
        }
        return  new Response($status,$body,$cookie,$header);
    }

    /**
     * @inheritDoc
     */
    public function requestBatch(array $requests): array
    {
        $result = [];
        if(empty($requests)) return $result;
        $wg = new  WaitGroup();
        foreach($requests as $key => $req){
            $wg->add();
            go(function() use ($wg,&$result,$req) {
                try {
                    //Response
                    $response = $req();
                    $result[] = $response;
                } catch (RequestException $ex) {
                    Logger::once()->error("batch request error is ".$ex->getMessage().' request info is '.json_encode($req));
                    $result[] = false;
                }
                $wg->done();
            });
        }
        $wg->wait();
        return $result;
    }

}