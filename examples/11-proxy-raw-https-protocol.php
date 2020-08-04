<?php

// A simple example which requests https://google.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy-latest.php
//
// To run the example, go to the project root and run:
//
// $ php examples/11-proxy-raw-https-protocol.php
//
// The proxy can be given as first argument and defaults to localhost:8080 otherwise.
//
// For illustration purposes only. If you want to send HTTP requests in a real
// world project, take a look at example #01 and https://github.com/reactphp/http#client-usage.

use Clue\React\HttpProxy\ProxyConnector;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

require __DIR__ . '/../vendor/autoload.php';

$url = isset($argv[1]) ? $argv[1] : '127.0.0.1:8080';

$loop = React\EventLoop\Factory::create();

$proxy = new ProxyConnector($url, new Connector($loop));
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$connector->connect('tls://google.com:443')->then(function (ConnectionInterface $stream) {
    $stream->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $stream->on('data', function ($chunk) {
        echo $chunk;
    });
}, 'printf');

$loop->run();
