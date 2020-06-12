<?php

namespace app\Protocols;

use app\Connection\TcpConnection;

class Http
{


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
        $crlfPos = strpos($recvBuffer, '\r\n\r\n');
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
     * @see ProtocolInterface::encode()
     */
    public static function encode($data, TcpConnection $connection)
    {
    }

    /**
     * @see ProtocolInterface::decode()
     */
    public static function decode($recvBuffer, TcpConnection $connection)
    {
    }

}
