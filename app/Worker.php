<?php

namespace app;

use app\Events\EventInterface;

require_once __DIR__ . '/Lib/Constants.php';

class Worker
{

    /**
     * 状态开始
     */
    const STATUS_STARTING = 1;

    /**
     * Default backlog. Backlog is the maximum length of the queue of pending connections.
     *
     * @var int
     */
    const DEFAULT_BACKLOG = 102400;

    /**
     * 日志文件
     * @var string
     */
    public static $logFile = '';

    /**
     * worker 进程的数量
     * @var int
     */
    public $count = 1;

    /**
     * 进程名称
     * @var string
     */
    public static $processTitle = 'WorkerMan';

    /**
     * 操作系统
     * @var string
     */
    protected static $os = OS_TYPE_LINUX;

    /**
     * 标准输出流
     * @var null
     */
    protected static $outputStream = null;

    /**
     * 假如 $outputStream 支持装饰
     * @var null
     */
    protected static $outputDecorated = null;

    /**
     * 开始文件
     * @var string
     */
    protected static $startFile = '';

    /**
     * 暂停是否接受新连接
     * @var bool
     */
    protected $pauseAccept = true;

    /**
     * 加载的文件路径
     * @var string
     */
    protected $autoloadRootPath = '';


    /**
     * Maximum length of the worker names.
     *
     * @var int
     */
    protected static $_maxWorkerNameLength = 12;

    /**
     * Maximum length of the socket names.
     *
     * @var int
     */
    protected static $_maxSocketNameLength = 12;
    /**
     * Maximum length of the process user names.
     *
     * @var int
     */
    protected static $_maxUserNameLength = 12;

    /**
     * Maximum length of the Proto names.
     *
     * @var int
     */
    protected static $_maxProtoNameLength = 4;

    /**
     * Maximum length of the Processes names.
     *
     * @var int
     */
    protected static $_maxProcessesNameLength = 9;

    /**
     * Maximum length of the Status names.
     *
     * @var int
     */
    protected static $_maxStatusNameLength = 1;


    /**
     * Socket name. The format is like this http://0.0.0.0:80 .
     *
     * @var string
     */
    protected $socketName = '';

    /**
     * Context of socket.
     *
     * @var resource
     */
    protected $context = null;


    /**
     * 当前状态
     * @var int
     */
    protected static $_status = self::STATUS_STARTING;

    /**
     * 当前工作进程的状态信息
     * @var array
     */
    protected static $globalStatistics = array(
        'start_timestamp' => 0,
        'worker_exit_info' => array()
    );

    /**
     * 用来储存当前工作进程状态的信息文件
     * @var string
     */
    protected static $statisticsFile = '';

    /**
     * 所有的worker实例.
     *
     * @var Worker[]
     */
    protected static $workers = array();


    /**
     * The PID of master process.
     *
     * @var int
     */
    protected static $masterPid = 0;


    /**
     * Listening socket
     * @var resource
     */
    protected $mainSocket = null;

    /**
     * All worker processes pid.
     * The format is like this [worker_id=>[pid=>pid, pid=>pid, ..], ..]
     *
     * @var array
     */
    protected static $pidMap = array();
    /**
     * worker_id 和 进程id 的映射关系
     * 格式 [worker_id=>[0=>$pid, 1=>$pid, ..], ..]
     * @var array
     */
    protected static $idMap = array();


    /**
     * PHP built-in protocols.
     *
     * @var array
     */
    protected static $builtinTransports = array(
        'tcp' => 'tcp',
        'udp' => 'udp',
        'unix' => 'unix',
        'ssl' => 'tcp',
    );

    protected static $availableEventLoops = array(
        'libevent' => '\app\Events\Libevent',
        'event' => '\app\Events\Event',
    );


    /**
     * 这个文件存储master进程id
     * @var string
     */
    public static $pidFile = '';

    /**
     * 守护进程
     * @var bool
     */
    public static $daemonize = false;

    /**
     * 进程名称
     * @var string
     */
    public $name = 'none';

    /**
     * @var string
     */
    public $user = '';


    /**
     * Stdout file.
     *
     * @var string
     */
    public static $stdoutFile = '/dev/null';


    /**
     * 应用层协议
     * @var string
     */
    public $protocol = null;

    /**
     * Worker id.
     *
     * @var int
     */
    public $id = 0;

    /**
     * 成功建立套接字连接
     * @var callable
     */
    public $onConnect = null;

    /**
     * 接收数据触发
     * @var callable
     */
    public $onMessage = null;

    /**
     * worker 进程开启触发
     * @var callable
     */
    public $onWorkerStart = null;

    /**
     * 断开连接触发
     * @var callable
     */
    public $onClose = null;


    /**
     * 连接发生错误
     * @var callable
     */
    public $onError = null;


    /**
     * Transport layer protocol.
     * 传输层协议
     * @var string
     */
    public $transport = 'tcp';


    /**
     * reuse port.
     *
     * @var bool
     */
    public $reusePort = false;

    /**
     * Unix group of processes, needs appropriate privileges (usually root).
     * @var string
     */
    public $group = '';


    /**
     * EventLoopClass
     * @var string
     */
    public static $eventLoopClass = '';


    /**
     * Get UI columns to be shown in terminal
     * 暂时没搞懂，先抄过来
     * 1. $column_map: array('ui_column_name' => 'clas_property_name')
     * 2. Consider move into configuration in future
     *
     * @return array
     */
    public static function getUiColumns()
    {
        return array(
            'proto' => 'transport',
            'user' => 'user',
            'worker' => 'name',
            'socket' => 'socket',
            'processes' => 'count',
            'status' => 'status',
        );
    }


    /**
     * Global event loop.
     *
     * @var Events\EventInterface
     */
    public static $globalEvent = null;

    /**
     * 运行
     * @throws \Exception
     */

    public static function runAll()
    {
        //检查运行环境
        static::checkSapiEnv();
        //初始化操作
        static::init();
        //加锁
        static::lock();
        //解析命令行
        static::parseCommand();
        //守护进程
        static::daemonize();
        //初始化workers
        static::initWorkers();
        //安装信号
        //static::installSignal();
        //保存master pid
        static::saveMasterPid();
        //解锁
        static::unlock();
        //展示连接情况,不重要先不写
        static::displayUI();
        //字如其名，fork worker 进程
        static::forkWorkers();
    }


    /**
     * Display staring UI.
     *
     * @return void
     */
    protected static function displayUI()
    {

    }


    /**
     * Fork some worker processes.
     *
     * @return void
     * @throws \Exception
     */
    protected static function forkWorkers()
    {
        //Array
        //(
        //    [000000006b0156170000000009fc0bd0] => app\Worker Object
        //        (
        //            [count] => 1
        //            [autoloadRootPath:protected] =>
        //            [socketName:protected] => websocket://0.0.0.0:2000
        //            [context:protected] => Resource id #10
        //            [mainSocket:protected] => Resource id #17
        //            [name] => none
        //            [user] => chengjiebin
        //            [protocol] => \app\Protocols\Websocket
        //            [transport] => tcp
        //            [reusePort] =>
        //            [group] =>
        //            [workerId] => 000000006b0156170000000009fc0bd0
        //            [socket] => websocket://0.0.0.0:2000
        //            [status] => <g> [OK] </g>
        //        )
        //
        //)
        //print_r(static::$workers);
        foreach (static::$workers as $worker) {
            if (static::$_status === static::STATUS_STARTING) {
                if (empty($worker->name)) {
                    $worker->name = $worker->getSocketName();
                }
                $workerNameLength = strlen($worker->name);
                if (static::$_maxWorkerNameLength < $workerNameLength) {
                    static::$_maxWorkerNameLength = $workerNameLength;
                }
            }
            while (count(static::$pidMap[$worker->workerId]) < $worker->count) {
                static::forkOneWorker($worker);
            }
        }

    }

    /**
     * Fork one worker process.
     *
     * @param self $worker
     * @throws \Exception
     */
    protected static function forkOneWorker(self $worker)
    {
        //获取可用的worker id
        //Array
        //(
        //    [000000006b0156170000000009fc0bd0] => Array
        //        (
        //            [0] => 0
        //        )
        //
        //)
        //print_r(static::$idMap);

        //Array
        //(
        //    [000000006b0156170000000009fc0bd0] => Array
        //        (
        //        )
        //
        //)
        //
        //print_r(static::$pidMap);
        $id = static::getId($worker->workerId, 0);
        if ($id === false) {
            return;
        }
        $pid = pcntl_fork();
        //父进程
        if ($pid > 0) {
            static::$pidMap[$worker->workerId][$pid] = $pid;
            static::$idMap[$worker->workerId][$id] = $pid;
        } elseif (0 === $pid) {//子进程
            //随机数
            srand();
            mt_srand();
            // 这里是socket部分，如果端口复用，就直接listen
            if ($worker->reusePort) {
                $worker->listen();
            }
            static::$pidMap = array();
            //重定向标准输出
            if (static::$_status === static::STATUS_STARTING) {
                static::resetStd();
            }

            //移除其他的监听
            foreach (static::$workers as $key => $oneWorker) {
                if ($oneWorker->workerId !== $worker->id) {
                    //搞不懂这里先不写
                    $oneWorker->unListen();
                    unset(static::$workers[$key]);
                }
            }

            //删除原先的定时器，搞不懂。后面再写

            //设置进程名称
            static::setProcessTitle(self::$processTitle . ':worker process' . $worker->name . '' . $worker->getSocketName());

            //设置用户和用户组
            $worker->setUserAndGroup();

            $worker->id = $id;

            //
            $worker->run();
            $err = new \Exception('event-loop exited');
            static::log($err);
            //异常退出
            exit(250);
        } else {
            new \Exception("forkOneWorker fail");
        }

    }

    /**
     * @param $workerId
     * @param $pid
     * @return int
     */
    protected static function getId($workerId, $pid)
    {
        return array_search($pid, static::$idMap[$workerId]);
    }


    /**
     * Set unix user and group for current process.
     *
     * @return void
     */
    public function setUserAndGroup()
    {
        //get uid
        $userInfo = posix_getpwnam($this->user);
        if (!$userInfo) {
            static::log("Warning: User {$this->user} not exists");
            return;
        }

        $uid = $userInfo['uid'];
        if ($this->group) {
            $groupInfo = posix_getgrnam($this->group);
            if (!$groupInfo) {
                static::log("Warning: Group {$this->group} not exists");
                return;
            }
            $gid = $groupInfo['gid'];

        } else {
            $gid = $userInfo['gid'];
        }

        if ($uid !== posix_getuid() || $gid !== posix_getgrgid()) {
            if (!posix_setgid($gid) || !posix_initgroups($userInfo['name'], $gid) || posix_setuid($uid)) {
                static::log("Warning: change gid or uid fail.");
            }
        }

    }

    /**
     * unListen.
     *
     * @return void
     */
    public function unListen()
    {

    }

    public static function delAll()
    {
        //下面定了一个5秒后的闹铃信号，并捕捉。
        //如果seconds设置为0,将不会创建alarm信号。
        //pcntl_signal(SIGALRM, function () {
        //    echo 'Received an alarm signal !' . PHP_EOL;
        //}, false);
        //
        //pcntl_alarm(5);
        //
        //while (true) {
        //    pcntl_signal_dispatch();
        //    sleep(1);
        //}


    }

    public function run()
    {
        //更新进程的状态
        static::$_status = static::STATUS_STARTING;

        //可以在终止脚本前回调
        register_shutdown_function(array("\\app\\Worker", 'checkErrors'));

        // Create a global event loop.
        if (!static::$globalEvent) {
            $eventLoopClass = static::getEventLoopName();
            static::$globalEvent = new $eventLoopClass;
            $this->resumeAccept();
        }
        //Reinstall signal
        static::reinstallSignal();
        //init Timer
        Timer::init(static::$globalEvent);

        //set an empty onMessage callback
        if (empty($this->onMessage)) {
            $this->onMessage = function () {
            };
        }
        //还原之前的错误处理函数，重置set handler
        restore_error_handler();

        if ($this->onWorkerStart) {
            try {
                call_user_func($this->onWorkerStart, $this);
            } catch (\Exception $e) {
                static::log($e);
                sleep(1);
                exit(250);
            } catch (\Error $e) {
                static::log($e);
                sleep(1);
                exit(250);
            }
        }
        static::$globalEvent->loop();
    }

    /**
     * 重新安装信号
     * @return void
     */
    public static function reinstallSignal()
    {
        $signalHandler = '\Workerman\Worker::signalHandler';
        // uninstall stop signal handler
        \pcntl_signal(\SIGINT, \SIG_IGN, false);
        // uninstall graceful stop signal handler
        \pcntl_signal(\SIGTERM, \SIG_IGN, false);
        // uninstall reload signal handler
        \pcntl_signal(\SIGUSR1, \SIG_IGN, false);
        // uninstall graceful reload signal handler
        \pcntl_signal(\SIGQUIT, \SIG_IGN, false);
        // uninstall status signal handler
        \pcntl_signal(\SIGUSR2, \SIG_IGN, false);
        // uninstall connections status signal handler
        \pcntl_signal(\SIGIO, \SIG_IGN, false);
        // reinstall stop signal handler
        static::$globalEvent->add(\SIGINT, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall graceful stop signal handler
        static::$globalEvent->add(\SIGTERM, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall reload signal handler
        static::$globalEvent->add(\SIGUSR1, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall graceful reload signal handler
        static::$globalEvent->add(\SIGQUIT, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall status signal handler
        static::$globalEvent->add(\SIGUSR2, EventInterface::EV_SIGNAL, $signalHandler);
        // reinstall connection status signal handler
        static::$globalEvent->add(\SIGIO, EventInterface::EV_SIGNAL, $signalHandler);

    }

    public static function checkErrors()
    {

    }

    protected static function getEventLoopName()
    {
        if (static::$eventLoopClass) {
            return static::$eventLoopClass;
        }

        $loopName = '';

        foreach (static::$availableEventLoops as $name => $class) {
            //优先加载第一个
            if (extension_loaded($name)) {
                $loopName = $name;
                break;
            }
        }

        if ($loopName) {
            //这里循环渐进，先用libevent库
            static::$eventLoopClass = static::$availableEventLoops[$loopName];
        }


    }


    /**
     * 重定向标准输出和输入
     *
     * @throws \Exception
     */
    public static function resetStd()
    {
        if (!static::$daemonize) {
            return;
        }

        global $STDOUT, $STDERR;
        $handle = fopen(static::$stdoutFile, "a");
        if ($handle) {
            unset($handle);
            set_error_handler(function () {
            });
            fclose($STDOUT);
            fclose($STDERR);
            fclose(\STDOUT);
            fclose(\STDERR);
            $STDOUT = fopen(static::$stdoutFile, "a");
            $STDERR = fopen(static::$stdoutFile, "a");
            //修改输出流
            static::$outputStream = null;
            static::outputStream($STDOUT);
            restore_error_handler();
            return;
        }

        throw new \Exception('Can not open stdoutFile' . static::$stdoutFile);
    }


    /**
     * Save pid.
     *
     * @throws \Exception
     */
    protected static function saveMasterPid()
    {
        static::$masterPid = posix_getpid();
        if (false === \file_put_contents(static::$pidFile, static::$masterPid)) {
            throw new Exception('can not save pid to ' . static::$pidFile);
        }
    }

    /**
     * 检查sapi运行环境
     * @return void
     */
    public static function checkSapiEnv()
    {
        if (PHP_SAPI != 'cli') {
            exit("only run in command line mode \n");
        }

        //只能运行在linux系统下。
        if (DIRECTORY_SEPARATOR === '\\') {
            exit("only run linux env");
        }
    }

    /**
     * 初始化操作
     * @return void
     */
    public static function init()
    {
        //设置用户自定义的错误处理函数
        set_error_handler(function ($code, $msg, $file, $line) {
            static::safeEcho("$msg in file $file on line $line\n");
        });

        //产生一条回溯跟踪
        $backtrace = debug_backtrace();
        //开始文件名
        static::$startFile = $backtrace[count($backtrace) - 1]['file'];//index.php
        $uniquePrefix = str_replace('/', '_', static::$startFile);
        //定义pid文件路径和名字
        if (empty(static::$pidFile)) {
            static::$pidFile = __DIR__ . "/../$uniquePrefix.pid";
        }
        //定义日志文件名
        if (empty(static::$logFile)) {
            static::$logFile = __DIR__ . '/../workerman.log';
        }

        $logFile = (string)static::$logFile;

        //文件不存在 创建文件赋予权限
        if (!is_file($logFile)) {
            touch($logFile);
            chmod($logFile, 0622);
        }

        static::$_status = self::STATUS_STARTING;
        static::$globalStatistics['start_timestamp'] = time();
        //sys_get_temp_dir返回临时文件的目录
        static::$statisticsFile = sys_get_temp_dir() . "/$uniquePrefix.status";
        //设置进程名称
        static::setProcessTitle(static::$processTitle . ': master process start_file' . static::$startFile);
        //初始化映射关系
        self::initId();
        //初始化定时器
        Timer::init();
    }

    /**
     * run as deamon mode.
     * @throws Exception
     */
    protected static function daemonize()
    {
        //守护进程即是让当前进程脱离终端控制，否则终端关闭进程也关闭了
        static::$daemonize = 1;
        if (!static::$daemonize) {
            return;
        }
        //设置权限，看了unix网络编程，这个函数是创建屏蔽字也就是设置权限
        umask(0);
        // fork进程，主进程退出执行，子进程继续执行
        // 注意这里的子进程不是指Worker进程
        // 尽管是子进程，但他却依然是Master进程
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            exit(0);
        }
        // 子进程（Master进程）使用posix_setsid()创建新会话和进程组
        // 这一句话便足以让当前进程脱离控制终端！
        if (-1 === posix_setsid()) {
            throw new Exception("setsid fail");
        }
        // 避免SVR4某些情况下情况下进程会再次获得控制终端
        $pid = pcntl_fork();
        // 主进程再次终止运行，最终的子进程会成为Master进程变成
        // daemon程序运行在后台，然后继续fork出Worker进程
        if (-1 === $pid) {
            throw new Exception("fork fail");
        } elseif (0 !== $pid) {
            exit(0);
        }

    }


    /**
     * 初始化worker实例
     * @throws \Exception
     */
    protected static function initWorkers()
    {

        foreach (static::$workers as $worker) {

            if (empty($worker->name)) {
                $worker->name = 'none';
            }

            if (empty($worker->user)) {
                $worker->user = static::getCurrentUser();
            } else {
                //必须拥有根权限才能修改uid 和 gid
                if (posix_getuid() !== 0 && $worker->name !== static::getCurrentUser()) {
                    static::log('Warning: You must have the root privileges to change uid and gid');
                }
            }

            //socket name
            $worker->socket = $worker->getSocketName();


            $worker->status = '<g> [OK] </g>';
            //得到这些列的长度。。。暂时不知道做什么的
            foreach (static::getUiColumns() as $columnName => $prop) {
                !isset($worker->{$prop}) && $worker->{$prop} = 'NNNN';
                $propLength = strlen($worker->{$prop});
                $key = '_max' . ucfirst(strtolower($columnName)) . 'NameLength';
                static::$$key = max(static::$$key, $propLength);
            }

            //没有开启端口复用，监听
            if (!$worker->reusePort) {
                $worker->listen();
            }

        }
    }


    /**
     * Listen
     *
     * @throws \Exception
     */
    public function listen()
    {
        if (!$this->socketName) {
            return;
        }

        if (!$this->mainSocket) {
            //tcp://0.0.0.0:2000
            $localSocket = $this->parseSocketAddress();

            //Flag
            $flags = $this->transport === 'udp' ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;
            $errno = 0;
            $errMsg = '';
            //SO_REUSEPORT
            if ($this->reusePort) {
                //设置socket协议
                stream_context_set_option($this->context, 'socket', 'so_reuseport', 1);
            }

            //创建Internet或Unix域服务器套接字
            //由于创建一个SOCKET的流程总是 socket、bind、listen，所以PHP提供了一个非常方便的函数一次性创建、绑定端口、监听端口
            $this->mainSocket = stream_socket_server($localSocket, $errno, $errMsg, $flags, $this->context);
            if (!$this->mainSocket) {
                throw new \Exception($errMsg);
            }

            if ($this->transport === 'ssl') {
                //在已连接的套接字上打开/关闭加密
                stream_socket_enable_crypto($this->mainSocket, false);
            } elseif ($this->transport === 'unix') {
                $socketFile = substr($localSocket, 7);
                if ($this->user) {
                    chown($socketFile, $this->user);
                }
                if ($this->group) {
                    chgrp($socketFile, $this->group);
                }
            }

            if (function_exists('socket_import_stream') && static::$builtinTransports[$this->transport] === 'tcp') {
                set_error_handler(function () {
                });
                //将stream_socket对象转为socket对象，先用stream可能是为了减少繁琐的创建监听绑定吧。
                //stream_socket与sockets相比有个缺点，无法精确设置socket选项。当需要设置stream_socket选项时，
                //可以通过http://php.net/manual/en/function.socket-import-stream.php将stream_socket转换成扩展的sockets，
                //然后就可以通过http://php.net/manual/en/function.socket-set-option.php设置stream_socket的socket选项了。
                $socket = socket_import_stream($this->mainSocket);
                socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
                socket_set_option($socket, SOL_TCP, TCP_NODELAY, 1);
                restore_error_handler();
            }
            // 设置非阻塞
            \stream_set_blocking($this->mainSocket, false);
        }

        //接收连接
        $this->resumeAccept();
    }


    /**
     * 接受新的连接
     * @return void
     */

    public function resumeAccept()
    {
        if (static::$globalEvent && true === $this->pauseAccept && $this->mainSocket) {
            if ($this->transport !== 'udp') {
                static::$globalEvent->add($this->mainSocket, EventInterface::EV_READ, array($this, 'acceptConnection'));
            }
//            $this->pauseAccept = false;
        }

    }

    /**
     * accept a connection
     * @param resource $socket
     * @return void
     */
    public function acceptConnection($socket)
    {
        set_error_handler(function () {
        });
        $newSocket = stream_socket_accept($socket);
        restore_error_handler();

        if (!$newSocket) {
            return;
        }

        //TcpConnection



    }


    /**
     * 解析本地socket地址
     * @throws \Exception
     */
    protected function parseSocketAddress()
    {
        if (!$this->socketName) {
            return;
        }
        //Get the application layer communication protocol and listening address
        //websocket ://0.0.0.0:2000
        list($scheme, $address) = explode(':', $this->socketName, 2);
        if (!isset(static::$builtinTransports[$scheme])) {
            $scheme = ucfirst($scheme);
            $this->protocol = substr($scheme, 0, 1) === '\\' ? $scheme : '\\Protocols\\' . $scheme;
            //判断自定义协议是否存在
            if (!class_exists($this->protocol)) {
                $this->protocol = "\\app\\Protocols\\$scheme";
                if (!class_exists($this->protocol)) {
                    throw new \Exception("class {$this->protocol} not exist");
                }
            }
            if (!isset(static::$builtinTransports[$this->transport])) {
                throw new \Exception('Bad worker->transport' . var_export($this->transport, true));
            }

        } else {
            $this->transport = $scheme;
        }

        return static::$builtinTransports[$this->transport] . ":" . $address;
    }


    /**
     * Install signal handler.
     *
     * @return void
     */
    protected static function installSignal()
    {
        $signalHandler = '\app\Worker::signalHandler';
        // stop
        \pcntl_signal(\SIGINT, $signalHandler, false);
        // graceful stop
        \pcntl_signal(\SIGTERM, $signalHandler, false);
        // reload
        \pcntl_signal(\SIGUSR1, $signalHandler, false);
        // graceful reload
        \pcntl_signal(\SIGQUIT, $signalHandler, false);
        // status
        \pcntl_signal(\SIGUSR2, $signalHandler, false);
        // connection status
        \pcntl_signal(\SIGIO, $signalHandler, false);
        // ignore
        \pcntl_signal(\SIGPIPE, \SIG_IGN, false);
    }


    /**
     * 信号回调
     * @param $signal
     */
    public function signalHandler($signal)
    {

    }

    /**
     * get socket name
     * @return string
     */
    public function getSocketName()
    {
        //lcfirst 第一个字母为小写
        return $this->socketName ? lcfirst($this->socketName) : 'none';
    }

    /**
     * 获取工作进程的用户
     * @return string
     */
    protected static function getCurrentUser()
    {
        $userInfo = posix_getpwuid(posix_getuid());
        return $userInfo['name'];
    }


    /**
     * Lock
     * @return void
     */
    protected static function lock()
    {
        //对打开的这个文件加读锁
        $fd = \fopen(static::$startFile, 'r');
        if ($fd && !flock($fd, LOCK_EX)) {//LOCK_EX取得独占锁
            static::log('Workerman[' . static::$startFile . '] already running.');
        }
    }


    /**
     * unlock
     * @return void
     */
    protected static function unlock()
    {
        $fd = fopen(static::$startFile, 'r');
        $fd && flock($fd, LOCK_UN);
    }


    /**
     * 解析命令行这里不能先写，等后面安装信号弄明白再回头写。。。
     */
    protected static function parseCommand()
    {
        //获取命令行参数
        global $argv;
        $startFile = $argv[0];

        $availableCommands = [
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        ];
        //提示使用方法
        $usage = "Usage: php yourfile <command> [mode]\nCommands: \nstart\t\tStart worker in DEBUG mode.\n\t\tUse mode -d to start in DAEMON mode.\nstop\t\tStop worker.\n\t\tUse mode -g to stop gracefully.\nrestart\t\tRestart workers.\n\t\tUse mode -d to start in DAEMON mode.\n\t\tUse mode -g to stop gracefully.\nreload\t\tReload codes.\n\t\tUse mode -g to reload gracefully.\nstatus\t\tGet worker status.\n\t\tUse mode -d to show live status.\nconnections\tGet worker connections.\n";
        //校验命令
        if (!isset($argv[1]) || !in_array($argv[1], $availableCommands)) {
            if (isset($argv[1])) {
                static::safeEcho('Unknown command:' . $argv[1] . '\n');
            }
            exit($usage);
        }

        //获取命令
        $command = trim($argv[1]);
        $command2 = isset($argv[2]) ?? '';

        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d' || static::$daemonize) {
                $mode = 'in DAEMON mode';
            } else {
                $mode = 'in DEBUG mode';
            }
        }

        static::log("Workerman[$startFile] $command $mode");

        $masterPid = is_file(static::$pidFile) ? file_get_contents(static::$pidFile) : 0;
        //判断进程是否存活 0 默认信号处理程序
        $masterIsAlive = $masterPid && posix_kill($masterPid, 0) && posix_getpid() !== $masterPid;
        if ($masterIsAlive) {
            if ($command == 'start') {
                static::log("Workerman[$startFile] alerady running");
                exit;
            } elseif ($command !== 'start' && $command !== 'restart') {
                static::log("Workerman[$startFile] not run");
                exit;
            }
        }

        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    static::$daemonize = true;
                }
                break;
            case 'status':
                while (1) {
                    if (is_file(static::$statisticsFile)) {
                        @unlink(static::$statisticsFile);
                    }
                    //master进程发送信号去终止所有的子进程
                    posix_kill($masterPid, SIGUSR2);//终止进程
                    sleep(1);
                    if ($command2 === '-d') {
                        static::safeEcho("\33[H\33[2J\33(B\33[m", true);
                    }
                    //状态这里
//                    static::safeEcho(static::formatStatusData());
                    if ($command2 !== '-d') {
                        exit(0);
                    }
                    static::safeEcho("\n Press Ctrl+c to quit.\n\n");
                }
                exit(0);

            default :
                exit(0);
        }


    }

    protected static function formatStatusData()
    {

    }


    /**
     * @param $msg
     * @return void
     */
    public static function log($msg)
    {
        $msg = $msg . "\n";
        if (!static::$daemonize) {
            static::safeEcho($msg);
        }
        //守护进程
        $file = (string)static::$logFile;
        $data = date('Y-m-d H:i:s') . '' . 'pid' . posix_getpid() . $msg;
        file_put_contents($file, $data, FILE_APPEND | LOCK_EX);
    }


    /**
     * 初始化worker_id映射关系
     * @return void
     */
    protected static function initId()
    {
        foreach (static::$workers as $worker_id => $worker) {
            $newIdMap = array();
            $worker->count = $worker->count < 1 ? 1 : $worker->count;
            for ($i = 0; $i < $worker->count; $i++) {
                $newIdMap[$i] = static::$idMap[$worker_id][$i] ?? 0;
            }
            static::$idMap[$worker_id] = $newIdMap;
        }
    }

    /**
     * 设置进程名称
     * @param $title
     * @return void
     */
    protected static function setProcessTitle($title)
    {
        set_error_handler(function () {
        });

        if (function_exists('cli_set_process_title')) {
            cli_set_process_title($title);
        } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            setproctitle($title);
        }

        restore_error_handler();
    }


    /**
     * 错误输出
     * @param $msg
     * @param bool $decorated
     * @return bool
     */
    public
    static function safeEcho($msg, $decorated = true)
    {
        $stream = static::outputStream();
        if (!$stream) {
            return false;
        }
        //$decorated = false;
        if (!$decorated) {
            $line = $white = $green = $end = '';
            if (static::$outputDecorated) {
                $line = "\033[1A\n\033]K";
                $white = "\033[47;30m";
                $green = "\033[32;40m";
                $end = "\033[0m";
            }
            $msg = \str_replace(['<n>', '<w>', '<g>'], [$line, $white, $green], $msg);
            $msg = \str_replace(['</n>', '</w>', '</g>'], $end, $msg);
        } elseif (!static::$outputDecorated) {
            return false;
        }
        //标准输出流。写入到标准输出
        fwrite($stream, $msg);
        //清空缓冲区
        fflush($stream);
        return true;

    }

    /**
     * 标准化输出1
     * @param null $stream
     * @return bool|false|resource
     */
    private
    static function outputStream($stream = null)
    {
        if (!$stream) {
            $stream = static::$outputStream ?: \STDOUT;
        }

        //是否是一个资源类型
        if (!$stream || !is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            return false;
        }
        //读取文件内容
        $stat = fstat($stream);

        if (!$stat) {
            return false;
        }
        //校验文件权限
        if (($stat["mode"] && 0170000) === 0100000) {
            static::$outputDecorated = false;
        } else {
            //posix_isatty是否连接到终端的描述符 是的话返回true 不是的话返回false,是终端的话会把错误输出到终端
            self::$outputDecorated = function_exists('posix_isatty') && posix_isatty($stream);
        }

        return static::$outputStream = $stream;
    }


    public function __construct($socketName = '', array $contextOption = array())
    {
        //保存所有的worker实例
        $this->workerId = spl_object_hash($this);
        static::$workers[$this->workerId] = $this;
        static::$pidMap[$this->workerId] = array();

        //socket 上下文
        if ($socketName) {
            $this->socketName = $socketName;
            $contextOption['socket']['backlog'] = $contextOption['socket']['backlog'] ?? self::DEFAULT_BACKLOG;
            //创建并返回一个文本数据流并应用各种选项，可用于fopen(),file_get_contents()等过程的超时设置、代理服务器、请求方式、头信息设置的特殊过程。
            //stream系其实就是PHP中流的概念，流对各种协议都做了一层抽象封装，比如[ http:// ]、[ file:// ]、[ ftp:// ]、[ php://input ]等等，
            //也就说流系列函数提供了统一的函数来处理各种各样的花式协议。
            $this->context = stream_context_create($contextOption);
        }

        //端口复用
        if (\version_compare(\PHP_VERSION, '7.0.0', 'ge') // if php >= 7.0.0
            && \strtolower(\php_uname('s')) !== 'darwin' // if not Mac OS
            && $this->transport !== 'unix') { // if not unix socket
            $this->reusePort = true;
        }

    }


}
