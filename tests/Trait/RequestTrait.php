<?php

declare(strict_types=1);

namespace Tests\Trait;

use WireMock\Phpunit\WireMockRequestBodyType;
use WireMock\Phpunit\WireMockTrait;

trait RequestTrait
{
    use WireMockTrait;

    /**
     * @param array<string, string> $requestHeaders
     */
    public function mockTestRequest(string $expectedBody, string $path = '/test', array $requestHeaders = []): void
    {
        $this->wireMock(
            'GET',
            $path,
            $requestHeaders,
            null,
            [],
            $expectedBody
        );
    }

    public function mockTestScenario(
        string $expectedBody,
        string $path,
        int $expectedRequestCount,
        int $expectedStubRequestCount,
        string $inScenario,
        string $whenScenario,
        ?string $toScenario = null
    ): void {
        $this->wireMock(
            'GET',
            $path,
            [],
            null,
            [],
            $expectedBody,
            whenScenario: $whenScenario,
            toScenario: $toScenario,
            inScenario: $inScenario,
            requestCount: $expectedRequestCount,
            stubRequestCount: $expectedStubRequestCount
        );
    }

    /**
     * @param array<string, string> $requestHeaders
     */
    public function mockTestPostRequest(
        string $expectedBody,
        string $requestBody,
        bool $stubRequestBody = false,
        array $requestHeaders = []
    ): void {
        $this->wireMock(
            'POST',
            '/test',
            $requestHeaders,
            $requestBody,
            [],
            $expectedBody,
            200,
            null,
            $stubRequestBody
        );
    }

    public function mockTestPostRequestWithXML(string $expectedBody, string $requestBody): void
    {
        $this->wireMock(
            'POST',
            '/test',
            [],
            $requestBody,
            [],
            $expectedBody,
            200,
            WireMockRequestBodyType::XML
        );
    }
}
