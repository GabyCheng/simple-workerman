<?php

namespace app\Connection;

abstract class ConnectionInterface
{

    /**
     * Statistics for status command.
     * @var array
     */
    public static $statistics = array(
        'connection_count' => 0,
        'total_request' => 0,
        'throw_exception' => 0,
        'send_fail' => 0,
    );

    /**
     * Emitted when data is received
     * @var callable
     */
    public $onMessage = null;


    /**
     * Emitted when the other end of the socket sends a FIN packet
     * @var callable
     */
    public $onClose = null;

    /**
     * Emitted when an error occurs with connection
     * @var callable
     */
    public $onError = null;


    /**
//     * @param $sendBuffer
//     * @return mixed|boolean
//     */
//    abstract public function send($sendBuffer);
//
//    /**
//     * get remote ip
//     * @return string
//     */
//    abstract public function getRemoteIp();
//
//    /**
//     * get remote port
//     * @return int
//     */
//    abstract public function getRemotePort();
//
//    /**
//     * get local ip
//     * @return string
//     */
//    abstract public function getLocalIp();
//
//    /**
//     * get local ip
//     * @return int
//     */
//    abstract public function getLocalPort();
//
//    /**
//     * get local address
//     * @return string
//     */
//    abstract public function getLocalAddress();
//
//    /**
//     * is ipv4
//     * @return bool
//     */
//    abstract public function isIPv4();
//
//    /**
//     * is ipv6
//     * @return bool
//     */
//    abstract public function isIpv6();
//
//    /**
//     * close connection
//     * @param $data
//     * @return void
//     */
//    abstract public function close($data = null);
//
}
