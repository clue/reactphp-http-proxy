# Changelog

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
