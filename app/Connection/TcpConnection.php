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
     * status connecting
     * @var int
     */
    const STATUS_CONNECTING = 1;

    /**
     * status connection established.
     * @var int
     */
    const STATUS_ESTABLISHED = 2;

    /**
     * status closing
     * @var int
     */
    const STATUS_CLOSING = 4;

    /**
     * status closed
     * @var int
     */
    const STATUS_CLOSED = 8;
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
     * bytes written
     * @var int
     */
    public $bytesWritten = 0;


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
     * @var \app\Protocols\Http
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
        Worker::$globalEvent->add($this->socket, EventInterface::EV_READ, array($this, 'baseRead'));
        $this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
        $this->maxPackageSize = self::$defaultMaxPackageSize;
        $this->remoteAddress = $remoteAddress;
        static::$connection[$this->id] = $this;
    }


    /**
     * Adding support of custom functions within protocols
     *
     * @param string $name
     * @param array  $arguments
     * @return void
     */
    public function __call($name, array $arguments) {
        // Try to emit custom function within protocol
        if (\method_exists($this->protocol, $name)) {
            try {
                return \call_user_func(array($this->protocol, $name), $this, $arguments);
            } catch (\Exception $e) {
                Worker::log($e);
                exit(250);
            } catch (\Error $e) {
                Worker::log($e);
                exit(250);
            }
        }
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
                    //get a full package from the buffer
                    $oneRequestBuffer = substr($this->recvBuffer, 0, $this->currentPackageLength);
                    //remote the current package from the receive buffer
                    $this->recvBuffer = substr($this->recvBuffer, $this->currentPackageLength);
                }
                //reset the current packet length to 0.
                $this->currentPackageLength = 0;
                if (!$this->onMessage) {
                    continue;
                }
                try {
                    call_user_func($this->onMessage, $this, $parser::decode($oneRequestBuffer, $this));
                } catch (\Exception $e) {
                    Worker::log($e);
                    exit(250);
                } catch (\Error $e) {
                    Worker::log($e);
                    exit(250);
                }
                return;
            }
        }

        if ($this->recvBuffer === '' || $this->isPaused) {
            return;
        }
        //applications protocol is not set
        ++self::$statistics['total_request'];
        if (!$this->onMessage) {
            $this->recvBuffer = '';
            return;
        }

        try {
            call_user_func($this->onMessage, $this, $this->recvBuffer);
        } catch (\Exception $e) {
            Worker::log($e);
            exit(250);
        } catch (\Error $e) {
            Worker::log($e);
            exit(250);
        }

        //clean receive buffer
        $this->recvBuffer = '';
    }


    /**
     * Base write handler
     * @return void|bool
     */
    public function baseWrite()
    {
        set_error_handler(function () {
        });
        $len = @fwrite($this->socket, $this->sendBuffer);
        restore_error_handler();

        if ($len === strlen($this->sendBuffer)) {
            $this->bytesWritten += $len;
            Worker::$globalEvent->del($this->socket, EventInterface::EV_WRITE);
            $this->sendBuffer = '';
            //try to emit onBufferDrain callback when the send buffer becomes empty.
            if ($this->onBufferDrain) {
                try {
                    call_user_func($this->onBufferDrain, $this);
                } catch (\Exception $e) {
                    Worker::log($e);
                    exit(250);
                } catch (\Error $e) {
                    Worker::log($e);
                    exit(250);
                }
            }

            if ($this->status === self::STATUS_CLOSED) {
                $this->destroy();
            }
            true;
        }

        if ($len > 0) {
            $this->bytesWritten += $len;
            $this->sendBuffer = substr($this->sendBuffer, $len);
        } else {
            ++self::$statistics['send_fail'];
            $this->destroy();
        }

    }

    public function destroy()
    {
        //avoid repeated calls.
        if ($this->status === self::STATUS_CLOSED) {
            return;
        }

        Worker::$globalEvent->del($this->socket, EventInterface::EV_READ);
        Worker::$globalEvent->del($this->socket, EventInterface::EV_WRITE);

        try {
            @fclose($this->socket);
        } catch (\Exception $e) {
        } catch (\Error $e) {
        }

        $this->status = self::STATUS_CLOSED;

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
        if ($this->status === self::STATUS_CLOSED) {
            $this->onMessage = $this->onClose = $this->onError = $this->onBufferDrain = $this->onBufferFull = null;

            if ($this->worker) {
                unset($this->worker->connections[$this->_id]);
            }
            unset(static::$connection[$this->id]);
        }

    }

    /**
     * close connection
     * @param mixed $data
     * @param bool $raw
     * @return void
     */
    public function close($data = null, $raw = false)
    {
        if ($this->status === self::STATUS_CONNECTING) {
            $this->destroy();
            return;
        }

        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return;
        }

        if ($data !== null) {
            $this->send($data, $raw);
        }

        $this->status = self::STATUS_CLOSING;
        if ($this->sendBuffer === '') {
            $this->destroy();
        } else {
            $this->pauseRecv();
        }
    }

    /**
     *  sends data on the connection.
     * @param  mixed $sendBuffer
     * @param bool $raw
     * @return bool|null|
     */
    public function send($sendBuffer, $raw = false)
    {
        if ($this->status === self::STATUS_CLOSING || $this->status === self::STATUS_CLOSED) {
            return false;
        }
        //Try to call protocol::encode($sendBuffer) before sending
        if (false === $raw && $this->protocol !== null) {
            $parser = $this->protocol;
            Worker::log("send....");
            $sendBuffer = $parser::encode($sendBuffer, $this);
            Worker::log("send....end....");
            if ($sendBuffer === '') {
                return;
            }
        }
        Worker::log($sendBuffer);
        if ($this->status !== self::STATUS_ESTABLISHED) {
            if ($this->sendBuffer && $this->bufferIsFull()) {
                ++self::$statistics['send_fail'];
                return false;
            }
            $this->sendBuffer .= $sendBuffer;
            $this->checkBufferWillFull();
            return;
        }

        //Attempt to send data directly
        if ($this->sendBuffer === '') {
            $len = 0;
            try {
                $len = @fwrite($this->socket, $sendBuffer);
            } catch (\Exception $e) {
                Worker::log($e);
            } catch (\Error $e) {
                Worker::log($e);
            }
            if ($len === strlen($sendBuffer)) {
                $this->bytesWritten += $len;
                return true;
            }

            //send only part of the data
            if ($len > 0) {
                $this->sendBuffer = substr($sendBuffer, $len);
                $this->bytesWritten += $len;
            } else {
                if (!is_resource($this->socket) || feof($this->socket)) {
                    ++self::$statistics['send_fail'];
                    if ($this->onError) {
                        try {
                            call_user_func($this->onError, $this, WORKERMAN_SEND_FAIL, 'client closed');
                        } catch (\Exception $e) {
                            Worker::log($e);
                            exit(250);
                        } catch (\Error $e) {
                            Worker::log($e);
                            exit(250);
                        }
                    }
                    $this->destroy();
                    return false;
                }
                $this->sendBuffer = $sendBuffer;
            }

            Worker::$globalEvent->add($this->socket, EventInterface::EV_WRITE, array($this, 'baseWrite'));
            $this->checkBufferWillFull();
            return;
        }

        if ($this->bufferIsFull()) {
            ++self::$statistics['send_fail'];
            return false;
        }

        $this->sendBuffer .= $sendBuffer;
        $this->checkBufferWillFull();
    }


    /**
     * check whether the send buffer will be full
     * @return void
     */
    protected function checkBufferWillFull()
    {
        if ($this->maxSendBufferSize <= strlen($this->sendBuffer)) {
            if ($this->onBufferFull) {

                try {
                    call_user_func($this->onBufferFull, $this);
                } catch (\Exception $e) {
                    Worker::log($e);
                    exit(250);
                } catch (\Error $e) {
                    Worker::log($e);
                    exit(250);
                }
            }
        }

    }


    /**
     * Whether send buffer is full
     * @return bool
     */
    protected function bufferIsFull()
    {

        if ($this->maxSendBufferSize <= strlen($this->sendBuffer)) {
            if ($this->onError) {
                try {
                    call_user_func($this->onError, $this, WORKERMAN_SEND_FAIL, 'send buffer full and drop package');
                } catch (\Exception $e) {
                    Worker::log($e);
                    exit(250);
                } catch (\Error $e) {
                    Worker::log($e);
                    exit(250);
                }
            }
            return true;
        }
        return false;
    }


    /**
     * Pauses the reading of data. that is onMessage will not be emitted. Useful to throttle back an upload.
     * @return void
     */
    public function pauseRecv()
    {
        Worker::$globalEvent->del($this->socket, EventInterface::EV_READ);
        $this->isPaused = true;
    }


}
