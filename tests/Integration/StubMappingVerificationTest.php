<?php

declare(strict_types=1);

namespace Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use WireMock\Client\WireMock;
use WireMock\Phpunit\WireMockProxy;
use WireMock\Phpunit\WireMockTrait;

final class StubMappingVerificationTest extends TestCase
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

    public function testItVerifiesUsingStubMappingVerification(): void
    {
        $stub = WireMockProxy::instance()->stubFor(
            WireMock::get('/test')
                ->willReturn(WireMock::aResponse()
                    ->withBody('{"someKey": "someValue"}')
                    ->withHeader('Content-Type', 'application/json'))
        );
        $this->appendStubMappingVerification($stub);

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
        $this->appendStubMappingVerification($stub);

        $response = $this->client->get('/test');
        $result = json_decode($response->getBody()->getContents(), true);
        self::assertEquals(['someKey' => 'someValue'], $result);
    }
}
