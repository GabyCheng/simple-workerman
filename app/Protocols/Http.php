<?php

namespace app\Protocols;

use app\Connection\TcpConnection;
use app\Worker;
use http\Env\Response;

class Http
{

    /**
     * open cache
     * @var bool
     */
    protected static $enableCache = true;


    /**
     * Request class name
     * @var string
     */
    protected static $requestClass = 'app\Protocols\Http\Request';

    /**
     * Check the integrity of the package.
     * @param string $recvBuffer
     * @param TcpConnection $connection
     * @return int
     */
    public static function input($recvBuffer, TcpConnection $connection)
    {
        static $input = array();
        if (!isset($recvBuffer[512]) && isset($input[$recvBuffer])) {
            return $input[$recvBuffer];
        }
        $crlfPos = strpos($recvBuffer, "\r\n\r\n");
        if (false === $crlfPos) {
            //Judge whether the package length exceeds the limit
            if ($recvLen = strlen($recvBuffer) >= 16384) {
                $connection->close("HTTP/1.1 413 Request Entity Too Large\r\n\r\n");
                return 0;
            }
            return 0;
        }

        $headLen = $crlfPos + 4;
        $method = strstr($recvBuffer, ' ', true);

        if ($method === 'GET' || $method === 'OPTIONS' || $method === 'HEAD' || $method === 'DELETED') {
            if (!isset($recvBuffer[512])) {
                $input[$recvBuffer] = $headLen;
                if (count($input) > 512) {
                    unset($input[key($input)]);
                }
            }

            return $headLen;
        } elseif ($method !== 'POST' && $method !== 'PUT') {
            $connection->close('HTTP/1.1 400 Bad Request\r\n\r\n', true);
            return 0;
        }

        //post or put header content-length
        $header = substr($recvBuffer, 0, $crlfPos);
        $length = false;
        $pos = strpos($header, "\r\nContent-Length: ");
        if ($pos) {
            $length = $headLen + (int)substr($header, $pos + 18, 10);
        } elseif (\preg_match("/\r\ncontent-length: ?(\d+)/i", $header, $match)) {
            $length = $headLen + $match[1];
        }

        if ($length !== false) {
            if (!isset($recvBuffer[512])) {
                $input[$recvBuffer] = $length;
                if (count($input) > 512) {
                    unset($input[key($input)]);
                }
            }
            return $length;
        }

        $connection->close("HTTP/1.1 400 Bad Request\r\n\r\n", true);
        return 0;
    }


    /**
     * Http decode.
     * @param string $recvBuffer
     * @param TcpConnection $connection
     * @return \app\Protocols\Http\Request;
     */
    public static function decode($recvBuffer, TcpConnection $connection)
    {
        static $requests = array();
        $cacheable = static::$enableCache && !isset($recvBuffer[512]);
        if (true === $cacheable && isset($request[$recvBuffer])) {
            $request = $requests[$recvBuffer];
            $request->connection = $connection;
            $connection->request = $request;
            $request->properties = array();
            return $request;
        }
        $request = new static::$requestClass($recvBuffer);
        $request->connection = $connection;
        $connection->request = $request;
        if (true === $cacheable) {
            $requests[$recvBuffer] = $request;
            if (count($requests) > 512) {
                unset($requests[key($requests)]);
            }
        }
        return $request;
    }

    /**
     * Http encode
     * @param string|Response $data
     * @param TcpConnection $connection
     */
    public static function encode($response, TcpConnection $connection)
    {
        if (isset($connection->request)) {
            $connection->request->session = null;
            $connection->request->connection = null;
            $connection->request = null;
        }
        if (is_scalar($response) || null === $response) {
            $extHeader = '';
            if (isset($connection->header)) {
                foreach ($connection->header as $name => $value) {
                    if (is_array($value)) {
                        foreach ($value as $item) {
                            $extHeader = "$name:$item\r\n";
                        }
                    } else {
                        $extHeader = "$name: $value\r\n";
                    }
                    unset($connection->header);
                }
            }
            $bodyLen = strlen($response);

            return "HTTP/1.1 200 OK\r\nServer: workerman\r\n{$extHeader}Connection: keep-alive\r\nContent-Type: text/html;charset=utf-8\r\nContent-Length: $bodyLen\r\n\r\n$response";
        }
        //资源和文件操作
        return (string)$response;
    }


}
