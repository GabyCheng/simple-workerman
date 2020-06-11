<?php

namespace app\Connection;

use app\Events\EventInterface;
use app\Worker;

class TcpConnection extends ConnectionInterface
{

    /**
     * Read buffer size
     * @var int
     */
    const READ_BUFFER_SIZE = 65535;

    /**
     * status connection established.
     */
    const STATUS_ESTABLISHED = 2;

    /**
     * status closed
     */
    const STATUS_CLOSE = 8;
    /**
     * Connection->id
     * @var int
     */
    public $id = 0;

    /**
     * Which worker belong to
     * @var Worker
     */
    public $worker = null;

    /**
     * A copy of $worker->id which used to clean up the connection in worker->connections
     * @var int
     */
    protected $_id = 0;

    /**
     * send buffer
     * @var string
     */
    protected $sendBuffer = '';

    /**
     * receive buffer
     * @var string
     */
    protected $recvBuffer = '';


    /**
     * current package length
     * @var int
     */
    protected $currentPackageLength = 0;

    /**
     * is paused
     * @var bool
     */
    protected $isPaused = false;


    /**
     * SSL handshake completed or not
     * @var bool
     */
    protected $sslHandshakeCompleted = false;

    /**
     * socket
     * @var resource
     */
    protected $socket = null;

    /**
     * connection status
     * @var int
     */
    protected $status = self::STATUS_ESTABLISHED;

    /**
     * id recorder
     * @var int
     */
    protected static $idRecorder = 1;


    /**
     * Remote address
     * @var string
     */
    protected $remoteAddress = '';


    /**
     * all connection instances
     * @var array
     */
    public static $connection = array();

    /**
     * Sets the maximum send buffer size for the current connection
     * OnBufferFull callback will be emited when the send buffer is full
     * @var int
     */
    public $maxSendBufferSize = 1048576;


    /**
     * Default send buffer size
     * @var int
     */
    public static $defaultMaxSendBufferSize = 1048576;

    /**
     * Emitted when the send buffer becomes full
     * @var callable
     */
    public $onBufferFull = null;

    /**
     * Emitted when the send buffer becomes empty
     * @var callable
     */
    public $onBufferDrain = null;

    /**
     * sets the maximum acceptable packet size for the current connection
     * @var int
     */
    public $maxPackageSize = 1048576;


    /**
     * @var int default maximum acceptable packet size
     */
    public static $defaultMaxPackageSize = 10485760;

    /**
     * bytes read
     * @var int
     */
    public $bytesRead = 0;

    /**
     * Application layer protocol.
     * the format is like app\\protocols\\Http
     * @var \app\Protocols\Websocket
     */
    public $protocol = null;


    /**
     * TcpConnection constructor.
     * @param resource $socket
     * @param string $remoteAddress
     */
    public function __construct($socket, $remoteAddress = '')
    {
        ++self::$statistics['connection_count'];
        $this->id = $this->_id = self::$idRecorder++;
        if (self::$idRecorder === PHP_INT_MAX) {
            self::$idRecorder = 0;
        }

        $this->socket = $socket;
        stream_set_blocking($this->socket, 0);

        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($this->socket, 0);
        }
        Worker::log($socket);
        Worker::$globalEvent->add($this->socket, EventInterface::EV_WRITE, array($this, 'baseRead'));
        $this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
        $this->maxPackageSize = self::$defaultMaxPackageSize;
        $this->remoteAddress = $remoteAddress;
        static::$connection[$this->id] = $this;
    }


    /**
     * base read handler.
     * @param resource $socket
     * @param bool $checkEof
     * @return void
     */
    public function baseRead($socket, $checkEof = true)
    {
        $buffer = '';

        try {
            $buffer = @fread($socket, self::READ_BUFFER_SIZE);
        } catch (\Exception $e) {
        } catch (\Error $e) {
        }

        if ($buffer === '' || $buffer === false) {
            if ($checkEof && (feof($socket) || !is_resource($socket) || $buffer === false)) {
                $this->destroy();
                return;
            }
        } else {
            $this->bytesRead += strlen($buffer);
            $this->recvBuffer .= $buffer;
        }

        //if the application layer protocol has been set up
        if ($this->protocol !== null) {
            $parser = $this->protocol;
            while ($this->recvBuffer !== '' && !$this->isPaused) {
                if ($this->currentPackageLength) {
                    if ($this->currentPackageLength > strlen($this->recvBuffer)) {
                        break;
                    }
                } else {

                    try {
                        $this->currentPackageLength = $parser::input($this->recvBuffer, $this);
                    } catch (\Exception $e) {
                    } catch (\Error $e) {
                    }
                    // The packet length is unknown.
                    if ($this->currentPackageLength === 0) {
                        break;
                    } elseif ($this->currentPackageLength > 0 && $this->currentPackageLength <= $this->maxPackageSize) {
                        //Data is not enough for a package
                        if ($this->currentPackageLength > strlen($this->recvBuffer)) {
                            break;
                        }
                    } else {//Wrong package.
                        Worker::safeEcho('Error package. package_length=' . var_export($this->currentPackageLength, true));
                        $this->destroy();
                        return;
                    }
                }

                //the data is enough for a packet
                ++self::$statistics['total_request'];

                if (strlen($this->recvBuffer) === $this->currentPackageLength) {
                    $oneRequestBuffer = $this->recvBuffer;
                    $this->recvBuffer = '';
                } else {
                    $oneRequestBuffer = substr($this->recvBuffer, 0, $this->currentPackageLength);
                    $this->recvBuffer = substr($this->recvBuffer, $this->currentPackageLength);
                }

                $this->currentPackageLength = 0;

            }
        }


        if ($this->onMessage) {
            call_user_func($this->onMessage, $this, $this->recvBuffer);
        }

    }


    /**
     * Base write handler
     * @return void|bool
     */
    public function baseWrite()
    {
        Worker::log('tttttttttttttttttttt');
        @fwrite($this->socket, 'test');
    }

    public function destroy()
    {
        //avoid repeated calls.
        if ($this->status === self::STATUS_CLOSE) {
            return;
        }

        Worker::$globalEvent->del($this->socket, EventInterface::EV_READ);
        Worker::$globalEvent->del($this->socket, EventInterface::EV_WRITE);

        try {
            @fclose($this->socket);
        } catch (\Exception $e) {
        } catch (\Error $e) {
        }

        $this->status = self::STATUS_CLOSE;

        if ($this->onClose) {
            try {
                call_user_func($this->onClose, $this);
            } catch (\Exception $e) {
                Worker::log($e);
                exit(250);
            } catch (\Error $e) {
                Worker::log($e);
                exit(250);
            }
        }

        //try to emit protocol::onClose continue ....


        //clean
        $this->sendBuffer = $this->recvBuffer = '';
        $this->currentPackageLength = 0;
        $this->isPaused = $this->sslHandshakeCompleted = false;
        //cleaning up the callback to avoid memory leaks
        if ($this->status === self::STATUS_CLOSE) {
            $this->onMessage = $this->onClose = $this->onError = $this->onBufferDrain = $this->onBufferFull = null;

            if ($this->worker) {
                unset($this->worker->connections[$this->_id]);
            }
            unset(static::$connection[$this->id]);
        }

    }

}
