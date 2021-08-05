<?php

// A simple example which requests https://google.com/ directly (optional: Through an HTTP CONNECT proxy.)
// To run the example, go to the project root and run:
//
// $ php examples/12-optional-proxy-raw-https-protocol.php
//
// If you chose the optional route, you can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy.php
//
// To run the same example with your proxy, the proxy URL can be given as an environment variable:
//
// $ http_proxy=127.0.0.2:8080 php examples/12-optional-proxy-raw-https-protocol.php
//
// This example highlights how changing from direct connection to using a proxy
// actually adds very little complexity and does not mess with your actual
// network protocol otherwise.
//
// For illustration purposes only. If you want to send HTTP requests in a real
// world project, take a look at example #01, example #02 and https://github.com/reactphp/http#client-usage.

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('http_proxy');
if ($url !== false) {
    $proxy = new Clue\React\HttpProxy\ProxyConnector($url);

    $connector = new React\Socket\Connector(array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
} else {
    $connector = new React\Socket\Connector();
}

$connector->connect('tls://google.com:443')->then(function (React\Socket\ConnectionInterface $connection) {
    $connection->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $connection->on('data', function ($chunk) {
        echo $chunk;
    });
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
