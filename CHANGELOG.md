# Changelog

## 1.7.0 (2021-08-06)

*   Feature: Simplify usage by supporting new default loop and making `Connector` optional.
    (#41 and #42 by @clue)

    ```php
    // old (still supported)
    $proxy = new Clue\React\HttpProxy\ProxyConnector(
        '127.0.0.1:8080',
        new React\Socket\Connector($loop)
    );

    // new (using default loop)
    $proxy = new Clue\React\HttpProxy\ProxyConnector('127.0.0.1:8080');
    ```

*   Documentation improvements and updated examples.
    (#39 and #43 by @clue and #40 by @PaulRotmann)

*   Improve test suite and use GitHub actions for continuous integration (CI).
    (#38 by @SimonFrings)

## 1.6.0 (2020-10-23)

*   Enhanced documentation for ReactPHP's new HTTP client.
    (#35 and #37 by @SimonFrings)

*   Improve test suite, prepare PHP 8 support and support PHPUnit 9.3.
    (#36 by @SimonFrings)

## 1.5.0 (2020-06-19)

*   Feature / Fix: Support PHP 7.4 by skipping unneeded cleanup of exception trace args.
    (#33 by @clue)

*   Clean up test suite and add `.gitattributes` to exclude dev files from exports.
    Run tests on PHP 7.4, PHPUnit 9 and simplify test matrix.
    Link to using SSH proxy (SSH tunnel) as an alternative.
    (#27 by @clue and #31, #32 and #34 by @SimonFrings)

## 1.4.0 (2018-10-30)

*   Feature: Improve error reporting for failed connection attempts and improve
    cancellation forwarding during proxy connection setup.
    (#23 and #26 by @clue)

    All error messages now always contain a reference to the remote URI to give
    more details which connection actually failed and the reason for this error.
    Similarly, any underlying connection issues to the proxy server will now be
    reported as part of the previous exception.

    For most common use cases this means that simply reporting the `Exception`
    message should give the most relevant details for any connection issues:

    ```php
    $promise = $proxy->connect('tcp://example.com:80');
    $promise->then(function (ConnectionInterface $connection) {
        // …
    }, function (Exception $e) {
        echo $e->getMessage();
    });
    ```

*   Feature: Add support for custom HTTP request headers.
    (#25 by @valga and @clue)

    ```php
    // new: now supports custom HTTP request headers
    $proxy = new ProxyConnector('127.0.0.1:8080', $connector, array(
        'Proxy-Authorization' => 'Bearer abc123',
        'User-Agent' => 'ReactPHP'
    ));
    ```

*   Fix: Fix connecting to IPv6 destination hosts.
    (#22 by @clue)

*   Link to clue/reactphp-buzz for HTTP requests and update project homepage.
    (#21 and #24 by @clue)

## 1.3.0 (2018-02-13)

*   Feature: Support communication over Unix domain sockets (UDS)
    (#20 by @clue)

    ```php
    // new: now supports communication over Unix domain sockets (UDS)
    $proxy = new ProxyConnector('http+unix:///tmp/proxy.sock', $connector);
    ```

*   Reduce memory consumption by avoiding circular reference from stream reader
    (#18 by @valga)

*   Improve documentation
    (#19 by @clue)

## 1.2.0 (2017-08-30)

*   Feature: Use socket error codes for connection rejections
    (#17 by @clue)

    ```php
    $promise = $proxy->connect('imap.example.com:143');
    $promise->then(null, function (Exeption $e) {
        if ($e->getCode() === SOCKET_EACCES) {
            echo 'Failed to authenticate with proxy!';
        }
        throw $e;
    });
    ```

*   Improve test suite by locking Travis distro so new defaults will not break the build and
    optionally exclude tests that rely on working internet connection
    (#15 and #16 by @clue)

## 1.1.0 (2017-06-11)

* Feature: Support proxy authentication if proxy URL contains username/password
  (#14 by @clue)

  ```php
  // new: username/password will now be passed to HTTP proxy server
  $proxy = new ProxyConnector('user:pass@127.0.0.1:8080', $connector);
  ```

## 1.0.0 (2017-06-10)

* First stable release, now following SemVer

> Contains no other changes, so it's actually fully compatible with the v0.3.2 release.

## 0.3.2 (2017-06-10)

* Fix: Fix rejecting invalid URIs and unexpected URI schemes
  (#13 by @clue)

* Fix HHVM build for now again and ignore future HHVM build errors
  (#12 by @clue)

* Documentation for Connector concepts (TCP/TLS, timeouts, DNS resolution)
  (#11 by @clue)

## 0.3.1 (2017-05-10)

* Feature: Forward compatibility with upcoming Socket v1.0 and v0.8
  (#10 by @clue)

## 0.3.0 (2017-04-10)

* Feature / BC break: Replace deprecated SocketClient with new Socket component
  (#9 by @clue)

  This implies that the `ProxyConnector` from this package now implements the
  `React\Socket\ConnectorInterface` instead of the legacy
  `React\SocketClient\ConnectorInterface`.

## 0.2.0 (2017-04-10)

* Feature / BC break: Update SocketClient to v0.7 or v0.6 and
  use `connect($uri)` instead of `create($host, $port)`
  (#8 by @clue)

  ```php
  // old
  $connector->create($host, $port)->then(function (Stream $conn) {
      $conn->write("…");
  });

  // new
  $connector->connect($uri)->then(function (ConnectionInterface $conn) {
      $conn->write("…");
  });
  ```

* Improve test suite by adding PHPUnit to require-dev
  (#7 by @clue)


## 0.1.0 (2016-11-01)

* First tagged release
