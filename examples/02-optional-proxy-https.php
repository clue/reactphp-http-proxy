<?php

// A simple example which requests https://google.com/ either directly or through
// an HTTP CONNECT proxy.
// The Proxy can be given as first argument or does not use a proxy otherwise.
// This example highlights how changing from direct connection to using a proxy
// actually adds very little complexity and does not mess with your actual
// network protocol otherwise.

use Clue\React\HttpProxy\ProxyConnector;
use React\Stream\Stream;
use React\SocketClient\TcpConnector;
use React\SocketClient\SecureConnector;
use React\SocketClient\DnsConnector;
use React\Dns\Resolver\Factory;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$tcp = new TcpConnector($loop);
$dnsFactory = new Factory();
$resolver = $dnsFactory->create('8.8.8.8', $loop);
$dns = new DnsConnector($tcp, $resolver);

// first argument given? use this as the proxy URL
if (isset($argv[1])) {
    $proxy = new ProxyConnector($argv[1], $dns);
    $connector = new SecureConnector($proxy, $loop);
} else {
    $connector = new SecureConnector($dns, $loop);
}

$connector->create('google.com', 443)->then(function (Stream $stream) {
    $stream->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $stream->on('data', function ($chunk) {
        echo $chunk;
    });
}, 'printf');

$loop->run();
