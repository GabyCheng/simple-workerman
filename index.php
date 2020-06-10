<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';


$event = new \app\Events\Event();

$event->add(1, $event::EV_TIMER_ONCE, function ($a) {
    static $a;
    echo $a++;
}, 1);



//new \app\Worker("websocket://0.0.0.0:2000");
//\app\Worker::runAll();
