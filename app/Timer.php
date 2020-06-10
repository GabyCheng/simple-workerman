<?php

namespace app;

use app\Events\EventInterface;

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
     * timerId
     * @var int
     */
    protected static $timerId = 0;


    /**
     * timer status
     * [
     * timer_id1 => bool,
     * timer_id2 => bool,
     * ]
     * @var array
     */
    protected static $status = array();

    /**
     * 事件
     * @var EventInterface
     */
    protected static $event = null;


    /**
     * init
     * @param EventInterface $event
     * @return void
     */
    public static function init($event = null)
    {
        if ($event) {
            self::$event = $event;
            return;
        }
        if (function_exists('pcntl_signal')) {
            //安装信号处理器 SIGALRM - 终止当前进程,安装信号器，收到SIGALRM信号回调 signalHandle方法
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

    /**
     * tick
     * @return void
     */
    public static function tick()
    {
        if (empty(self::$tasks)) {
            pcntl_alarm(0);
            return;
        }
        $now = time();
        foreach (self::$tasks as $runTime => $taskData) {
            if ($now >= $runTime) {
                foreach ($taskData as $index => $oneTask) {
                    $taskFunc = $oneTask[0];
                    $taskArgs = $oneTask[1];
                    $persistent = $oneTask[2];
                    $timeInterval = $oneTask[3];

                    try {
                        call_user_func_array($taskFunc, $taskArgs);
                    } catch (\Exception $e) {
                        Worker::safeEcho($e);
                    }

                    if ($persistent && !empty(self::$status[$index])) {
                        $newRunTime = time() + $timeInterval;
                        if (!isset(self::$tasks[$newRunTime])) {
                            self::$tasks[$newRunTime] = array();
                        }
                        self::$tasks[$newRunTime][$index] = array($taskFunc, (array)$taskArgs, $persistent, $timeInterval);
                    }
                }
                unset(self::$tasks[$runTime]);
            }
        }


    }


    /**
     * @param $timeInterval
     * @param $func
     * @param array $args
     * @param bool $persistent
     * @return int|bool
     */
    public static function add($timeInterval, $func, $args = array(), $persistent = true)
    {
        if ($timeInterval <= 0) {
            Worker::safeEcho(new \Exception('bad time interval'));
            return false;
        }

        if ($args === null) {
            $args = array();
        }

        if (self::$event) {
            return self::$event->add($timeInterval,
                $persistent ? EventInterface::EV_TIMER : EventInterface::EV_TIMER_ONCE, $func, $args);
        }

        if (!is_callable($func)) {
            Worker::safeEcho(new \Exception("not callable"));
            return false;
        }

        if (empty(self::$tasks)) {
            pcntl_alarm(1);
        }

        $runTime = time() + $timeInterval;
        if (!isset(self::$tasks[$runTime])) {
            self::$tasks[$runTime] = array();
        }

        self::$timerId = self::$timerId == PHP_INT_MAX ? 1 : ++self::$timerId;
        self::$status[self::$timerId] = true;
        self::$tasks[$runTime][self::$timerId] = array($func, (array)$args, $persistent, $timeInterval);

        return self::$timerId;
    }

    /**
     * remove a timer
     * @param mixed $timer_id
     * @return bool
     */
    public static function del($timer_id)
    {
        if (self::$event) {
            return self::$event->del($timer_id, EventInterface::EV_TIMER);
        }

        foreach (self::$tasks as $runTime => $taskData) {
            if (array_key_exists($timer_id, $taskData)) {
                unset(self::$tasks[$runTime][$timer_id]);
            }
        }

        if (array_key_exists($timer_id, self::$status)) {
            unset(self::$status[$timer_id]);
        }
        return true;
    }

    /**
     * remove all timers
     * @return void
     */
    public static function delAll()
    {
        static::$tasks = self::$status = array();
        pcntl_alarm(0);
        if (self::$event) {
            self::$event->clearAllTimer();
        }
    }


}

