<?php

// A simple example which uses a plain SMTP connection to Googlemail through a HTTP CONNECT proxy.
// Proxy can be given as first argument and defaults to localhost:8080 otherwise.
// Please note that MANY public proxies do not allow SMTP connections, YMMV.

use Clue\React\HttpProxy\ProxyConnector;
use React\Stream\Stream;
use React\SocketClient\TcpConnector;

require __DIR__ . '/../vendor/autoload.php';

$url = isset($argv[1]) ? $argv[1] : '127.0.0.1:8080';

$loop = React\EventLoop\Factory::create();

$connector = new TcpConnector($loop);
$proxy = new ProxyConnector($url, $connector);

$proxy->create('smtp.googlemail.com', 587)->then(function (Stream $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
        $stream->write("QUIT\r\n");
    });
}, 'printf');

$loop->run();
