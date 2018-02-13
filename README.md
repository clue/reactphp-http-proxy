# clue/http-proxy-react [![Build Status](https://travis-ci.org/clue/php-http-proxy-react.svg?branch=master)](https://travis-ci.org/clue/php-http-proxy-react)

Async HTTP proxy connector, use any TCP/IP-based protocol through an HTTP
CONNECT proxy server, built on top of [ReactPHP](https://reactphp.org).

HTTP CONNECT proxy servers (also commonly known as "HTTPS proxy" or "SSL proxy")
are commonly used to tunnel HTTPS traffic through an intermediary ("proxy"), to
conceal the origin address (anonymity) or to circumvent address blocking
(geoblocking). While many (public) HTTP CONNECT proxy servers often limit this
to HTTPS port `443` only, this can technically be used to tunnel any
TCP/IP-based protocol (HTTP, SMTP, IMAP etc.).
This library provides a simple API to create these tunneled connection for you.
Because it implements ReactPHP's standard
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface),
it can simply be used in place of a normal connector.
This makes it fairly simple to add HTTP CONNECT proxy support to pretty much any
existing higher-level protocol implementation.

* **Async execution of connections** -
  Send any number of HTTP CONNECT requests in parallel and process their
  responses as soon as results come in.
  The Promise-based design provides a *sane* interface to working with out of
  bound responses and possible connection errors.
* **Standard interfaces** -
  Allows easy integration with existing higher-level components by implementing
  ReactPHP's standard
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface).
* **Lightweight, SOLID design** -
  Provides a thin abstraction that is [*just good enough*](http://en.wikipedia.org/wiki/Principle_of_good_enough)
  and does not get in your way.
  Builds on top of well-tested components and well-established concepts instead of reinventing the wheel.
* **Good test coverage** -
  Comes with an automated tests suite and is regularly tested against actual proxy servers in the wild

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [ProxyConnector](#proxyconnector)
    * [Plain TCP connections](#plain-tcp-connections)
    * [Secure TLS connections](#secure-tls-connections)
    * [Connection timeout](#connection-timeout)
    * [DNS resolution](#dns-resolution)
    * [Authentication](#authentication)
    * [Advanced secure proxy connections](#advanced-secure-proxy-connections)
    * [Advanced Unix domain sockets](#advanced-unix-domain-sockets)
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
servers use custom ports (often the alternative HTTP port `8080`).
In its most simple form, the given connector will be a
[`\React\Socket\Connector`](https://github.com/reactphp/socket#connector) if you
want to connect to a given IP address as above.

This is the main class in this package.
Because it implements ReactPHP's standard
[`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface),
it can simply be used in place of a normal connector.
Accordingly, it provides only a single public method, the
[`connect()`](https://github.com/reactphp/socket#connect) method.
The `connect(string $uri): PromiseInterface<ConnectionInterface, Exception>`
method can be used to establish a streaming connection.
It returns a [Promise](https://github.com/reactphp/promise) which either
fulfills with a [ConnectionInterface](https://github.com/reactphp/socket#connectioninterface)
on success or rejects with an `Exception` on error.

This makes it fairly simple to add HTTP CONNECT proxy support to pretty much any
higher-level component:

```diff
- $client = new SomeClient($connector);
+ $proxy = new ProxyConnector('http://127.0.0.1:8080', $connector);
+ $client = new SomeClient($proxy);
```

#### Plain TCP connections

HTTP CONNECT proxies are most frequently used to issue HTTPS requests to your destination.
However, this is actually performed on a higher protocol layer and this
connector is actually inherently a general-purpose plain TCP/IP connector.
As documented above, you can simply invoke its `connect()` method to establish
a streaming plain TCP/IP connection and use any higher level protocol like so:

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
in ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector):

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

This class can also be used if you want to establish a secure TLS connection
(formerly known as SSL) between you and your destination, such as when using
secure HTTPS to your destination site. You can simply wrap this connector in
ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector) or the
low-level [`SecureConnector`](https://github.com/reactphp/socket#secureconnector):

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

> Note how secure TLS connections are in fact entirely handled outside of
  this HTTP CONNECT client implementation.

#### Connection timeout

By default, the `ProxyConnector` does not implement any timeouts for establishing remote
connections.
Your underlying operating system may impose limits on pending and/or idle TCP/IP
connections, anywhere in a range of a few minutes to several hours.

Many use cases require more control over the timeout and likely values much
smaller, usually in the range of a few seconds only.

You can use ReactPHP's [`Connector`](https://github.com/reactphp/socket#connector)
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

> Note how connection timeout is in fact entirely handled outside of this
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
However, wrapping the `ProxyConnector` in ReactPHP's
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

> Note how local DNS resolution is in fact entirely handled outside of this
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
`https://` scheme (HTTPS default port 443) and use ReactPHP's
[`Connector`](https://github.com/reactphp/socket#connector) or the low-level
[`SecureConnector`](https://github.com/reactphp/socket#secureconnector)
instance to create a secure connection to the proxy:

```php
$connector = new Connector($loop);
$proxy = new ProxyConnector('https://127.0.0.1:443', $connector);

$proxy->connect('tcp://smtp.googlemail.com:587');
```

#### Advanced Unix domain sockets

HTTP CONNECT proxy servers support forwarding TCP/IP based connections and
higher level protocols.
In some advanced cases, it may be useful to let your HTTP CONNECT proxy server
listen on a Unix domain socket (UDS) path instead of a IP:port combination.
For example, this allows you to rely on file system permissions instead of
having to rely on explicit [authentication](#authentication).

You can simply use the `http+unix://` URI scheme like this:

```php
$proxy = new ProxyConnector('http+unix:///tmp/proxy.sock', $connector);

$proxy->connect('tcp://google.com:80')->then(function (ConnectionInterface $stream) {
    // connected…
});
```

Similarly, you can also combine this with [authentication](#authentication)
like this:

```php
$proxy = new ProxyConnector('http+unix://user:pass@/tmp/proxy.sock', $connector);
```

> Note that Unix domain sockets (UDS) are considered advanced usage and PHP only
  has limited support for this.
  In particular, enabling [secure TLS](#secure-tls-connections) may not be
  supported.

> Note that the HTTP CONNECT protocol does not support the notion of UDS paths.
  The above works reasonably well because UDS is only used for the connection between
  client and proxy server and the path will not actually passed over the protocol.
  This implies that this does not support connecting to UDS destination paths.

## Install

The recommended way to install this library is [through Composer](https://getcomposer.org).
[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This project follows [SemVer](http://semver.org/).
This will install the latest supported version:

```bash
$ composer require clue/http-proxy-react:^1.3
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

This project aims to run on any platform and thus does not require any PHP
extensions and supports running on legacy PHP 5.3 through current PHP 7+ and
HHVM.
It's *highly recommended to use PHP 7+* for this project.

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

* If you want to learn more about how the
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
  and its usual implementations look like, refer to the documentation of the underlying
  [react/socket](https://github.com/reactphp/socket) component.
* If you want to learn more about processing streams of data, refer to the
  documentation of the underlying
  [react/stream](https://github.com/reactphp/stream) component.
* As an alternative to an HTTP CONNECT proxy, you may also want to look into
  using a SOCKS (SOCKS4/SOCKS5) proxy instead.
  You may want to use [clue/socks-react](https://github.com/clue/php-socks-react)
  which also provides an implementation of the same
  [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface)
  so that supporting either proxy protocol should be fairly trivial.
* If you're dealing with public proxies, you'll likely have to work with mixed
  quality and unreliable proxies. You may want to look into using
  [clue/connection-manager-extra](https://github.com/clue/php-connection-manager-extra)
  which allows retrying unreliable ones, implying connection timeouts,
  concurrently working with multiple connectors and more.
* If you're looking for an end-user HTTP CONNECT proxy server daemon, you may
  want to use [LeProxy](https://leproxy.org/).
