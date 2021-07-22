<?php

namespace Clue\Tests\React\HttpProxy;

use Clue\React\Block;
use Clue\React\HttpProxy\ProxyConnector;
use React\EventLoop\Factory;
use React\Socket\Connector;

/** @group internet */
class FunctionalTest extends AbstractTestCase
{
    private $loop;
    private $connector;

    /**
     * @before
     */
    public function setUpConnector()
    {
        $this->loop = Factory::create();
        $this->connector = new Connector($this->loop);
    }

    public function testNonListeningSocketRejectsConnection()
    {
        $proxy = new ProxyConnector('127.0.0.1:9999', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy failed (ECONNREFUSED)',
            defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testPlainGoogleDoesNotAcceptConnectMethod()
    {
        $proxy = new ProxyConnector('google.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy refused connection with HTTP error code 405 (Method Not Allowed) (ECONNREFUSED)',
            defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptConnectMethod()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('TLS not supported on legacy HHVM');
        }

        $proxy = new ProxyConnector('https://google.com:443', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy refused connection with HTTP error code 405 (Method Not Allowed) (ECONNREFUSED)',
            defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111
        );
        Block\await($promise, $this->loop, 3.0);
    }

    public function testSecureGoogleDoesNotAcceptPlainStream()
    {
        $proxy = new ProxyConnector('google.com:443');

        $promise = $proxy->connect('google.com:80');

        $this->setExpectedException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy was lost while waiting for response (ECONNRESET)',
            defined('SOCKET_ECONNRESET') ? SOCKET_ECONNRESET : 104
        );
        Block\await($promise, $this->loop, 3.0);
    }

    /**
     * @requires PHP 7
     */
    public function testCancelWhileConnectingShouldNotCreateGarbageCycles()
    {
        $proxy = new ProxyConnector('google.com', $this->connector);

        gc_collect_cycles();
        gc_collect_cycles(); // clear twice to avoid leftovers in PHP 7.4 with ext-xdebug and code coverage turned on

        $promise = $proxy->connect('google.com:80');
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
