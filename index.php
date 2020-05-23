<?php
/**
 * Created by PhpStorm.
 * User: chengjiebin
 * Date: 2020/3/11
 * Time: 10:30 AM
 */
define('_ROOT', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require _ROOT . 'vendor/autoload.php';


class A
{
    function test()
    {
        $test2 = "A::test2";
        pcntl_signal(SIGINT, $test2);
        declare(ticks=1) {
            $a = 0;
            while (1) {
                $a++;
                echo $a . PHP_EOL;
                sleep(1);
            }
        }

    }

    public static function  test2()
    {
        echo "ni zhen sb";
    }
}

$a = new A();
$a->test();
die;
new \app\Worker("websocket://0.0.0.0:2000");

\app\Worker::runAll();
