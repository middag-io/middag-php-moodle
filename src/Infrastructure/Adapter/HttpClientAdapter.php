<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Adapter;

use core_useragent;
use JsonException;
use Middag\Moodle\Shared\Util\Debug as debug;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client adapter using Symfony HttpClient via PSR-18 bridge.
 *
 * Preserves the previous Guzzle-based API surface: get/post/put/patch/delete
 * return PSR-7 ResponseInterface on success, decoded JSON on error with
 * parseable body, or null on connection/parse failure.
 *
 * @internal
 */
class HttpClientAdapter
{
    private readonly Psr18Client $psrClient;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    public function __construct(private readonly string $baseUri = '')
    {
        global $CFG;

        $config = [
            'timeout' => 30,
            'verify_peer' => true,
            'verify_host' => true,
        ];

        if (!empty($CFG->proxyhost)) {
            $proxy = $CFG->proxyhost;
            if (!empty($CFG->proxyport)) {
                $proxy .= ':' . $CFG->proxyport;
            }

            if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword)) {
                $proxy = $CFG->proxyuser . ':' . $CFG->proxypassword . '@' . $proxy;
            }

            $config['proxy'] = $proxy;
        }

        /** @var HttpClientInterface $symfony */
        $symfony = HttpClient::create($config);
        $factory = new Psr17Factory();
        $this->psrClient = new Psr18Client($symfony, $factory, $factory);

        $this->defaultHeaders = [
            'User-Agent' => core_useragent::get_moodlebot_useragent(),
            'Accept' => 'application/json',
        ];
    }

    public function setHeader(string $key, string $value): self
    {
        $this->defaultHeaders[$key] = $value;

        return $this;
    }

    public function setAuthorizationBearer(string $token): self
    {
        return $this->setHeader('Authorization', 'Bearer ' . $token);
    }

    public function setContentType(string $content_type): self
    {
        return $this->setHeader('Content-Type', $content_type);
    }

    public function get(string $uri, array $query = []): mixed
    {
        $target = $this->buildUri($uri, $query);

        return $this->request('GET', $target, null, null);
    }

    public function post(string $uri, array $data = [], bool $as_json = true): mixed
    {
        if ($as_json) {
            $body = json_encode($data, JSON_THROW_ON_ERROR);
            $contentType = 'application/json';
        } else {
            $body = http_build_query($data);
            $contentType = 'application/x-www-form-urlencoded';
        }

        return $this->request('POST', $this->buildUri($uri), $body, $contentType);
    }

    public function postMultipart(string $uri, array $data = []): mixed
    {
        $boundary = '----MIDDAG' . bin2hex(random_bytes(8));
        $body = $this->buildMultipart($data, $boundary);

        return $this->request('POST', $this->buildUri($uri), $body, 'multipart/form-data; boundary=' . $boundary);
    }

    public function put(string $uri, array $data = []): mixed
    {
        return $this->request('PUT', $this->buildUri($uri), json_encode($data, JSON_THROW_ON_ERROR), 'application/json');
    }

    public function patch(string $uri, array $data = []): mixed
    {
        return $this->request('PATCH', $this->buildUri($uri), json_encode($data, JSON_THROW_ON_ERROR), 'application/json');
    }

    public function delete(string $uri, array $query = []): mixed
    {
        return $this->request('DELETE', $this->buildUri($uri, $query), null, null);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildUri(string $uri, array $query = []): string
    {
        $full = $this->baseUri !== '' && !str_starts_with($uri, 'http') ? rtrim($this->baseUri, '/') . '/' . ltrim($uri, '/') : $uri;

        if ($query !== []) {
            $separator = str_contains($full, '?') ? '&' : '?';
            $full .= $separator . http_build_query($query);
        }

        return $full;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildMultipart(array $data, string $boundary): string
    {
        $body = '';
        foreach ($data as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (!isset($field['name'], $field['contents'])) {
                continue;
            }
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $field['name'] . '"';

            if (isset($field['filename'])) {
                $body .= '; filename="' . $field['filename'] . '"';
            }

            $body .= "\r\n";

            if (isset($field['headers']) && is_array($field['headers'])) {
                foreach ($field['headers'] as $h => $v) {
                    $body .= "{$h}: {$v}\r\n";
                }
            }

            $body .= "\r\n" . $field['contents'] . "\r\n";
        }

        return $body . "--{$boundary}--\r\n";
    }

    private function request(string $method, string $uri, ?string $body, ?string $contentType): mixed
    {
        $request = $this->psrClient->createRequest($method, $uri);

        foreach ($this->defaultHeaders as $h => $v) {
            $request = $request->withHeader($h, $v);
        }

        if ($contentType !== null) {
            $request = $request->withHeader('Content-Type', $contentType);
        }

        if ($body !== null) {
            $request = $request->withBody($this->psrClient->createStream($body));
        }

        try {
            $response = $this->psrClient->sendRequest($request);

            if ($response->getStatusCode() >= 400) {
                return $this->decodeOrResponse($response, $method, $uri);
            }

            return $response;
        } catch (ClientExceptionInterface $clientException) {
            debug::trace(sprintf('HTTP Connection Error %s %s: ', $method, $uri) . $clientException->getMessage());

            return null;
        }
    }

    private function decodeOrResponse(ResponseInterface $response, string $method, string $uri): mixed
    {
        $content = (string) $response->getBody();

        try {
            return json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            debug::trace(sprintf('HTTP Error %s %s: HTTP %d', $method, $uri, $response->getStatusCode()));

            return $response;
        }
    }
}
