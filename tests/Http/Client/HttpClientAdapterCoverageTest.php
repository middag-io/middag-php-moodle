<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Http\Client;

use Middag\Framework\Shared\Enum\DebugMode;
use Middag\Moodle\Http\Client\HttpClientAdapter;
use Middag\Moodle\Shared\Util\Debug;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionProperty;
use stdClass;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * HttpClientAdapter wraps Symfony HttpClient behind the PSR-18 bridge. The
 * network transport is replaced with Symfony's MockHttpClient (its own test
 * double) so every request path — URL building, header seeding, body encoding,
 * success/error/decoding branches, and the connection-failure catch — is
 * exercised for real without touching the network.
 *
 * Request-shaping tests build the adapter via newInstanceWithoutConstructor()
 * and inject a Psr18Client wired over MockHttpClient (the readonly $psrClient is
 * unset before injection, so reflection can seed it). Constructor tests build
 * the adapter normally to cover the proxy-config permutations and the default
 * header seeding from core_useragent.
 *
 * @internal
 */
#[CoversClass(HttpClientAdapter::class)]
final class HttpClientAdapterCoverageTest extends TestCase
{
    /** @var list<array{method: string, url: string, headers: array<int, string>, body: string}> */
    private array $captured = [];

    private mixed $prevCfg = null;

    protected function setUp(): void
    {
        $this->captured = [];
        $this->prevCfg = $GLOBALS['CFG'] ?? null;
        unset($GLOBALS['__middag_test_mtrace']);

        // Enable framework Debug so Debug::trace() actually emits (via the mtrace
        // stub) — lets the error/connection branches be asserted on observable
        // output as well as the return value.
        Debug::setRuntime(new NullLogger(), static fn (): int => DebugMode::FULL->value);
    }

    protected function tearDown(): void
    {
        Debug::resetRuntime();

        if ($this->prevCfg === null) {
            unset($GLOBALS['CFG']);
        } else {
            $GLOBALS['CFG'] = $this->prevCfg;
        }

        unset($GLOBALS['__middag_test_mtrace']);
    }

    // ---- Constructor: default headers + proxy permutations -----------------

    #[Test]
    public function testConstructorSeedsDefaultHeadersFromMoodleUserAgent(): void
    {
        $GLOBALS['CFG'] = new stdClass();

        $adapter = new HttpClientAdapter();

        $headers = $this->readDefaultHeaders($adapter);
        self::assertSame('MoodleBot/1.0 (+https://moodle.test)', $headers['User-Agent']);
        self::assertSame('application/json', $headers['Accept']);
    }

    #[Test]
    public function testConstructorBuildsWithProxyHostOnly(): void
    {
        // proxyhost set, no port, no credentials: exercises the proxyhost branch
        // while the port and user/password branches stay false.
        $cfg = new stdClass();
        $cfg->proxyhost = 'proxy.internal.test';
        $GLOBALS['CFG'] = $cfg;

        // The proxy string is buried in HttpClient::create() config and is not
        // externally observable; asserting successful construction proves the
        // branch executed without error.
        self::assertInstanceOf(HttpClientAdapter::class, new HttpClientAdapter());
    }

    #[Test]
    public function testConstructorBuildsWithProxyHostPortAndCredentials(): void
    {
        // proxyhost + port + user + password: exercises the port and credential
        // branches of the proxy assembly.
        $cfg = new stdClass();
        $cfg->proxyhost = 'proxy.internal.test';
        $cfg->proxyport = 8080;
        $cfg->proxyuser = 'bob';
        $cfg->proxypassword = 's3cret';
        $GLOBALS['CFG'] = $cfg;

        self::assertInstanceOf(HttpClientAdapter::class, new HttpClientAdapter('https://base.test'));
    }

    // ---- Fluent header setters ---------------------------------------------

    #[Test]
    public function testSetHeaderIsFluentAndAttachesTheHeaderToRequests(): void
    {
        $adapter = $this->makeInjectedAdapter($this->recording(new MockResponse('ok', ['http_code' => 200])));

        self::assertSame($adapter, $adapter->setHeader('X-Trace-Id', 'abc-123'));

        $adapter->get('https://api.test/ping');

        self::assertRequestHeaderSent('X-Trace-Id: abc-123');
    }

    #[Test]
    public function testSetAuthorizationBearerAttachesBearerHeader(): void
    {
        $adapter = $this->makeInjectedAdapter($this->recording(new MockResponse('ok', ['http_code' => 200])));

        self::assertSame($adapter, $adapter->setAuthorizationBearer('TOK-42'));

        $adapter->get('https://api.test/me');

        self::assertRequestHeaderSent('Authorization: Bearer TOK-42');
    }

    #[Test]
    public function testSetContentTypeAttachesContentTypeHeader(): void
    {
        $adapter = $this->makeInjectedAdapter($this->recording(new MockResponse('ok', ['http_code' => 200])));

        self::assertSame($adapter, $adapter->setContentType('application/xml'));

        // GET passes contentType=null so the default header set here is what
        // ends up on the wire (not overridden by the per-request content type).
        $adapter->get('https://api.test/doc');

        self::assertRequestHeaderSent('Content-Type: application/xml');
    }

    // ---- GET + URL building ------------------------------------------------

    #[Test]
    public function testGetReturnsResponseAndSeedsDefaultHeaders(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('PONG', ['http_code' => 200])),
        );

        $response = $adapter->get('https://api.test/health');

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('PONG', (string) $response->getBody());

        $last = $this->lastRequest();
        self::assertSame('GET', $last['method']);
        self::assertSame('https://api.test/health', $last['url']);
        self::assertRequestHeaderSent('User-Agent: TestBot/1.0');
        self::assertRequestHeaderSent('Accept: application/json');
    }

    #[Test]
    public function testGetPrependsBaseUriAndAppendsQueryWithQuestionMark(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
            'https://api.test/',
        );

        $adapter->get('/users', ['page' => 2, 'per' => 50]);

        self::assertSame('https://api.test/users?page=2&per=50', $this->lastRequest()['url']);
    }

    #[Test]
    public function testGetKeepsAbsoluteUrlEvenWhenBaseUriIsSet(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
            'https://api.test',
        );

        // uri starts with "http" → base URI is ignored, uri is used verbatim.
        $adapter->get('https://other.test/raw');

        self::assertSame('https://other.test/raw', $this->lastRequest()['url']);
    }

    #[Test]
    public function testGetAppendsQueryWithAmpersandWhenUrlAlreadyHasQuery(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        // baseUri empty + uri already carries a query → separator must be "&".
        $adapter->get('https://api.test/search?q=php', ['limit' => 10]);

        self::assertSame('https://api.test/search?q=php&limit=10', $this->lastRequest()['url']);
    }

    // ---- POST (json / form) ------------------------------------------------

    #[Test]
    public function testPostJsonEncodesBodyAndSetsJsonContentType(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('{"ok":true}', ['http_code' => 200])),
        );

        $response = $adapter->post('https://api.test/items', ['name' => 'widget', 'qty' => 3]);

        self::assertInstanceOf(ResponseInterface::class, $response);
        $last = $this->lastRequest();
        self::assertSame('POST', $last['method']);
        self::assertSame('{"name":"widget","qty":3}', $last['body']);
        self::assertRequestHeaderSent('Content-Type: application/json');
    }

    #[Test]
    public function testPostFormUrlEncodesBodyAndSetsFormContentType(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        $adapter->post('https://api.test/form', ['a' => '1', 'b' => 'two'], false);

        $last = $this->lastRequest();
        self::assertSame('a=1&b=two', $last['body']);
        self::assertRequestHeaderSent('Content-Type: application/x-www-form-urlencoded');
    }

    // ---- POST multipart ----------------------------------------------------

    #[Test]
    public function testPostMultipartBuildsBodyAcrossEveryFieldBranch(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        $adapter->postMultipart('https://api.test/upload', [
            'not-an-array-skipped',                                        // not array → skipped
            ['name' => 'no-contents'],                                     // missing contents → skipped
            ['contents' => 'no-name'],                                     // missing name → skipped
            ['name' => 'field1', 'contents' => 'value1'],                  // plain field
            ['name' => 'file1', 'contents' => 'FILEDATA', 'filename' => 'a.txt'], // filename branch
            ['name' => 'field2', 'contents' => 'value2', 'headers' => ['X-Part' => 'hv']], // per-part headers
            ['name' => 'field3', 'contents' => 'value3', 'headers' => 'not-array'],        // headers not array → inner skipped
        ]);

        $last = $this->lastRequest();

        // Content-Type carries the generated (random) boundary, so match on the
        // stable prefix rather than the exact header line.
        self::assertRequestHeaderContains('Content-Type: multipart/form-data; boundary=----MIDDAG');

        $body = $last['body'];
        // Included fields.
        self::assertStringContainsString('name="field1"', $body);
        self::assertStringContainsString('value1', $body);
        self::assertStringContainsString('name="file1"; filename="a.txt"', $body);
        self::assertStringContainsString('FILEDATA', $body);
        self::assertStringContainsString('name="field2"', $body);
        self::assertStringContainsString('X-Part: hv', $body);
        self::assertStringContainsString('name="field3"', $body);
        self::assertStringContainsString('value3', $body);
        // Closing boundary.
        self::assertStringContainsString('----MIDDAG', $body);
        // Skipped entries never produced a form-data name.
        self::assertStringNotContainsString('name="no-contents"', $body);
        self::assertStringNotContainsString('name="no-name"', $body);
        self::assertStringNotContainsString('not-an-array-skipped', $body);
    }

    // ---- PUT / PATCH / DELETE ----------------------------------------------

    #[Test]
    public function testPutSendsJsonBodyWithPutMethod(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        $adapter->put('https://api.test/items/1', ['name' => 'renamed']);

        $last = $this->lastRequest();
        self::assertSame('PUT', $last['method']);
        self::assertSame('{"name":"renamed"}', $last['body']);
        self::assertRequestHeaderSent('Content-Type: application/json');
    }

    #[Test]
    public function testPatchSendsJsonBodyWithPatchMethod(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        $adapter->patch('https://api.test/items/1', ['qty' => 9]);

        $last = $this->lastRequest();
        self::assertSame('PATCH', $last['method']);
        self::assertSame('{"qty":9}', $last['body']);
        self::assertRequestHeaderSent('Content-Type: application/json');
    }

    #[Test]
    public function testDeleteSendsDeleteMethodWithQueryAndNoBody(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('', ['http_code' => 200])),
        );

        $adapter->delete('https://api.test/items/1', ['force' => 1]);

        $last = $this->lastRequest();
        self::assertSame('DELETE', $last['method']);
        self::assertSame('https://api.test/items/1?force=1', $last['url']);
        self::assertSame('', $last['body']);
    }

    // ---- Error handling: decode-or-response --------------------------------

    #[Test]
    public function testErrorStatusWithJsonBodyReturnsDecodedObject(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('{"code":404,"message":"not found"}', ['http_code' => 404])),
        );

        $decoded = $adapter->get('https://api.test/missing');

        self::assertInstanceOf(stdClass::class, $decoded);
        self::assertSame(404, $decoded->code);
        self::assertSame('not found', $decoded->message);
    }

    #[Test]
    public function testErrorStatusWithNonJsonBodyReturnsResponseAndTraces(): void
    {
        $adapter = $this->makeInjectedAdapter(
            $this->recording(new MockResponse('Internal Server Error', ['http_code' => 500])),
        );

        $result = $adapter->get('https://api.test/boom');

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(500, $result->getStatusCode());
        self::assertSame('Internal Server Error', (string) $result->getBody());
        self::assertTrue($this->mtraceContains('HTTP Error GET https://api.test/boom: HTTP 500'));
    }

    // ---- Connection failure -------------------------------------------------

    #[Test]
    public function testConnectionFailureReturnsNullAndTraces(): void
    {
        $adapter = $this->makeInjectedAdapter(
            static function (): MockResponse {
                throw new TransportException('Connection refused');
            },
        );

        $result = $adapter->get('https://api.test/down');

        self::assertNull($result);
        self::assertTrue($this->mtraceContains('HTTP Connection Error GET https://api.test/down: Connection refused'));
    }

    // ---- helpers -----------------------------------------------------------

    /**
     * Build an adapter with the network transport swapped for MockHttpClient.
     * The constructor is bypassed (readonly $psrClient cannot be replaced once
     * initialized), and the three private fields are seeded via reflection with
     * known values so requests are fully deterministic.
     */
    private function makeInjectedAdapter(callable $responder, string $baseUri = ''): HttpClientAdapter
    {
        $factory = new Psr17Factory();
        $psr18 = new Psr18Client(new MockHttpClient($responder), $factory, $factory);

        $adapter = (new ReflectionClass(HttpClientAdapter::class))->newInstanceWithoutConstructor();

        $this->writeProperty($adapter, 'psrClient', $psr18);
        $this->writeProperty($adapter, 'baseUri', $baseUri);
        $this->writeProperty($adapter, 'defaultHeaders', [
            'User-Agent' => 'TestBot/1.0',
            'Accept' => 'application/json',
        ]);

        return $adapter;
    }

    /**
     * A MockHttpClient response factory that records the outgoing request shape
     * and returns the given canned response.
     */
    private function recording(MockResponse $response): callable
    {
        return function (string $method, string $url, array $options) use ($response): MockResponse {
            $body = $options['body'] ?? '';

            $this->captured[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'] ?? [],
                'body' => is_string($body) ? $body : '',
            ];

            return $response;
        };
    }

    /**
     * @return array{method: string, url: string, headers: array<int, string>, body: string}
     */
    private function lastRequest(): array
    {
        self::assertNotEmpty($this->captured, 'No request was captured.');

        return $this->captured[array_key_last($this->captured)];
    }

    private function assertRequestHeaderSent(string $expected): void
    {
        $headers = $this->lastRequest()['headers'];

        self::assertContains($expected, $headers, sprintf(
            'Expected header "%s" among: %s',
            $expected,
            implode(' | ', $headers),
        ));
    }

    private function assertRequestHeaderContains(string $needle): void
    {
        foreach ($this->lastRequest()['headers'] as $header) {
            if (str_contains($header, $needle)) {
                self::assertStringContainsString($needle, $header);

                return;
            }
        }

        self::fail(sprintf('No sent header contains "%s".', $needle));
    }

    private function mtraceContains(string $needle): bool
    {
        foreach ($GLOBALS['__middag_test_mtrace'] ?? [] as $line) {
            if (str_contains((string) $line, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function readDefaultHeaders(HttpClientAdapter $adapter): array
    {
        $property = new ReflectionProperty(HttpClientAdapter::class, 'defaultHeaders');

        return $property->getValue($adapter);
    }

    private function writeProperty(object $target, string $name, mixed $value): void
    {
        (new ReflectionProperty($target, $name))->setValue($target, $value);
    }
}
