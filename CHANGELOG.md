# Changelog

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
