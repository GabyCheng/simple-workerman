<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';

function youtube($url, $width=560, $height=315, $fullscreen=true)
{
    $arr = [];
    $a = parse_url( $url, PHP_URL_QUERY );
    parse_str($a, $arr);
    print_r($arr);
}

// show youtube on my page
$url='http://www.youtube.com/watch?v=yvTd6XxgCBE&a=11';
youtube($url, 560, 315, true);
die;

new \app\Worker("websocket://0.0.0.0:8080");
\app\Worker::runAll();


