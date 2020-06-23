<?php

namespace app\Events;

interface EventInterface
{

    /**
     * read event
     * @var int
     */
    const EV_READ = 1;

    /**
     * write event
     * @var int
     */
    const EV_WRITE = 2;

    /**
     * except event
     * @var int
     */
    const EV_EXCEPT = 3;

    /**
     * signal event
     * @var int
     */
    const EV_SIGNAL = 4;

    /**
     * Timer event
     * @var int
     */
    const EV_TIMER = 8;


    /**
     * Timer once event
     * @var int
     */
    const EV_TIMER_ONCE = 16;


    /**
     * add event listener to event loop
     * @param $fd
     * @param $flag
     * @param $func
     * @param null $args
     * @return bool
     */
    public function add($fd, $flag, $func, $args = null);


    /**
     * Remove event listener from event loop
     * @param $fd
     * @param $flag
     * @return bool
     */
    public function del($fd, $flag);


    /**
     * remove all timers
     * @return void
     */
    public function clearAllTimer();


    /**
     * main loop
     * @return  void
     */
    public function loop();


    /**
     * @return void
     */
    public function destroy();


    /**
     * get timer count
     * @return mixed
     */
    public function getTimerCount();


}
