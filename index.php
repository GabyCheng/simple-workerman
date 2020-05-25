<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';
$clean = false;
function shutdown_func(){
    global $clean;
    if (!$clean){
        die("not a clean shutdown");
    }
    return false;
}
register_shutdown_function("shutdown_func");
$a = 1;
$a = new FooClass(); // 将因为致命错误而失败
$clean = true;

die;
new \app\Worker("websocket://0.0.0.0:2000");

\app\Worker::runAll();
