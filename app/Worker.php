<?php

namespace app;

require_once __DIR__ . '/Lib/Constants.php';

class Worker
{

    /**
     * 状态开始
     */
    const STATUS_STARTING = 1;

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
     * 当前状态
     * @var int
     */
    protected static $status = self::STATUS_STARTING;

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
     * worker_id 和 进程id 的映射关系
     * 格式 [worker_id=>[0=>$pid, 1=>$pid, ..], ..]
     * @var array
     */
    protected static $idMap = array();


    /**
     * 这个文件存储master进程id
     * @var string
     */
    public static $pidFile = '';


    /**
     * 运行
     */

    public static function runAll()
    {
        //检查运行环境
        static::checkSapiEnv();
        //初始化操作
        static::init();
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
        static::$startFile = $backtrace[count($backtrace) - 1]['file'];
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

        static::$status = self::STATUS_STARTING;
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
    public static function safeEcho($msg, $decorated = true)
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
    private static function outputStream($stream = null)
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


}