<?php

// A simple example which requests https://google.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy.php
//
// The proxy defaults to 127.0.0.1:8080.
// To run the example, go to the project root and run:
//
// $ php examples/21-proxy-raw-smtp-protocol.php
//
// To run the same example with your proxy, the proxy URL can be given as an environment variable:
//
// $ http_proxy=127.0.0.1:8080 php examples/21-proxy-raw-smtp-protocol.php
//
// Please note that MANY public proxies do not allow SMTP connections, YMMV.

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('http_proxy');
if ($url === false) {
    $url = '127.0.0.1:8080';
}

$proxy = new Clue\React\HttpProxy\ProxyConnector($url);

$connector = new React\Socket\Connector(array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$connector->connect('tcp://smtp.googlemail.com:587')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("EHLO local\r\n");
    $connection->on('data', function ($chunk) use ($connection) {
        echo $chunk;
        $connection->write("QUIT\r\n");
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
