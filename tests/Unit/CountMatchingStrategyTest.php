<?php

declare(strict_types=1);

namespace Tests\Unit;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use WireMock\Client\WireMock;
use WireMock\Phpunit\Exception\VerifyException;
use WireMock\Phpunit\WireMockProxy;
use WireMock\Phpunit\WireMockTrait;

final class CountMatchingStrategyTest extends TestCase
{
    use WireMockTrait;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        WireMockProxy::startWireMock('wiremock', '8080', 3);
        WireMockProxy::instance()->reset();
        WireMockProxy::$verifyCallbacks = [];

        $this->client = new Client(['base_uri' => 'http://wiremock:8080']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        WireMockProxy::$verifyCallbacks = [];
        WireMockProxy::$wireMock = null;
    }

    public function testItVerifiesWhenMoreThanOrExactlyIsSatisfied(): void
    {
        $this->wireMock(
            'GET',
            '/count-test',
            responseBody: (string) json_encode(['ok' => true]),
            requestCount: WireMock::moreThanOrExactly(2),
        );

        $this->client->get('/count-test');
        $this->client->get('/count-test');
        $this->client->get('/count-test');

        WireMockProxy::verify('count-matching-strategy-satisfied');

        self::assertTrue(true);
    }

    public function testItVerifiesWhenServedCountEqualsLowerBound(): void
    {
        $this->wireMock(
            'GET',
            '/count-test',
            responseBody: (string) json_encode(['ok' => true]),
            requestCount: WireMock::moreThanOrExactly(2),
        );

        $this->client->get('/count-test');
        $this->client->get('/count-test');

        WireMockProxy::verify('count-matching-strategy-lower-bound');

        self::assertTrue(true);
    }

    public function testItFailsWhenMoreThanOrExactlyIsNotSatisfied(): void
    {
        $this->wireMock(
            'GET',
            '/count-test',
            responseBody: (string) json_encode(['ok' => true]),
            requestCount: WireMock::moreThanOrExactly(5),
        );

        $this->client->get('/count-test');
        $this->client->get('/count-test');

        $this->expectException(VerifyException::class);
        WireMockProxy::verify('count-matching-strategy-not-satisfied');
    }

    public function testItStillSupportsExactIntegerCount(): void
    {
        $this->wireMock(
            'GET',
            '/count-test',
            responseBody: (string) json_encode(['ok' => true]),
            requestCount: 2,
        );

        $this->client->get('/count-test');
        $this->client->get('/count-test');

        WireMockProxy::verify('exact-integer-count');

        self::assertTrue(true);
    }
}
