<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';


class Server
{
    private $host = '0.0.0.0';
    private $port = 6666;
    private $listenSocket = null;
    private $clientArray = array();
    private $closureArray = array();

    public function init()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_setopt($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, $this->host, $this->port);
        socket_listen($socket);
        socket_set_nonblock($socket);
        $this->clientArray = [$socket];
        $this->listenSocket = $socket;
    }


    public function on($event_name, Closure $func)
    {
        $this->closureArray[$event_name] = $func;
    }

    public function run()
    {

        while (true) {

            $read = $this->clientArray;
            $write = [];
            $except = [];
            $ret = socket_select($read, $write, $except, NULL);

            if ($ret <= 0) {
                continue;
            }

            //有链接
            if (in_array($this->listenSocket, $read)) {
                $conn = socket_accept($this->listenSocket);
                if (!$conn) {
                    continue;
                }
                $this->clientArray[] = $conn;
                $index = array_search($this->listenSocket, $read);
                unset($read[$index]);
                if ($this->closureArray['connect']) {
                    call_user_func_array($this->closureArray['connect'], array());
                }
            }

            print_r($read);

            print_r($this->clientArray);

            foreach ($read as $key => $value) {

                socket_recv($value, $content, 1024, 0);
                echo "client";
                print_r($this->clientArray);
                echo "read";
                print_r($read);
                if (!$content) {
                    unset($this->clientArray[$key]);
                    socket_close($value);
                    continue;
                }

                if ($this->closureArray['message']) {
                    call_user_func_array($this->closureArray['message'], array($content));
                }
              //  unset($this->clientArray[$key]);
               // socket_shutdown($value);
              //  socket_close($value);
            }


        }


    }


}

$server = new Server();
$server->init();

$server->on('connect', function () {
    echo 'connect';
});

$server->on('message', function ($data) {
    echo 'message' . $data;
});

$server->run();


die;
new \app\Worker("websocket://0.0.0.0:2000");

\app\Worker::runAll();
