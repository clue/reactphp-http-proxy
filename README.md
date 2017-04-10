# clue/http-proxy-react [![Build Status](https://travis-ci.org/clue/php-http-proxy-react.svg?branch=master)](https://travis-ci.org/clue/php-http-proxy-react)

Async HTTP CONNECT proxy connector, use any TCP/IP protocol through an HTTP proxy server, built on top of React PHP.

**Table of contents**

* [Quickstart example](#quickstart-example)
* [Usage](#usage)
  * [ConnectorInterface](#connectorinterface)
    * [connect()](#connect)
  * [ProxyConnector](#proxyconnector)
* [Install](#install)
* [Tests](#tests)
* [License](#license)
* [More](#more)

### Quickstart example

The following example code demonstrates how this library can be used to send a
secure HTTPS request to google.com through a local HTTP proxy server:

```php
$loop = React\EventLoop\Factory::create();
$connector = new TcpConnector($loop);
$proxy = new ProxyConnector('127.0.0.1:8080', $connector);
$ssl = new SecureConnector($proxy, $loop);

$ssl->connect('google.com:443')->then(function (ConnectionInterface $stream) {
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
$connector = new TcpConnector($loop);
$proxy = new ProxyConnector('127.0.0.1:8080', $connector);
```

The proxy URL may or may not contain a scheme and port definition. The default
port will be `80` for HTTP (or `443` for HTTPS), but many common HTTP proxy
servers use custom ports.
In its most simple form, the given connector will be a
[`TcpConnector`](https://github.com/reactphp/socket#tcpconnector) if you
want to connect to a given IP address as above.

This is the main class in this package.
Because it implements the the [`ConnectorInterface`](#connectorinterface), it
can simply be used in place of a normal connector.
This makes it fairly simple to add HTTP CONNECT proxy support to pretty much any
higher-level component:

```diff
- $client = new SomeClient($connector);
+ $proxy = new ProxyConnector('127.0.0.1:8080', $connector);
+ $client = new SomeClient($proxy);
```

This is most frequently used to issue HTTPS requests to your destination.
However, this is actually performed on a higher protocol layer and this
connector is actually inherently a general-purpose plain TCP/IP connector:

```php
$proxy = new ProxyConnector('127.0.0.1:8080', $connector);

$proxy->connect('smtp.googlemail.com:587')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
    });
});
```

Note that HTTP CONNECT proxies often restrict which ports one may connect to.
Many (public) proxy servers do in fact limit this to HTTPS (443) only.

If you want to establish a TLS connection (such as HTTPS) between you and
your destination, you may want to wrap this connector in a
[`SecureConnector`](https://github.com/reactphp/socket#secureconnector)
instance:

```php
$proxy = new ProxyConnector('127.0.0.1:8080', $connector);
$ssl = new SecureConnector($proxy, $loop);

$ssl->connect('smtp.googlemail.com:465')->then(function (ConnectionInterface $stream) {
    $stream->write("EHLO local\r\n");
    $stream->on('data', function ($chunk) use ($stream) {
        echo $chunk;
    });
});
```

Note that communication between the client and the proxy is usually via an
unencrypted, plain TCP/IP HTTP connection. Note that this is the most common
setup, because you can still establish a TLS connection between you and the
destination host as above.

If you want to connect to a (rather rare) HTTPS proxy, you may want use its
HTTPS port (443) and use a
[`SecureConnector`](https://github.com/reactphp/socket#secureconnector)
instance to create a secure connection to the proxy:

```php
$ssl = new SecureConnector($connector, $loop);
$proxy = new ProxyConnector('127.0.0.1:443', $ssl);

$proxy->connect('smtp.googlemail.com:587');
```

## Install

The recommended way to install this library is [through Composer](http://getcomposer.org).
[New to Composer?](http://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require clue/http-proxy-react:^0.1
```

See also the [CHANGELOG](CHANGELOG.md) for details about version upgrades.

## Tests

To run the test suite, you first need to clone this repo and then install all
dependencies [through Composer](http://getcomposer.org):

```bash
$ composer install
```

To run the test suite, go to the project root and run:

```bash
$ php vendor/bin/phpunit
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
