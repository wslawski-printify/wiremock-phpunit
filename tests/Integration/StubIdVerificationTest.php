<?php

declare(strict_types=1);

namespace Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use WireMock\Client\WireMock;
use WireMock\Phpunit\WireMockProxy;
use WireMock\Phpunit\WireMockTrait;

final class StubIdVerificationTest extends TestCase
{
    use WireMockTrait;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client([
            'base_uri' => 'http://wiremock:8080',
        ]);
    }

    public function testItVerifiesUsingStubIdVerification(): void
    {
        $stub = WireMockProxy::instance()->stubFor(
            WireMock::get('/test')
                ->willReturn(WireMock::aResponse()
                    ->withBody('{"someKey": "someValue"}')
                    ->withHeader('Content-Type', 'application/json'))
        );
        $this->appendStubIdVerification($stub->getId(), true);

        $response = $this->client->get('/test');
        $result = json_decode($response->getBody()->getContents(), true);
        self::assertEquals(['someKey' => 'someValue'], $result);
    }

    public function testWithTestTokenDefined(): void
    {
        $stub = WireMockProxy::instance()->stubFor(
            WireMock::get('/test')
                ->willReturn(WireMock::aResponse()
                    ->withBody('{"someKey": "someValue"}')
                    ->withHeader('Content-Type', 'application/json'))
        );
        WireMockProxy::$testToken = '12345';
        $this->appendStubIdVerification($stub->getId(), true);

        $response = $this->client->get('/test');
        $result = json_decode($response->getBody()->getContents(), true);
        self::assertEquals(['someKey' => 'someValue'], $result);
    }
}
