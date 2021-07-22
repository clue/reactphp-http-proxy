<?php

// A simple example which uses an HTTP client to request https://example.com/ (optional: Through an HTTP CONNECT proxy.)
// To run the example, go to the project root and run:
//
// $ php examples/02-optional-proxy-http-request.php
//
// If you chose the optional route, you can use any kind of proxy, for example https://github.com/leproxy/leproxy and execute it like this:
//
// $ php leproxy.php
//
// To run the same example with your proxy, the proxy URL can be given as an environment variable:
//
// $ http_proxy=127.0.0.2:8080 php examples/02-optional-proxy-http-request.php

require __DIR__ . '/../vendor/autoload.php';

$connector = null;
$url = getenv('http_proxy');
if ($url !== false) {
    $proxy = new Clue\React\HttpProxy\ProxyConnector($url);

    $connector = new React\Socket\Connector(null, array(
        'tcp' => $proxy,
        'timeout' => 3.0,
        'dns' => false
    ));
}

$browser = new React\Http\Browser(null, $connector);

$browser->get('https://example.com/')->then(function (Psr\Http\Message\ResponseInterface $response) {
    var_dump($response->getHeaders(), (string) $response->getBody());
}, function (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
});
