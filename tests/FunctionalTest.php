<?php

namespace Clue\Tests\React\HttpProxy;

use React\EventLoop\Factory;
use Clue\React\HttpProxy\ProxyConnector;
use React\Socket\TcpConnector;
use React\Socket\DnsConnector;
use Clue\React\Block;
use React\Socket\SecureConnector;

/** @group internet */
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

    public function testNonListeningSocketRejectsConnection()
    {
        $proxy = new ProxyConnector('127.0.0.1:9999', $this->dnsConnector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy failed (ECONNREFUSED)',
            SOCKET_ECONNREFUSED
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testPlainGoogleDoesNotAcceptConnectMethod()
    {
        $proxy = new ProxyConnector('google.com', $this->dnsConnector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy refused connection with HTTP error code 405 (Method Not Allowed) (ECONNREFUSED)',
            SOCKET_ECONNREFUSED
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptConnectMethod()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('TLS not supported on really old platforms (HHVM < 3.8)');
        }

        $secure = new SecureConnector($this->dnsConnector, $this->loop);
        $proxy = new ProxyConnector('https://google.com:443', $secure);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy refused connection with HTTP error code 405 (Method Not Allowed) (ECONNREFUSED)',
            SOCKET_ECONNREFUSED
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptPlainStream()
    {
        $proxy = new ProxyConnector('google.com:443', $this->dnsConnector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy was lost while waiting for response (ECONNRESET)',
            SOCKET_ECONNRESET
        );
        Block\await($promise, $this->loop, 3.0);
    }

    /**
     * @requires PHP 7
     */
    public function testCancelWhileConnectingShouldNotCreateGarbageCycles()
    {
        $proxy = new ProxyConnector('google.com', $this->dnsConnector);

        gc_collect_cycles();
        gc_collect_cycles(); // clear twice to avoid leftovers in PHP 7.4 with ext-xdebug and code coverage turned on

        $promise = $proxy->connect('google.com:80');
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function setExpectedException($exception, $message = '', $code = 0)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($exception);
            if ($message !== null) {
                $this->expectExceptionMessage($message);
            }
            $this->expectExceptionCode($code);
        } else {
            parent::setExpectedException($exception, $message, $code);
        }
    }
}
