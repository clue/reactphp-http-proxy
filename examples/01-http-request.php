<?php

// A simple example which uses an HTTP client to request https://example.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy-latest.php
//
// To run the example, go to the project root and run:
//
// $ php examples/01-http-requests.php
//
// The proxy can be given as first argument and defaults to localhost:8080 otherwise.
use React\HTTP\Browser;

require __DIR__ . '/../vendor/autoload.php';

$url = isset($argv[1]) ? $argv[1] : '127.0.0.1:8080';

$loop = React\EventLoop\Factory::create();
$proxy = new Clue\React\HttpProxy\ProxyConnector($url, new React\Socket\Connector($loop));

$connector = new React\Socket\Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new React\Http\Browser($loop, $connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, 'printf');

$loop->run();
