<?php

namespace Clue\Tests\React\HttpProxy;

use Clue\React\HttpProxy\ProxyConnector;
use React\Socket\ConnectionInterface;
use React\Promise\Deferred;
use React\Promise\Promise;

class ProxyConnectorTest extends AbstractTestCase
{
    private $connector;

    /**
     * @before
     */
    public function setUpMock()
    {
        $this->connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
    }

    public function testConstructWithoutConnectorAssignsConnectorAutomatically()
    {
        $proxy = new ProxyConnector('proxy.example.com');

        $ref = new \ReflectionProperty($proxy, 'connector');
        $ref->setAccessible(true);
        $connector = $ref->getValue($proxy);

        $this->assertInstanceOf('React\Socket\ConnectorInterface', $connector);
    }

    public function testInvalidProxy()
    {
        $this->setExpectedException('InvalidArgumentException');
        new ProxyConnector('///', $this->connector);
    }

    public function testInvalidProxyScheme()
    {
        $this->setExpectedException('InvalidArgumentException');
        new ProxyConnector('ftp://example.com', $this->connector);
    }

    public function testInvalidHttpsUnixScheme()
    {
        $this->setExpectedException('InvalidArgumentException');
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

    public function testWillSendCustomHttpHeadersToProxy()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nX-Custom-Header: X-Custom-Value\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector, array(
            'X-Custom-Header' => 'X-Custom-Value'
        ));

        $proxy->connect('google.com:80');
    }

    public function testWillSendMultipleCustomCookieHeadersToProxy()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nCookie: id=123\r\nCookie: year=2018\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector, array(
            'Cookie' => array(
                'id=123',
                'year=2018'
            )
        ));

        $proxy->connect('google.com:80');
    }

    public function testWillAppendCustomProxyAuthorizationHeaderWithCredentialsFromUri()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('write')->with("CONNECT google.com:80 HTTP/1.1\r\nHost: google.com:80\r\nProxy-Authorization: Basic dXNlcjpwYXNz\r\nProxy-Authorization: foobar\r\n\r\n");

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('user:pass@proxy.example.com', $this->connector, array(
            'Proxy-Authorization' => 'foobar'
        ));

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

    public function testRejectsWithPreviousIfConnectorRejects()
    {
        $promise = \React\Promise\reject($previous = new \RuntimeException());
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy failed (ECONNREFUSED)',
            defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111
        ));

        $promise->then(null, $this->expectCallableOnceWith($this->callback(function (\Exception $e) use ($previous) {
            return $e->getPrevious() === $previous;
        })));
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

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy returned invalid response (EBADMSG)',
            defined('SOCKET_EBADMSG') ? SOCKET_EBADMSG: 71
        ));
    }

    public function testRejectsAndClosesIfStreamWritesTooMuchData()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->expects($this->once())->method('close');
        $stream->emit('data', array(str_repeat('*', 10000)));

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy response headers exceed maximum of 8 KiB (EMSGSIZE)',
            defined('SOCKET_EMSGSIZE') ? SOCKET_EMSGSIZE : 90
        ));
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

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy denied access with HTTP error code 407 (Proxy Authentication Required) (EACCES)',
            defined('SOCKET_EACCES') ? SOCKET_EACCES : 13
        ));
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

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because proxy refused connection with HTTP error code 403 (Not allowed) (ECONNREFUSED)',
            defined('SOCKET_ECONNREFUSED') ? SOCKET_ECONNREFUSED : 111
        ));
    }

    public function testRejectsWithPreviousExceptionIfStreamEmitsError()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $promise = \React\Promise\resolve($stream);
        $this->connector->expects($this->once())->method('connect')->willReturn($promise);

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $stream->emit('error', array($previous = new \RuntimeException()));

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 failed because connection to proxy caused a stream error (EIO)',
            defined('SOCKET_EIO') ? SOCKET_EIO : 5
        ));

        $promise->then(null, $this->expectCallableOnceWith($this->callback(function (\Exception $e) use ($previous) {
            return $e->getPrevious() === $previous;
        })));
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

    public function testCancelPromiseWhileConnectionIsReadyWillCloseOpenConnectionAndReject()
    {
        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();
        $stream->expects($this->once())->method('close');

        $deferred = new Deferred();

        $this->connector->expects($this->once())->method('connect')->willReturn($deferred->promise());

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');

        $deferred->resolve($stream);

        $this->assertInstanceOf('React\Promise\CancellablePromiseInterface', $promise);

        $promise->cancel();

        $promise->then(null, $this->expectCallableOnceWithException(
            'RuntimeException',
            'Connection to tcp://google.com:80 cancelled while waiting for proxy (ECONNABORTED)',
            defined('SOCKET_ECONNABORTED') ? SOCKET_ECONNABORTED : 103
        ));
    }

    public function testCancelPromiseDuringConnectionShouldNotCreateGarbageCycles()
    {
        $pending = new Promise(function () { });
        $this->connector->expects($this->once())->method('connect')->willReturn($pending);

        gc_collect_cycles();

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testCancelPromiseWhileConnectionIsReadyShouldNotCreateGarbageCycles()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        $stream = $this->getMockBuilder('React\Socket\Connection')->disableOriginalConstructor()->setMethods(array('close', 'write'))->getMock();

        $deferred = new Deferred();

        $this->connector->expects($this->once())->method('connect')->willReturn($deferred->promise());

        gc_collect_cycles();

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');
        $deferred->resolve($stream);
        $promise->cancel();
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }

    public function testRejectedConnectionShouldNotCreateGarbageCycles()
    {
        if (class_exists('React\Promise\When')) {
            $this->markTestSkipped('Not supported on legacy Promise v1 API');
        }

        $rejected = \React\Promise\reject(new \RuntimeException());
        $this->connector->expects($this->once())->method('connect')->willReturn($rejected);

        gc_collect_cycles();

        $proxy = new ProxyConnector('proxy.example.com', $this->connector);

        $promise = $proxy->connect('google.com:80');
        unset($promise);

        $this->assertEquals(0, gc_collect_cycles());
    }
}
