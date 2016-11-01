<?php

namespace Tests\Clue\React\HttpProxy;

use React\EventLoop\Factory;
use Clue\React\HttpProxy\ProxyConnector;
use React\SocketClient\TcpConnector;
use React\SocketClient\DnsConnector;
use Clue\React\Block;
use React\SocketClient\SecureConnector;

class FunctionalTest extends AbstractTestCase
{
    private $loop;
    private $tcpConnector;
    private $dnsConnector;

    public function setUp()
    {
        $this->loop = Factory::create();

        $this->tcpConnector = new TcpConnector($this->loop);

        $f = new \React\Dns\Resolver\Factory();
        $resolver = $f->create('8.8.8.8', $this->loop);

        $this->dnsConnector = new DnsConnector($this->tcpConnector, $resolver);
    }

    public function testPlainGoogleDoesNotAcceptConnectMethod()
    {
        $proxy = new ProxyConnector('google.com', $this->dnsConnector);

        $promise = $proxy->create('google.com', 80);

        $this->setExpectedException('RuntimeException', 'Method Not Allowed', 405);
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptConnectMethod()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('TLS not supported on really old platforms (HHVM < 3.8)');
        }

        $secure = new SecureConnector($this->dnsConnector, $this->loop);
        $proxy = new ProxyConnector('google.com:443', $secure);

        $promise = $proxy->create('google.com', 80);

        $this->setExpectedException('RuntimeException', 'Method Not Allowed', 405);
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptPlainStream()
    {
        $proxy = new ProxyConnector('google.com:443', $this->dnsConnector);

        $promise = $proxy->create('google.com', 80);

        $this->setExpectedException('RuntimeException', 'Connection to proxy lost');
        Block\await($promise, $this->loop, 3.0);
    }
}
