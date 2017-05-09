<?php

// A simple example which uses a secure SMTP connection to Googlemail through a HTTP CONNECT proxy.
// Proxy can be given as first argument and defaults to localhost:8080 otherwise.
// This example highlights how changing from plain connections (see previous
// example) to using a secure connection actually adds very little complexity
// and does not mess with your actual network protocol otherwise.
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

$connector->connect('tls://smtp.googlemail.com:465')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
        $stream->write("QUIT\r\n");
    });
}, 'printf');

$loop->run();
