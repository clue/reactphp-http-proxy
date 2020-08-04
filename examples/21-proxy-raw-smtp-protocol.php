<?php

// A simple example which requests https://google.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy-latest.php
//
// To run the example, go to the project root and run:
//
// $ php examples/21-proxy-raw-smtp-protocol.php
//
// The proxy can be given as first argument and defaults to localhost:8080 otherwise.
// Please note that MANY public proxies do not allow SMTP connections, YMMV.

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

$connector->connect('tcp://smtp.googlemail.com:587')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
        $stream->write("QUIT\r\n");
    });
}, 'printf');

$loop->run();
