<?php

namespace app;

class Timer
{

    /**
     *基于警报信号的任务
     * [
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     *   ..
     * ]
     * @var array
     */
    protected static $tasks = array();


    /**
     * 事件
     * @var EventInterface
     */
    protected static $event = null;

    public function __construct()
    {
        return $this;
    }


    public static function init($event = null)
    {
        if ($event) {
            self::$event = $event;
            return;
        }
        //安装信号处理器
        if (function_exists('pcntl_signal')) {
            //SIGALRM - 终止当前进程,安装信号器，收到SIGALRM信号回调 signalHandle方法
            pcntl_signal(\SIGALRM, array('\app\Timer', "signalHandle"), false);
        }
    }

    public static function signalHandle()
    {
        if (!self::$event) {
            //创建一个计时器，在指定的秒数后向进程发送一个SIGALRM信号
            pcntl_alarm(1);
            self::tick();
        }
    }

    public static function tick()
    {
        if (empty(self::$tasks)) {
            pcntl_alarm(0);
            return;
        }
    }

}

