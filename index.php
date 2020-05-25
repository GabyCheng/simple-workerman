<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';
echo 11;
declare(ticks = 1);
function signal_handler($signal) {
    print "catch you ";
    // pcntl_alarm(5);
}
pcntl_signal(SIGALRM, "signal_handler", true);
pcntl_alarm(0);
while(1) {
    pcntl_signal_dispatch();
}

die;
new \app\Worker("websocket://0.0.0.0:2000");

\app\Worker::runAll();
