<?php

namespace app\Protocols;

use app\Connection\ConnectionInterface;

interface ProtocolInterface
{

    /**
     * check the integrity of the package.
     * please return the length of package.
     * if length is unknown please return 0 that mean waiting more data.
     * if the package has something wrong place return false the connection will be closed.
     * @param $recvBuffer
     * @param ConnectionInterface $connection
     * @return int|false
     */
    public static function input($recvBuffer, ConnectionInterface $connection);


    /**
     * Decode package and emit onMessage($message) callback, $message is the result that decode returned.
     * @param string $recvBuffer
     * @param ConnectionInterface $connection
     * @return mixed
     */
    public static function decode($recvBuffer, ConnectionInterface $connection);


    /**
     * Encode package brefore sending to client.
     * @param mixed $data
     * @param ConnectionInterface $connection
     * @return string
     */
    public static function encode($data, ConnectionInterface $connection);
}

