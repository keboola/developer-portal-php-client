<?php

declare(strict_types=1);

namespace Keboola\DeveloperPortal\Test;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\DeveloperPortal\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class ClientWaveTest extends TestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];

    /**
     * @param list<array<mixed>> $responseBodies
     */
    private function createClient(array $responseBodies): Client
    {
        $responses = array_map(
            fn(array $body) => new Response(200, [], (string) json_encode($body)),
            $responseBodies,
        );
        $this->history = [];
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::history($this->history));

        return new Client('https://apps-api.example.com/', [
            'credentials' => ['token' => 'test-token'],
            'options' => ['handler' => $handlerStack],
        ]);
    }

    private function getRequestUri(int $index): string
    {
        self::assertArrayHasKey($index, $this->history);
        return (string) $this->history[$index]['request']->getUri();
    }

    public function testAdminListAppsPaginatedWithoutWave(): void
    {
        $client = $this->createClient([[]]);
        $client->adminListAppsPaginated();
        self::assertSame('https://apps-api.example.com/admin/apps?offset=0&limit=1000', $this->getRequestUri(0));
    }

    public function testAdminListAppsPaginatedWithWave(): void
    {
        $client = $this->createClient([[]]);
        $client->adminListAppsPaginated('foo', 10, 20, 2);
        self::assertSame(
            'https://apps-api.example.com/admin/apps?filter=foo&offset=10&limit=20&wave=2',
            $this->getRequestUri(0),
        );
    }

    public function testAdminListAppsPaginatedWithWaveZero(): void
    {
        $client = $this->createClient([[]]);
        $client->adminListAppsPaginated(null, 0, 1000, 0);
        self::assertSame(
            'https://apps-api.example.com/admin/apps?offset=0&limit=1000&wave=0',
            $this->getRequestUri(0),
        );
    }

    public function testAdminListAppsWithoutWave(): void
    {
        $client = $this->createClient([[['id' => 'a']], []]);
        $result = $client->adminListApps();
        self::assertSame([['id' => 'a']], $result);
        self::assertCount(2, $this->history);
        self::assertSame('https://apps-api.example.com/admin/apps?offset=0&limit=1000', $this->getRequestUri(0));
        self::assertSame('https://apps-api.example.com/admin/apps?offset=1000&limit=1000', $this->getRequestUri(1));
    }

    public function testAdminListAppsWithWavePropagatesToEveryPage(): void
    {
        $client = $this->createClient([[['id' => 'a']], []]);
        $result = $client->adminListApps(null, 3);
        self::assertSame([['id' => 'a']], $result);
        self::assertCount(2, $this->history);
        self::assertSame(
            'https://apps-api.example.com/admin/apps?offset=0&limit=1000&wave=3',
            $this->getRequestUri(0),
        );
        self::assertSame(
            'https://apps-api.example.com/admin/apps?offset=1000&limit=1000&wave=3',
            $this->getRequestUri(1),
        );
    }

    /**
     * @dataProvider adminGetAppProvider
     * @param array{published?: bool, wave?: int|null} $options
     */
    public function testAdminGetApp(array $options, string $expectedUri): void
    {
        $client = $this->createClient([['id' => 'keboola.ex-foo']]);
        $client->adminGetApp('keboola.ex-foo', $options);
        self::assertSame($expectedUri, $this->getRequestUri(0));
    }

    /**
     * @return array<string, array{array{published?: bool, wave?: int|null}, string}>
     */
    public function adminGetAppProvider(): array
    {
        $base = 'https://apps-api.example.com/admin/apps/keboola.ex-foo';
        return [
            'default options' => [['published' => false], $base],
            'empty options' => [[], $base],
            'published only' => [['published' => true], $base . '?published=true'],
            'wave only' => [['wave' => 2], $base . '?wave=2'],
            'wave zero' => [['wave' => 0], $base . '?wave=0'],
            'wave null' => [['published' => false, 'wave' => null], $base],
            'published and wave' => [['published' => true, 'wave' => 3], $base . '?published=true&wave=3'],
            'unpublished and wave' => [['published' => false, 'wave' => 1], $base . '?wave=1'],
        ];
    }

    public function testAdminGetAppDefaultArgument(): void
    {
        $client = $this->createClient([['id' => 'keboola.ex-foo']]);
        $client->adminGetApp('keboola.ex-foo');
        self::assertSame('https://apps-api.example.com/admin/apps/keboola.ex-foo', $this->getRequestUri(0));
    }

    public function testPublicGetAppDetailWithoutWave(): void
    {
        $client = $this->createClient([['id' => 'keboola.ex-foo']]);
        $client->publicGetAppDetail('keboola.ex-foo');
        self::assertSame('https://apps-api.example.com/apps/keboola.ex-foo', $this->getRequestUri(0));
    }

    public function testPublicGetAppDetailWithWave(): void
    {
        $client = $this->createClient([['id' => 'keboola.ex-foo']]);
        $client->publicGetAppDetail('keboola.ex-foo', 2);
        self::assertSame('https://apps-api.example.com/apps/keboola.ex-foo?wave=2', $this->getRequestUri(0));
    }
}
