<?php

// A simple example which requests https://google.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy.php
//
// The proxy defaults to localhost:8080.
// To run the example, go to the project root and run:
//
// $ php examples/22-proxy-raw-smtps-protocol.php
//
// To run the same example with your proxy, the proxy URL can be given as an environment variable:
//
// $ http_proxy=127.0.0.2:8080 php examples/22-proxy-raw-smtps-protocol.php
//
// This example highlights how changing from plain connections (see previous
// example) to using a secure connection actually adds very little complexity
// and does not mess with your actual network protocol otherwise.
// Please note that MANY public proxies do not allow SMTP connections, YMMV.

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('http_proxy');
if ($url === false) {
    $url = 'localhost:8080';
}

$proxy = new Clue\React\HttpProxy\ProxyConnector($url);

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$connector->connect('tls://smtp.googlemail.com:465')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("EHLO local\r\n");
    $connection->on('data', function ($chunk) use ($connection) {
        echo $chunk;
        $connection->write("QUIT\r\n");
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
