<?php

namespace app\Lib;

/**
 * 由于安装信号的函数不能使用，在init 里面不能使用$this,所以要写一个类去调用
 * Class Timer
 * @package app\Lib
 */
class Timer extends \app\Timer
{
}