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
     * @param $sendBuffer
     * @return mixed|boolean
     */
    abstract public function send($sendBuffer);


    abstract public function getRemoteIp();


}
