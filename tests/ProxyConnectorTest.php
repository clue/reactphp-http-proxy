<?php

namespace Tests\Clue\React\HttpProxy;

use Clue\React\HttpProxy\ProxyConnector;
use React\Promise\Promise;
use React\Socket\ConnectionInterface;

class ProxyConnectorTest extends AbstractTestCase
{
    private $connector;

    public function setUp()
    {
        $this->connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidProxy()
    {
        new ProxyConnector('///', $this->connector);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidProxyScheme()
    {
        new ProxyConnector('ftp://example.com', $this->connector);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidHttpsUnixScheme()
    {
        new ProxyConnector('https+unix:///tmp/proxy.sock', $this->connector);
    }

    public function testCreatesConnectionToHttpPort()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://proxy.example.com:80?hostname=google.com')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testCreatesConnectionToHttpPortAndPassesThroughUriComponents()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://proxy.example.com:80/path?foo=bar&hostname=google.com#segment')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('google.com:80/path?foo=bar#segment');
    }

    public function testCreatesConnectionToHttpPortAndObeysExplicitHostname()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://proxy.example.com:80?hostname=www.google.com')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('google.com:80?hostname=www.google.com');
    }

    public function testCreatesConnectionToIpv4Address()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://proxy.example.com:80?hostname=127.0.0.1')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('127.0.0.1:80');
    }

    public function testCreatesConnectionToIpv6Address()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://proxy.example.com:80?hostname=%3A%3A1')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('[::1]:80');
    }

    public function testCreatesConnectionToIpv4AddressOverIpv6Proxy()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tcp://[::1]:80?hostname=127.0.0.1')->willReturn($promise);

        $proxy = new ProxyConnector('[::1]:80', $this->connector);

        $proxy->connect('127.0.0.1:80');
    }

    public function testCreatesConnectionToHttpsPort()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('tls://proxy.example.com:443?hostname=google.com')->willReturn($promise);

        $proxy = new ProxyConnector('https://proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testCreatesConnectionToUnixPath()
    {
        $promise = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->with('unix:///tmp/proxy.sock')->willReturn($promise);

        $proxy = new ProxyConnector('http+unix:///tmp/proxy.sock', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testCancelPromiseWillCancelPendingConnection()
    {
        $promise = new Promise(function () { }, $this->expectCallableOnce());
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $this->assertInstanceOf('React\Promise\CancellablePromiseInterface', $promise);

        $promise->cancel();
    }

    public function testWillWriteToOpenConnection()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testWillWriteIpv6HostToOpenConnection()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT [::1]:80 HTTP/1.1\r\nHost: [::1]:80\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $proxy->connect('[::1]:80');
    }

    public function testWillProxyAuthorizationHeaderIfProxyUriContainsAuthentication()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nProxy-Authorization: Basic dXNlcjpwYXNz\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('user:pass@proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testWillProxyAuthorizationHeaderIfProxyUriContainsOnlyUsernameWithoutPassword()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nProxy-Authorization: Basic dXNlcjo=\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('user@proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testWillProxyAuthorizationHeaderIfProxyUriContainsAuthenticationWithPercentEncoding()
    {
        $user = 'h@llÖ';
        $pass = '%secret?';

        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nProxy-Authorization: Basic " . base64_encode($user . ':' . $pass) . "\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector(rawurlencode($user) . ':' . rawurlencode($pass) . '@proxy.example.com', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testWillProxyAuthorizationHeaderIfUnixProxyUriContainsAuthentication()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nProxy-Authorization: Basic dXNlcjpwYXNz\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->with('unix:///tmp/proxy.sock')->willReturn($promise);

        $proxy = new ProxyConnector('http+unix://user:pass@/tmp/proxy.sock', $this->connector);

        $proxy->connect('google.com:80');
    }

    public function testRejectsInvalidUri()
    {
        $this->connector->expects($this->never())->method('connect');

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('///');

        $promise->then(null, $this->expectCallableOnce());
    }

    public function testRejectsUriWithNonTcpScheme()
    {
        $this->connector->expects($this->never())->method('connect');

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('tls://google.com:80');

        $promise->then(null, $this->expectCallableOnce());
    }

    public function testRejectsIfConnectorRejects()
    {
        $promise = \React\Promise\reject(new \RuntimeException());
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $promise->then(null, $this->expectCallableOnce());
    }

    public function testRejectsAndClosesIfStreamWritesNonHttp()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->expects($this->once())->method('close');
        $stream->emit('data', array("invalid\r\n\r\n"));

        $promise->then(null, $this->expectCallableOnceWithExceptionCode(SOCKET_EBADMSG));
    }

    public function testRejectsAndClosesIfStreamWritesTooMuchData()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->expects($this->once())->method('close');
        $stream->emit('data', array(str_repeat('*', 100000)));

        $promise->then(null, $this->expectCallableOnceWithExceptionCode(SOCKET_EMSGSIZE));
    }

    public function testRejectsAndClosesIfStreamReturnsProyAuthenticationRequired()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->expects($this->once())->method('close');
        $stream->emit('data', array("HTTP/1.1 407 Proxy Authentication Required\r\n\r\n"));

        $promise->then(null, $this->expectCallableOnceWithExceptionCode(SOCKET_EACCES));
    }

    public function testRejectsAndClosesIfStreamReturnsNonSuccess()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->expects($this->once())->method('close');
        $stream->emit('data', array("HTTP/1.1 403 Not allowed\r\n\r\n"));

        $promise->then(null, $this->expectCallableOnceWithExceptionCode(SOCKET_ECONNREFUSED));
    }

    public function testResolvesIfStreamReturnsSuccess()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $promise->then($this->expectCallableOnce('React\Stream\Stream'));
        $never = $this->expectCallableNever();
        $promise->then(function (ConnectionInterface $stream) use ($never) {
            $stream->on('data', $never);
        });

        $stream->emit('data', array("HTTP/1.1 200 OK\r\n\r\n"));
    }

    public function testResolvesIfStreamReturnsSuccessAndEmitsExcessiveData()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $once = $this->expectCallableOnceWith('hello!');
        $promise->then(function (ConnectionInterface $stream) use ($once) {
            $stream->on('data', $once);
        });

        $stream->emit('data', array("HTTP/1.1 200 OK\r\n\r\nhello!"));
    }

    public function testCancelPromiseWillCloseOpenConnectionAndReject()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('close');

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $this->assertInstanceOf('React\Promise\CancellablePromiseInterface', $promise);

        $promise->cancel();

        $promise->then(null, $this->expectCallableOnceWithExceptionCode(SOCKET_ECONNABORTED));
    }
}
