# clue/http-proxy-react [![Build Status](https://travis-ci.org/clue/php-http-proxy-react.svg?branch=master)](https://travis-ci.org/clue/php-http-proxy-react)

Async HTTP CONNECT proxy connector, use any TCP/IP protocol through an HTTP proxy server, built on top of [ReactPHP](http://reactphp.org).

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [ConnectorInterface](#connectorinterface)
    * [connect()](#connect)
  * [ProxyConnector](#proxyconnector)
    * [Plain TCP connections](#plain-tcp-connections)
    * [Secure TLS connections](#secure-tls-connections)
    * [Connection timeout](#connection-timeout)
    * [DNS resolution](#dns-resolution)
    * [Authentication](#authentication)
    * [Advanced secure proxy connections](#advanced-secure-proxy-connections)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

### Quickstart example

The following example code demonstrates how this library can be used to send a
secure HTTPS request to google.com through a local HTTP proxy server:

```php
$loop = React\EventLoop\Factory::create();

$proxy = new ProxyConnector('127.0.0.1:8080', new Connector($loop));
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'timeout' => 3.0,
    'dns' => false
));

$connector->connect('tls://google.com:443')->then(function (ConnectionInterface $stream) {
    $stream->write("GET / HTTP/1.1\r\nHost: google.com\r\nConnection: close\r\n\r\n");
    $stream->on('data', function ($chunk) {
        echo $chunk;
    });
}, 'printf');

$loop->run();
```

See also the [examples](examples).

## Usage

### ConnectorInterface

The `ConnectorInterface` is responsible for providing an interface for
establishing streaming connections, such as a normal TCP/IP connection.

In order to use this library, you should understand how this integrates with its
ecosystem.
This base interface is actually defined in React's
[Socket component](https://github.com/reactphp/socket) and used
throughout React's ecosystem.

Most higher-level components (such as HTTP, database or other networking
service clients) accept an instance implementing this interface to create their
TCP/IP connection to the underlying networking service.
This is usually done via dependency injection, so it's fairly simple to actually
swap this implementation against this library in order to connect through an
HTTP CONNECT proxy.

The interface only offers a single method:

#### connect()

The `connect(string $uri): PromiseInterface<ConnectionInterface, Exception>` method
can be used to establish a streaming connection.
It returns a [Promise](https://github.com/reactphp/promise) which either
fulfills with a [ConnectionInterface](https://github.com/reactphp/socket#connectioninterface) or
rejects with an `Exception`:

```php
$connector->connect('google.com:443')->then(
    function (ConnectionInterface $stream) {
        // connection successfully established
    },
    function (Exception $error) {
        // failed to connect due to $error
    }
);
```

### ProxyConnector

The `ProxyConnector` is responsible for creating plain TCP/IP connections to
any destination by using an intermediary HTTP CONNECT proxy.

```
[you] -> [proxy] -> [destination]
```

Its constructor simply accepts an HTTP proxy URL and a connector used to connect
to the proxy server address:

```php
$connector = new Connector($loop);
$proxy = new ProxyConnector('http://127.0.0.1:8080', $connector);
```

The proxy URL may or may not contain a scheme and port definition. The default
port will be `80` for HTTP (or `443` for HTTPS), but many common HTTP proxy
servers use custom ports.
In its most simple form, the given connector will be a
[`\React\Socket\Connector`](https://github.com/reactphp/socket#connector) if you
want to connect to a given IP address as above.

This is the main class in this package.
Because it implements the the [`ConnectorInterface`](#connectorinterface), it
can simply be used in place of a normal connector.
This makes it fairly simple to add HTTP CONNECT proxy support to pretty much any
higher-level component:

```diff
- $client = new SomeClient($connector);
+ $proxy = new ProxyConnector('http://127.0.0.1:8080', $connector);
+ $client = new SomeClient($proxy);
```

#### Plain TCP connections

This is most frequently used to issue HTTPS requests to your destination.
However, this is actually performed on a higher protocol layer and this
connector is actually inherently a general-purpose plain TCP/IP connector.

The `ProxyConnector` implements the [`ConnectorInterface`](#connectorinterface) and
hence provides a single public method, the [`connect()`](#connect) method.

```php
$proxy = new ProxyConnector('http://127.0.0.1:8080', $connector);

$proxy->connect('tcp://smtp.googlemail.com:587')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
    });
});
```

You can either use the `ProxyConnector` directly or you may want to wrap this connector
in React's [`Connector`](https://github.com/reactphp/socket#connector):

```php
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$connector->connect('tcp://smtp.googlemail.com:587')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
    });
});
```

Note that HTTP CONNECT proxies often restrict which ports one may connect to.
Many (public) proxy servers do in fact limit this to HTTPS (443) only.

#### Secure TLS connections

If you want to establish a TLS connection (such as HTTPS) between you and
your destination, you may want to wrap this connector in React's
[`Connector`](https://github.com/reactphp/socket#connector) or the low-level
[`SecureConnector`](https://github.com/reactphp/socket#secureconnector):

```php
$proxy = new ProxyConnector('http://127.0.0.1:8080', $connector);
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));

$connector->connect('tls://smtp.googlemail.com:465')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
    });
});
```

> Also note how secure TLS connections are in fact entirely handled outside of
  this HTTP CONNECT client implementation.

#### Connection timeout

By default, the `ProxyConnector` does not implement any timeouts for establishing remote
connections.
Your underlying operating system may impose limits on pending and/or idle TCP/IP
connections, anywhere in a range of a few minutes to several hours.

Many use cases require more control over the timeout and likely values much
smaller, usually in the range of a few seconds only.

You can use React's [`Connector`](https://github.com/reactphp/socket#connector)
or the low-level
[`TimeoutConnector`](https://github.com/reactphp/socket#timeoutconnector)
to decorate any given `ConnectorInterface` instance.
It provides the same `connect()` method, but will automatically reject the
underlying connection attempt if it takes too long:

```php
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false,
    'timeout' => 3.0
));

$connector->connect('tcp://google.com:80')->then(function ($stream) {
    // connection succeeded within 3.0 seconds
});
```

See also any of the [examples](examples).

> Also note how connection timeout is in fact entirely handled outside of this
  HTTP CONNECT client implementation.

#### DNS resolution

By default, the `ProxyConnector` does not perform any DNS resolution at all and simply
forwards any hostname you're trying to connect to the remote proxy server.
The remote proxy server is thus responsible for looking up any hostnames via DNS
(this default mode is thus called *remote DNS resolution*).

As an alternative, you can also send the destination IP to the remote proxy
server.
In this mode you either have to stick to using IPs only (which is ofen unfeasable)
or perform any DNS lookups locally and only transmit the resolved destination IPs
(this mode is thus called *local DNS resolution*).

The default *remote DNS resolution* is useful if your local `ProxyConnector` either can
not resolve target hostnames because it has no direct access to the internet or
if it should not resolve target hostnames because its outgoing DNS traffic might
be intercepted.

As noted above, the `ProxyConnector` defaults to using remote DNS resolution.
However, wrapping the `ProxyConnector` in React's
[`Connector`](https://github.com/reactphp/socket#connector) actually
performs local DNS resolution unless explicitly defined otherwise.
Given that remote DNS resolution is assumed to be the preferred mode, all
other examples explicitly disable DNS resoltion like this:

```php
$connector = new Connector($loop, array(
    'tcp' => $proxy,
    'dns' => false
));
```

If you want to explicitly use *local DNS resolution*, you can use the following code:

```php
// set up Connector which uses Google's public DNS (8.8.8.8)
$connector = Connector($loop, array(
    'tcp' => $proxy,
    'dns' => '8.8.8.8'
));
```

> Also note how local DNS resolution is in fact entirely handled outside of this
  HTTP CONNECT client implementation.

#### Authentication

If your HTTP proxy server requires authentication, you may pass the username and
password as part of the HTTP proxy URL like this:

```php
$proxy = new ProxyConnector('http://user:pass@127.0.0.1:8080', $connector);
```

Note that both the username and password must be percent-encoded if they contain
special characters:

```php
$user = 'he:llo';
$pass = 'p@ss';

$proxy = new ProxyConnector(
    rawurlencode($user) . ':' . rawurlencode($pass) . '@127.0.0.1:8080',
    $connector
);
```

> The authentication details will be used for basic authentication and will be
  transferred in the `Proxy-Authorization` HTTP request header for each
  connection attempt.
  If the authentication details are missing or not accepted by the remote HTTP
  proxy server, it is expected to reject each connection attempt with a
  `407` (Proxy Authentication Required) response status code and an exception
  error code of `SOCKET_EACCES` (13).

#### Advanced secure proxy connections

Note that communication between the client and the proxy is usually via an
unencrypted, plain TCP/IP HTTP connection. Note that this is the most common
setup, because you can still establish a TLS connection between you and the
destination host as above.

If you want to connect to a (rather rare) HTTPS proxy, you may want use the
`https://` scheme (HTTPS default port 443) and use React's
[`Connector`](https://github.com/reactphp/socket#connector) or the low-level
[`SecureConnector`](https://github.com/reactphp/socket#secureconnector)
instance to create a secure connection to the proxy:

```php
$connector = new Connector($loop);
$proxy = new ProxyConnector('https://127.0.0.1:443', $connector);

$proxy->connect('tcp://smtp.googlemail.com:587');
```

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/http-proxy-react:^1.2
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](https://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
```

The test suite contains tests that rely on a working internet connection,
alternatively you can also run it like this:

```bash
$ php vendor/bin/phpunit --exclude-group internet
```

## License

MIT

## More

* If you want to learn more about processing streams of data, refer to the
  documentation of the underlying
  [react/stream](https://github.com/reactphp/stream) component.
* If you want to learn more about how the
  [`ConnectorInterface`](#connectorinterface) and its usual implementations look
  like, refer to the documentation of the underlying
  [react/socket](https://github.com/reactphp/socket) component.
* As an alternative to an HTTP CONNECT proxy, you may also want to look into
  using a SOCKS (SOCKS4/SOCKS5) proxy instead.
  You may want to use [clue/socks-react](https://github.com/clue/php-socks-react)
  which also provides an implementation of the
  [`ConnectorInterface`](#connectorinterface) so that supporting either proxy
  protocol should be fairly trivial.
* If you're dealing with public proxies, you'll likely have to work with mixed
  quality and unreliable proxies. You may want to look into using
  [clue/connection-manager-extra](https://github.com/clue/php-connection-manager-extra)
  which allows retrying unreliable ones, implying connection timeouts,
  concurrently working with multiple connectors and more.
