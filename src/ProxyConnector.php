<?php

namespace Clue\React\HttpProxy;

use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use RingCentral\Psr7;
use React\Promise\Deferred;

/**
 * A simple Connector that uses an HTTP CONNECT proxy to create plain TCP/IP connections to any destination
 *
 * [you] -> [proxy] -> [destination]
 *
 * This is most frequently used to issue HTTPS requests to your destination.
 * However, this is actually performed on a higher protocol layer and this
 * connector is actually inherently a general-purpose plain TCP/IP connector.
 *
 * Note that HTTP CONNECT proxies often restrict which ports one may connect to.
 * Many (public) proxy servers do in fact limit this to HTTPS (443) only.
 *
 * If you want to establish a TLS connection (such as HTTPS) between you and
 * your destination, you may want to wrap this connector in a SecureConnector
 * instance.
 *
 * Note that communication between the client and the proxy is usually via an
 * unencrypted, plain TCP/IP HTTP connection. Note that this is the most common
 * setup, because you can still establish a TLS connection between you and the
 * destination host as above.
 *
 * If you want to connect to a (rather rare) HTTPS proxy, you may want use its
 * HTTPS port (443) and use a SecureConnector instance to create a secure
 * connection to the proxy.
 *
 * @link https://tools.ietf.org/html/rfc7231#section-4.3.6
 */
class ProxyConnector implements ConnectorInterface
{
    private $connector;
    private $proxyHost;
    private $proxyPort;

    /**
     * Instantiate a new ProxyConnector which uses the given $proxyUrl
     *
     * @param string $proxyUrl The proxy URL may or may not contain a scheme and
     *     port definition. The default port will be `80` for HTTP (or `443` for
     *     HTTPS), but many common HTTP proxy servers use custom ports.
     * @param ConnectorInterface $connector In its most simple form, the given
     *     connector will be a TcpConnector if you want to connect to a given IP
     *     address.
     * @throws InvalidArgumentException if the proxy URL is invalid
     */
    public function __construct($proxyUrl, ConnectorInterface $connector)
    {
        if (strpos($proxyUrl, '://') === false) {
            $proxyUrl = 'http://' . $proxyUrl;
        }

        $parts = parse_url($proxyUrl);
        if (!$parts || !isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid proxy URL');
        }

        if (!isset($parts['port'])) {
            $parts['port'] = $parts['scheme'] === 'https' ? 443 : 80;
        }

        $this->connector = $connector;
        $this->proxyHost = $parts['host'];
        $this->proxyPort = $parts['port'];
    }

    public function create($host, $port)
    {
        return $this->connector->create($this->proxyHost, $this->proxyPort)->then(function (Stream $stream) use ($host, $port) {
            $deferred = new Deferred(function ($_, $reject) use ($stream) {
                $reject(new RuntimeException('Operation canceled while waiting for response from proxy'));
                $stream->close();
            });

            // keep buffering data until headers are complete
            $buffer = '';
            $fn = function ($chunk) use (&$buffer, &$fn, $deferred, $stream) {
                $buffer .= $chunk;

                $pos = strpos($buffer, "\r\n\r\n");
                if ($pos !== false) {
                    // end of headers received => stop buffering
                    $stream->removeListener('data', $fn);

                    // try to parse headers as response message
                    try {
                        $response = Psr7\parse_response(substr($buffer, 0, $pos));
                    } catch (Exception $e) {
                        $deferred->reject(new RuntimeException('Invalid response received from proxy: ' . $e->getMessage(), 0, $e));
                        $stream->close();
                        return;
                    }

                    // status must be 2xx
                    if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                        $deferred->reject(new RuntimeException('Proxy rejected with HTTP error code: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase(), $response->getStatusCode()));
                        $stream->close();
                        return;
                    }

                    // all okay, resolve with stream instance
                    $deferred->resolve($stream);

                    // emit remaining incoming as data event
                    $buffer = (string)substr($buffer, $pos + 4);
                    if ($buffer !== '') {
                        $stream->emit('data', array($buffer));
                        $buffer = '';
                    }
                    return;
                }

                // stop buffering when 8 KiB have been read
                if (isset($buffer[8192])) {
                    $deferred->reject(new RuntimeException('Proxy must not send more than 8 KiB of headers'));
                    $stream->close();
                }
            };
            $stream->on('data', $fn);

            $stream->on('error', function (Exception $e) use ($deferred) {
                $deferred->reject(new RuntimeException('Stream error while waiting for response from proxy', 0, $e));
            });

            $stream->on('close', function () use ($deferred) {
                $deferred->reject(new RuntimeException('Connection to proxy lost while waiting for response'));
            });

            $stream->write("CONNECT " . $host . ":" . $port . " HTTP/1.1\r\nHost: " . $host . ":" . $port . "\r\n\r\n");

            return $deferred->promise();
        });
    }
}
