<?php

// A simple example which requests https://google.com/ either directly or through
// an HTTP CONNECT proxy.
// The Proxy can be given as first argument or does not use a proxy otherwise.
// This example highlights how changing from direct connection to using a proxy
// actually adds very little complexity and does not mess with your actual
// network protocol otherwise.

use Clue\React\HttpProxy\ProxyConnector;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$connector = new Connector($loop);

// first argument given? use this as the proxy URL
if (isset($argv[1])) {
    $proxy = new ProxyConnector($argv[1], $connector);
    $connector = new Connector($loop, array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
}

$connector->connect('tls://google.com:443')->then(function (ConnectionInterface $stream) {
    $stream->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $stream->on('data', function ($chunk) {
        echo $chunk;
    });
}, 'printf');

$loop->run();
