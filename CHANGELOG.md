# Changelog

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
