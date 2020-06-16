<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';

// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new \app\Worker("http://0.0.0.0:8080");

// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onMessage = function ($connection, $request) {
    $data['get'] = $request->get();
};


\app\Worker::runAll();


