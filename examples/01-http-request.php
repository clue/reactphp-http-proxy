<?php

// A simple example which uses an HTTP client to request https://example.com/ through an HTTP CONNECT proxy.
// You can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy.php
//
// The proxy defaults to localhost:8080.
// To run the example go to the project root and run:
//
// $ php examples/01-http-request.php
//
// To run the same example with your proxy, the proxy URL can be given as an environment variable:
//
// $ http_proxy=127.0.0.2:8080 php examples/01-http-request.php

require __DIR__ . '/../vendor/autoload.php';

$url = getenv('http_proxy');
if ($url === false) {
    $url = 'localhost:8080';
}

$proxy = new Clue\React\HttpProxy\ProxyConnector($url);

$connector = new React\Socket\Connector(null, array(
    'tcp' => $proxy,
    'dns' => false
));

$browser = new React\Http\Browser(null, $connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
