<?php

//php启动过程中错误不会显示
//ini_set('display_errors', 'on');

//设置报告何种PHP错误
//error_reporting(E_ALL);

//是否使用 PCRE 的 JIT 编译.据说编译速度快
ini_set('pcre.jit', 0);

const WORKERMAN_CONNECT_FAIL = 1;
const WORKERMAN_SEND_FAIL = 1;

const OS_TYPE_LINUX = 'linux';

if (!class_exists('Error')) {
    class Error extends Exception
    {
    }

}

if (!interface_exists('SessionHandlerInterface')) {
    //session 操作
    interface SessionHandlerInterface
    {
        public function close();

        public function destroy($sessionKey);

        public function gc($maxLifeTime);

        public function open($savePath, $sessionName);

        public function read($sessionId);

        public function write($sessionId, $sessionData);
    }
}