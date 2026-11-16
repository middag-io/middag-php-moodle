<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Tests\Support;

use InvalidArgumentException;
use Middag\Moodle\Http\Contract\RouterInterface;
use Middag\Moodle\Http\MoodleHttpKernel;
use Middag\Moodle\Runtime\Kernel;
use Middag\Moodle\Support\RouterBridgeSupport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Round-trip tests for RouterBridgeSupport::proxyRequest().
 *
 * Each test builds a PSR-7 request the way Moodle's native router (Slim via
 * r.php) hands it over, sends it through the real Kernel and MoodleHttpKernel
 * against a small test route table, and inspects the PSR-7 response the proxy
 * returns. Nothing in the request or response path is mocked.
 *
 * @internal
 */
#[CoversClass(RouterBridgeSupport::class)]
#[CoversClass(Kernel::class)]
final class RouterBridgeSupportProxyTest extends TestCase
{
    private const string LEAK_MARKER = 'leak-marker-7f3a';

    private Psr17Factory $psr17;

    /** @var array<string, mixed> */
    private array $prevServer = [];

    /** @var array<string, mixed> */
    private array $prevGet = [];

    /** @var array<string, mixed> */
    private array $prevPost = [];

    /** @var array<string, mixed> */
    private array $prevFiles = [];

    protected function setUp(): void
    {
        $this->prevServer = $_SERVER;
        $this->prevGet = $_GET;
        $this->prevPost = $_POST;
        $this->prevFiles = $_FILES;
        $this->psr17 = new Psr17Factory();
        Kernel::shutdown();
        $this->bootKernelWithTestRoutes();
    }

    protected function tearDown(): void
    {
        Kernel::shutdown();
        $_SERVER = $this->prevServer;
        $_GET = $this->prevGet;
        $_POST = $this->prevPost;
        $_FILES = $this->prevFiles;
        http_response_code(200);
    }

    #[Test]
    #[DataProvider('methodProvider')]
    public function forwardsTheHttpMethod(string $method): void
    {
        $echo = $this->echo($this->proxy($this->slimRequest($method, 'echo'), 'echo'));

        self::assertSame($method, $echo['method']);
        self::assertSame('/api/echo', $echo['path']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methodProvider(): iterable
    {
        yield 'GET' => ['GET'];

        yield 'POST' => ['POST'];

        yield 'PUT' => ['PUT'];

        yield 'DELETE' => ['DELETE'];
    }

    #[Test]
    public function routesMethodRestrictedRoutes(): void
    {
        $allowed = $this->proxy($this->slimRequest('POST', 'only-post'), 'only-post');
        $denied = $this->proxy($this->slimRequest('GET', 'only-post'), 'only-post');

        self::assertSame(200, $allowed->getStatusCode());
        self::assertSame('post-only', (string) $allowed->getBody());
        self::assertSame(405, $denied->getStatusCode());
    }

    #[Test]
    public function keepsTheQueryString(): void
    {
        $request = $this->slimRequest('GET', 'echo?page=2&tags%5B%5D=a&tags%5B%5D=b')
            ->withQueryParams(['page' => '2', 'tags' => ['a', 'b']]);

        $echo = $this->echo($this->proxy($request, '/echo'));

        self::assertSame(['page' => '2', 'tags' => ['a', 'b']], $echo['query']);
        self::assertSame('page=2&tags%5B%5D=a&tags%5B%5D=b', $echo['query_string']);
        self::assertSame('/api/echo', $echo['path']);
    }

    #[Test]
    public function rebuildsQueryParamsMoodleStripsFromTheRequest(): void
    {
        // Moodle's request validator drops every query param the route does not
        // declare, so the route handler receives empty query params while the
        // URI still carries the full query string.
        $request = $this->slimRequest('GET', 'echo?type=course&sesskey=abc&wstoken=t0k&tags%5B%5D=a&tags%5B%5D=b')
            ->withQueryParams([]);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(
            ['type' => 'course', 'sesskey' => 'abc', 'wstoken' => 't0k', 'tags' => ['a', 'b']],
            $echo['query'],
        );
    }

    #[Test]
    public function forwardsTheRawJsonBody(): void
    {
        $json = '{"name":"Ada","tags":["x","y"]}';
        $request = $this->slimRequest('POST', 'echo')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psr17->createStream($json));

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame($json, $echo['content']);
        self::assertSame(['name' => 'Ada', 'tags' => ['x', 'y']], $echo['json']);
    }

    #[Test]
    public function forwardsTheParsedFormBody(): void
    {
        $request = $this->slimRequest('POST', 'echo')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->psr17->createStream('title=Hello&count=3'))
            ->withParsedBody(['title' => 'Hello', 'count' => '3']);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['title' => 'Hello', 'count' => '3'], $echo['form']);
        self::assertSame('title=Hello&count=3', $echo['content']);
    }

    #[Test]
    public function forwardsHeadersIncludingMultipleValues(): void
    {
        $request = $this->slimRequest('GET', 'echo')
            ->withHeader('Authorization', 'Bearer abc')
            ->withHeader('X-Multi', ['first', 'second']);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['Bearer abc'], $echo['headers']['authorization']);
        self::assertSame(['first', 'second'], $echo['headers']['x-multi']);
        self::assertSame(['moodle.test'], $echo['headers']['host']);
    }

    #[Test]
    public function forwardsCookiesAttributesAndServerParams(): void
    {
        $request = $this->slimRequest('GET', 'echo')
            ->withCookieParams(['MoodleSession' => 'sess-1', 'theme' => 'dark'])
            ->withAttribute('route_name', 'native.echo');

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['MoodleSession' => 'sess-1', 'theme' => 'dark'], $echo['cookies']);
        self::assertSame('native.echo', $echo['attribute']);
        self::assertSame('203.0.113.9', $echo['client_ip']);
    }

    /**
     * Moodle's request validator empties the parsed body of a route that
     * declares no body; the raw form stream is still there.
     */
    #[Test]
    #[DataProvider('formMethodProvider')]
    public function parsesAUrlencodedFormBodyMoodleEmptied(string $method): void
    {
        $request = $this->slimRequest($method, 'echo')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8')
            ->withBody($this->readStream('sesskey=abc&name=Ada+L&tags%5B%5D=x'))
            ->withParsedBody([]);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['sesskey' => 'abc', 'name' => 'Ada L', 'tags' => ['x']], $echo['form']);
        self::assertSame('sesskey=abc&name=Ada+L&tags%5B%5D=x', $echo['content']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function formMethodProvider(): iterable
    {
        yield 'POST' => ['POST'];

        yield 'PUT' => ['PUT'];

        yield 'PATCH' => ['PATCH'];
    }

    #[Test]
    public function fallsBackToPhpGlobalsForAMultipartPostMoodleEmptied(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mfmup');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'file-bytes');
        $_POST = ['sesskey' => 'abc', 'title' => 'Report'];
        $_FILES = ['doc' => [
            'name' => 'report.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => 10,
        ]];

        $request = $this->slimRequest('POST', 'echo')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=x')
            ->withParsedBody([]);

        try {
            $echo = $this->echo($this->proxy($request, 'echo'));
        } finally {
            @unlink($tmp);
        }

        self::assertSame(['sesskey' => 'abc', 'title' => 'Report'], $echo['form']);
        self::assertSame(['doc' => 'report.txt'], $echo['files']);
        self::assertSame(['doc' => 'file-bytes'], $echo['file_contents']);
    }

    #[Test]
    public function leavesAnEmptyMultipartBodyAloneWhenPhpGlobalsAreEmpty(): void
    {
        $_POST = [];
        $_FILES = [];
        $request = $this->slimRequest('POST', 'echo')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=x')
            ->withParsedBody([]);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame([], $echo['form']);
        self::assertSame([], $echo['files']);
    }

    #[Test]
    public function keepsAParsedBodyMoodleAlreadyProvided(): void
    {
        $_POST = ['from' => 'globals'];
        $request = $this->slimRequest('POST', 'echo')
            ->withHeader('Content-Type', 'multipart/form-data; boundary=x')
            ->withParsedBody(['from' => 'request']);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['from' => 'request'], $echo['form']);
    }

    #[Test]
    public function forwardsUploadedFiles(): void
    {
        $upload = $this->psr17->createUploadedFile(
            $this->psr17->createStream('file-bytes'),
            10,
            UPLOAD_ERR_OK,
            'notes.txt',
            'text/plain',
        );
        $request = $this->slimRequest('POST', 'echo')->withUploadedFiles(['doc' => $upload]);

        $echo = $this->echo($this->proxy($request, 'echo'));

        self::assertSame(['doc' => 'notes.txt'], $echo['files']);
    }

    #[Test]
    #[DataProvider('scriptNameProvider')]
    public function matchesTheForwardedPathWhateverTheEntryScript(string $scriptName): void
    {
        $request = $this->psr17->createServerRequest(
            'GET',
            'https://moodle.test' . $scriptName . '/local_example/echo',
            [
                'SCRIPT_NAME' => $scriptName,
                'SCRIPT_FILENAME' => '/var/www/html' . $scriptName,
                'PHP_SELF' => $scriptName . '/local_example/echo',
                'REQUEST_URI' => $scriptName . '/local_example/echo',
            ],
        );

        $response = $this->proxy($request, 'echo');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/api/echo', $this->echo($response)['path']);
    }

    /**
     * A Moodle install served from a sub-directory (even one named like the API
     * prefix) must not shift the path the framework router matches.
     *
     * @return iterable<string, array{string}>
     */
    public static function scriptNameProvider(): iterable
    {
        yield 'root install' => ['/r.php'];

        yield 'sub-directory install' => ['/moodle/r.php'];

        yield 'sub-directory named api' => ['/api/r.php'];
    }

    #[Test]
    #[DataProvider('statusProvider')]
    public function copiesStatusCodeAndReasonPhrase(int $code, string $reason): void
    {
        $response = $this->proxy($this->slimRequest('GET', 'status/' . $code), 'status/' . $code);

        self::assertSame($code, $response->getStatusCode());
        self::assertSame($reason, $response->getReasonPhrase());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function statusProvider(): iterable
    {
        yield '201 Created' => [201, 'Created'];

        yield '204 No Content' => [204, 'No Content'];

        yield '422 Unprocessable' => [422, 'Unprocessable Content'];

        yield '503 Unavailable' => [503, 'Service Unavailable'];
    }

    #[Test]
    #[DataProvider('encodedPathProvider')]
    public function decodesTheForwardedPathOnlyOnce(string $rawTail, string $slimPath, string $expectedId): void
    {
        $response = $this->proxy($this->slimRequest('GET', $rawTail), $slimPath);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame($expectedId, (string) $response->getBody());
    }

    /**
     * Moodle's router hands the proxy a path Slim already decoded once; the
     * framework router must decode the raw URI path exactly once, as on the
     * plugin's native endpoint.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function encodedPathProvider(): iterable
    {
        // raw URI tail, path as Slim decodes it, id the controller must see
        yield 'encoded slash' => ['item/a%2Fb', 'item/a/b', 'a/b'];

        yield 'encoded percent before 2F' => ['item/a%252Fb', 'item/a%2Fb', 'a%2Fb'];

        yield 'double-encoded percent' => ['item/segments%25252Ffields', 'item/segments%252Ffields', 'segments%252Ffields'];

        yield 'encoded percent before 41' => ['item/a%2541', 'item/a%41', 'a%41'];

        yield 'encoded letter' => ['item/a%41', 'item/aA', 'aA'];
    }

    #[Test]
    public function encodesThePathWhenTheUriDoesNotCarryIt(): void
    {
        $request = $this->psr17->createServerRequest('GET', 'https://moodle.test/r.php/elsewhere');

        $response = $this->proxy($request, 'item/a%41 b');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        self::assertSame('a%41 b', (string) $response->getBody());
    }

    #[Test]
    public function returnsTheKernel404ForAnUnknownRoute(): void
    {
        $request = $this->slimRequest('GET', 'missing')->withHeader('Accept', 'application/json');

        $response = $this->proxy($request, 'missing');

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Route not found', (string) $response->getBody());
    }

    #[Test]
    public function preservesEveryValueOfMultiValueResponseHeaders(): void
    {
        $response = $this->proxy($this->slimRequest('GET', 'multi'), 'multi');

        self::assertSame(['one', 'two', 'three'], $response->getHeader('X-Multi'));

        // The kernel adds the XSRF-TOKEN cookie; the controller adds two more.
        $cookies = $response->getHeader('Set-Cookie');
        self::assertCount(3, $cookies);
        self::assertStringStartsWith('a=1', $this->cookieStartingWith($cookies, 'a='));
        self::assertStringStartsWith('b=2', $this->cookieStartingWith($cookies, 'b='));
        self::assertStringStartsWith('XSRF-TOKEN=', $this->cookieStartingWith($cookies, 'XSRF-TOKEN='));
    }

    #[Test]
    public function replacesHeadersTheHostResponseAlreadyCarried(): void
    {
        $hostResponse = $this->psr17->createResponse()
            ->withHeader('X-Multi', 'stale')
            ->withHeader('X-Host-Only', 'kept');

        $response = RouterBridgeSupport::proxyRequest($this->slimRequest('GET', 'multi'), $hostResponse, 'multi');

        self::assertSame(['one', 'two', 'three'], $response->getHeader('X-Multi'));
        self::assertSame(['kept'], $response->getHeader('X-Host-Only'));
    }

    #[Test]
    public function copiesTheBodyBytesExactly(): void
    {
        $response = $this->proxy($this->slimRequest('GET', 'bytes'), 'bytes');

        self::assertSame($this->binaryPayload(), (string) $response->getBody());
        self::assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
    }

    /**
     * @param array<string, string> $headers
     */
    #[Test]
    #[DataProvider('acceptProvider')]
    public function controllerFailureBecomesA500WithoutDetails(array $headers): void
    {
        $request = $this->slimRequest('GET', 'fail');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->proxy($request, 'fail');

        self::assertSame(500, $response->getStatusCode());
        $this->assertNoLeak($response);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function acceptProvider(): iterable
    {
        yield 'json client' => [['Accept' => 'application/json']];

        yield 'browser client' => [['Accept' => 'text/html']];
    }

    #[Test]
    public function kernelFailureBecomesA500JsonWithoutDetails(): void
    {
        $request = $this->slimRequest('POST', 'echo')->withBody($this->explodingStream());

        $response = $this->proxy($request, 'echo');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"error":"Internal framework error"}', (string) $response->getBody());
        $this->assertNoLeak($response);
    }

    #[Test]
    public function kernelBootFailureBecomesA500Json(): void
    {
        Kernel::shutdown();

        $response = $this->proxy($this->slimRequest('GET', 'echo'), 'echo');

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('{"error":"Internal framework error"}', (string) $response->getBody());
    }

    #[Test]
    public function rejectsARequestThatIsNotPsr7(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(ServerRequestInterface::class);

        RouterBridgeSupport::proxyRequest(new stdClass(), $this->psr17->createResponse(), 'echo');
    }

    #[Test]
    public function rejectsAResponseThatIsNotPsr7(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(ResponseInterface::class);

        RouterBridgeSupport::proxyRequest($this->slimRequest('GET', 'echo'), new stdClass(), 'echo');
    }

    #[Test]
    public function handleRequestDispatchesAGivenServerRequest(): void
    {
        $request = $this->psr17->createServerRequest('PUT', 'https://moodle.test/api/echo?x=1')
            ->withQueryParams(['x' => '1']);

        $response = Kernel::handleRequest($request);

        $echo = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('PUT', $echo['method']);
        self::assertSame(['x' => '1'], $echo['query']);
    }

    #[Test]
    public function handleReturningStillBuildsTheRequestFromGlobals(): void
    {
        $_SERVER['HTTP_HOST'] = 'moodle.test';
        $_SERVER['SERVER_NAME'] = 'moodle.test';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['SCRIPT_NAME'] = '/local/example/index.php';
        $_SERVER['SCRIPT_FILENAME'] = '/var/www/html/local/example/index.php';
        $_SERVER['REQUEST_URI'] = '/local/example/index.php/api/echo?y=2';
        $_SERVER['QUERY_STRING'] = 'y=2';
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        $_GET = ['y' => '2'];

        $echo = $this->echo(Kernel::handleReturning());

        self::assertSame('DELETE', $echo['method']);
        self::assertSame('/api/echo', $echo['path']);
        self::assertSame(['y' => '2'], $echo['query']);
    }

    private function proxy(ServerRequestInterface $request, string $path): ResponseInterface
    {
        return RouterBridgeSupport::proxyRequest($request, $this->psr17->createResponse(), $path);
    }

    /**
     * A request shaped like the one Moodle's native router passes to a route
     * handler: the r.php URL and the server params of the real entry script.
     */
    private function slimRequest(string $method, string $pathAndQuery): ServerRequestInterface
    {
        return $this->psr17->createServerRequest(
            $method,
            'https://moodle.test/r.php/local_example/' . $pathAndQuery,
            [
                'SCRIPT_NAME' => '/r.php',
                'SCRIPT_FILENAME' => '/var/www/html/r.php',
                'PHP_SELF' => '/r.php/local_example/' . $pathAndQuery,
                'REQUEST_URI' => '/r.php/local_example/' . $pathAndQuery,
                'REMOTE_ADDR' => '203.0.113.9',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function echo(ResponseInterface $response): array
    {
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $cookies
     */
    private function cookieStartingWith(array $cookies, string $prefix): string
    {
        foreach ($cookies as $cookie) {
            if (str_starts_with($cookie, $prefix)) {
                return $cookie;
            }
        }

        self::fail('No Set-Cookie value starts with ' . $prefix);
    }

    private function assertNoLeak(ResponseInterface $response): void
    {
        self::assertStringNotContainsString(self::LEAK_MARKER, (string) $response->getBody());

        foreach ($response->getHeaders() as $values) {
            foreach ($values as $value) {
                self::assertStringNotContainsString(self::LEAK_MARKER, $value);
            }
        }
    }

    /**
     * A request body whose read fails, so the failure happens inside the kernel
     * (while it bridges the request) rather than inside a controller.
     */
    private function explodingStream(): StreamInterface
    {
        return new readonly class(Stream::create('')) implements StreamInterface {
            public function __construct(private StreamInterface $inner) {}

            public function __toString(): string
            {
                throw new RuntimeException('leak-marker-7f3a: body read failed');
            }

            public function close(): void
            {
                $this->inner->close();
            }

            public function detach()
            {
                return $this->inner->detach();
            }

            public function getSize(): ?int
            {
                return $this->inner->getSize();
            }

            public function tell(): int
            {
                return $this->inner->tell();
            }

            public function eof(): bool
            {
                return $this->inner->eof();
            }

            public function isSeekable(): bool
            {
                return $this->inner->isSeekable();
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                $this->inner->seek($offset, $whence);
            }

            public function rewind(): void
            {
                $this->inner->rewind();
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new RuntimeException('read-only');
            }

            public function isReadable(): bool
            {
                return true;
            }

            public function read(int $length): string
            {
                return $this->__toString();
            }

            public function getContents(): string
            {
                return $this->__toString();
            }

            public function getMetadata(?string $key = null)
            {
                return $this->inner->getMetadata($key);
            }
        };
    }

    /**
     * A body stream positioned at its end, as after a read by the host.
     */
    private function readStream(string $contents): StreamInterface
    {
        $stream = $this->psr17->createStream($contents);
        $stream->getContents();

        return $stream;
    }

    private function binaryPayload(): string
    {
        return "\x00\x01\xFF\xFEbinary\r\n\x00tail";
    }

    /**
     * Boot the Kernel singleton around a real MoodleHttpKernel and a small
     * route table served by {@see self::testController()}.
     */
    private function bootKernelWithTestRoutes(): void
    {
        $container = new ContainerBuilder();
        $container->set('test.proxy_controller', $this->testController());

        $routes = new RouteCollection();
        $routes->add('echo', new Route('/api/echo', ['_controller' => ['test.proxy_controller', 'echo']]));
        $routes->add('only_post', new Route('/api/only-post', ['_controller' => ['test.proxy_controller', 'onlyPost']], methods: ['POST']));
        $routes->add('status', new Route('/api/status/{code}', ['_controller' => ['test.proxy_controller', 'status']], ['code' => '\d{3}']));
        $routes->add('multi', new Route('/api/multi', ['_controller' => ['test.proxy_controller', 'multi']]));
        $routes->add('bytes', new Route('/api/bytes', ['_controller' => ['test.proxy_controller', 'bytes']]));
        $routes->add('item', new Route('/api/item/{id}', ['_controller' => ['test.proxy_controller', 'item']], ['id' => '.+']));
        $routes->add('fail', new Route('/api/fail', ['_controller' => ['test.proxy_controller', 'fail']]));

        $httpKernel = new MoodleHttpKernel(
            $container,
            $routes,
            new RequestContext(),
            new HttpFoundationFactory(),
            new PsrHttpFactory($this->psr17, $this->psr17, $this->psr17, $this->psr17),
        );

        $router = new readonly class($routes) implements RouterInterface {
            private RequestContext $context;

            public function __construct(private RouteCollection $routes)
            {
                $this->context = new RequestContext();
            }

            public function initializeContext(): void {}

            public function getRoutes(): RouteCollection
            {
                return $this->routes;
            }

            public function getContext(): RequestContext
            {
                return $this->context;
            }

            public function registerDefaultRoutes(): void {}

            public function scanAnnotations(ContainerInterface $container, ?string $specificClass = null): void {}

            public function generateUrl(string $route, array $parameters = [], int $referenceType = 1): string
            {
                return '';
            }
        };

        $reflection = new ReflectionClass(Kernel::class);
        $kernel = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('booted')->setValue($kernel, true);
        $reflection->getProperty('router')->setValue($kernel, $router);
        $reflection->getProperty('httpKernel')->setValue($kernel, $httpKernel);
        $reflection->getProperty('instance')->setValue(null, $kernel);
    }

    private function testController(): object
    {
        return new class {
            public function echo(Request $request): JsonResponse
            {
                $files = [];
                $fileContents = [];

                foreach ($request->files->all() as $name => $file) {
                    $files[$name] = $file->getClientOriginalName();
                    $fileContents[$name] = (string) file_get_contents($file->getPathname());
                }

                return new JsonResponse([
                    'method' => $request->getMethod(),
                    'path' => $request->getPathInfo(),
                    'query' => $request->query->all(),
                    'query_string' => $request->server->get('QUERY_STRING'),
                    'form' => $request->request->all(),
                    'content' => $request->getContent(),
                    'json' => json_decode($request->getContent() ?: 'null', true),
                    'headers' => $request->headers->all(),
                    'cookies' => $request->cookies->all(),
                    'attribute' => $request->attributes->get('route_name'),
                    'client_ip' => $request->getClientIp(),
                    'files' => $files,
                    'file_contents' => $fileContents,
                ]);
            }

            public function item(Request $request): Response
            {
                return new Response((string) $request->attributes->get('id'));
            }

            public function onlyPost(): Response
            {
                return new Response('post-only');
            }

            public function status(Request $request): Response
            {
                return new Response('', (int) $request->attributes->get('code'));
            }

            public function multi(): Response
            {
                $response = new Response('multi');
                $response->headers->set('X-Multi', ['one', 'two', 'three']);
                $response->headers->setCookie(Cookie::create('a', '1'));
                $response->headers->setCookie(Cookie::create('b', '2'));

                return $response;
            }

            public function bytes(): Response
            {
                return new Response($this->payload(), 200, ['Content-Type' => 'application/octet-stream']);
            }

            public function fail(): never
            {
                throw new RuntimeException('leak-marker-7f3a: controller failed');
            }

            private function payload(): string
            {
                return "\x00\x01\xFF\xFEbinary\r\n\x00tail";
            }
        };
    }
}
