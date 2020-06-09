<?php

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, '127.0.0.1', 6666);

$fd = STDIN;


while ($data = fgetc($fd)) {
    socket_write($socket, $data, 1024);
}




socket_close($socket);




