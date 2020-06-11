<?php

namespace app\Events;


use app\Worker;

class Event implements EventInterface
{

    /**
     * eventBase
     * @var object
     */
    protected $eventBase = null;

    /**
     * event listeners of signal
     * @var array
     */
    protected $eventSignal = array();

    /**
     * all timer event listeners
     * [func, args, event, flag, timerId]
     * @var array
     */
    protected $eventTimer = array();

    /**
     * Timer id
     * @var int
     */
    protected static $timerId = 1;

    /**
     * all listeners for read/write event
     * @var array
     */
    protected $allEvent = array();


    /**
     * Event constructor.
     * @return void
     */
    public function __construct()
    {
        $this->eventBase = new \EventBase();
    }

    /**
     * @see EventInterface::add()
     */
    public function add($fd, $flag, $func, $args = null)
    {
        // TODO: Implement add() method.
        if (\class_exists('\\\\Event', false)) {
            $className = '\\\\Event';
        } else {
            $className = '\Event';
        }

        switch ($flag) {
            case self::EV_SIGNAL:
                $fdKey = (int)$flag;
                $event = $className::signal($this->eventBase, $fd, $func);
                if (!$event || !$event->add()) {
                    return false;
                }
                $this->eventSignal[$fdKey] = $event;
                return true;
            case self::EV_TIMER;
            case self::EV_TIMER_ONCE:
                $param = array($func, (array)$args, $flag, $fd, self::$timerId);
                $event = new $className($this->eventBase, -1, $className::TIMEOUT | $className::PERSIST, array($this, "timerCallback"), $param);
                if (!$event || !$event->addTimer($fd)) {
                    return false;
                }
                $this->eventTimer[self::$timerId] = $event;
                return self::$timerId++;
            default:
                $fdKey = (int)$fd;
                $realFlag = $flag === self::EV_READ ? $className::READ | $className::PERSIST : $className::WRITE | $className::PERSIST;
                Worker::log($realFlag);
                $event = new $className($this->eventBase, $fd, $realFlag, $func, $fd);

                if (!$event || !$event->add()) {
                    Worker::log('eeeeeeeeeeeee');
                    return false;
                }
                $this->allEvent[$fdKey][$flag] = $event;
                return true;
        }


    }


    /**
     * @see EventInterface::del()
     */
    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
            case self::EV_WRITE;
                $fdKey = (int)$fd;
                if (isset($this->allEvent[$fd][$flag])) {
                    $this->allEvent[$fdKey][$flag]->del();
                    unset($this->allEvent[$fdKey][$flag]);
                }
                if (empty($this->allEvent[$fdKey])) {
                    unset($this->allEvent[$fdKey]);
                }
                break;
            case self::EV_SIGNAL:
                $fdKey = (int)$fd;
                if (isset($this->eventSignal[$fdKey])) {
                    $this->eventSignal[$fdKey]->del();
                    unset($this->eventSignal[$fdKey]);
                }
                break;
            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                $fdKey = (int)$fd;
                if (isset($this->eventTimer[$fdKey])) {
                    $this->eventTimer[$fdKey]->del();
                    unset($this->eventTimer[$fdKey]);
                }
                break;
        }
        return true;
    }


    public function destory()
    {
        foreach ($this->eventSignal as $event) {
            $event->del();
        }
    }


    /**
     * @see EventInterface::clearAllTimer();
     * @return void
     */
    public function clearAllTimer()
    {
        foreach ($this->eventTimer as $event) {
            $event->del();
        }
        $this->eventTimer = array();
    }

    /**
     * @see EventInterface::getTimerCount()
     * @return int
     */
    public function getTimerCount()
    {
        return count($this->eventTimer);
    }

    /**
     * @see EventInterface::loop()
     */
    public function loop()
    {
        $this->eventBase->loop();
    }

    /**
     * Timer callback
     * @param $fd
     * @param $what
     * @param $param
     */
    public function timerCallback($fd, $what, $param)
    {
        $timerId = $param[4];

        if ($param[2] == self::EV_TIMER_ONCE) {
            $this->eventTimer[$timerId]->del();
            unset($this->eventTimer[$timerId]);
        }
        try {
            call_user_func_array($param[0], $param[1]);
        } catch (\Exception $e) {
            Worker::log($e);
            //该值会作为退出状态码，并且不会被打印输出。
            // 退出状态码应该在范围0至254，不应使用被PHP保留的退出状态码255。
            // 状态码0用于成功中止程序。
            exit(250);
        } catch (\Error $e) {
            Worker::log($e);
            exit(250);
        }

    }

}
