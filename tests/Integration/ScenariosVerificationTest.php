<?php

declare(strict_types=1);

namespace Integration;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Tests\Trait\RequestTrait;
use WireMock\Stubbing\Scenario;

final class ScenariosVerificationTest extends TestCase
{
    use RequestTrait;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client([
            'base_uri' => 'http://wiremock:8080',
        ]);
    }

    public function testScenarios(): void
    {
        $this->mockTestScenario(
            (string) json_encode(['test' => 'first']),
            '/test',
            2, // at that point total requests to `/test` is 2, one for each scenario
            1,
            'scenarios',
            Scenario::STARTED,
            'second-part'
        );

        $this->mockTestScenario(
            (string) json_encode(['test' => 'second']),
            '/test',
            1, // at that point total requests to `/test` is 1, this request is cleaned up after first scenario, only for the second scenario
            1,
            'scenarios',
            'second-part'
        );

        $response = $this->client->get('/test');
        $result = json_decode($response->getBody()->getContents(), true);
        self::assertEquals(['test' => 'first'], $result);

        $response = $this->client->get('/test');
        $result = json_decode($response->getBody()->getContents(), true);
        self::assertEquals(['test' => 'second'], $result);
    }
}
