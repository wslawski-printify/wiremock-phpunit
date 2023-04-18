<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

use WireMock\Client\ClientException;
use WireMock\Client\Curl;
use WireMock\Client\HttpWait;
use WireMock\Client\ServeEventQuery;
use WireMock\Phpunit\Dto\Stub;
use WireMock\Phpunit\Exception\RequestVerificationException;
use WireMock\Phpunit\Exception\StartException;
use WireMock\Phpunit\Exception\VerifyException;
use WireMock\Client\WireMock;
use WireMock\Serde\SerializerFactory;

final class WireMockProxy
{
    /**
     * @var array<string, Stub>
     * @internal
     */
    public static array $verifyCallbacks = [];

    /**
     * @internal
     */
    public static ?WireMock $wireMock = null;

    private static ?Curl $curl = null;
    private static string $host;
    private static string $port;

    /**
     * @internal
     */
    public static ?string $testToken = null;

    public static function startWireMock(
        string $host,
        string $port,
        int $timeout
    ): void {
        if (self::$wireMock !== null) {
            return;
        }

        self::$testToken = getenv('TEST_TOKEN') !== false ? getenv('TEST_TOKEN') : null;

        if ($host === '' || $port === '') {
            throw StartException::missingParameters();
        }

        self::$host = $host;
        self::$port = $port;
        self::$curl = new Curl();
        self::$wireMock = new WireMock(
            new HttpWait(),
            self::$curl,
            SerializerFactory::default(),
            $host,
            $port
        );
        $serverStarted = self::$wireMock->isAlive($timeout);

        if (!$serverStarted) {
            throw StartException::timeout($timeout);
        }
    }

    public static function reset(): void
    {
        if (self::$wireMock === null) {
            return;
        }

        foreach (WireMockContext::$scenarios as $scenario) {
            try {
                self::$wireMock->resetScenario($scenario);
            } catch (ClientException $clientException) {
                if ($clientException->getResponseCode() !== 404) {
                    throw $clientException;
                }
            }
        }

        $allRequests = json_decode(
            (string)self::$curl?->get(
                sprintf(
                    'http://%s:%s/__admin/requests',
                    self::$host,
                    self::$port
                )
            ),
            true,
            512,
            JSON_THROW_ON_ERROR
        )['requests'] ?? [];


        $unmatched = array_filter($allRequests, static function (array $request) {
            return $request['wasMatched'] === false;
        });

        foreach ($unmatched as $request) {
            self::$wireMock->removeServeEvent($request['id']);
        }

        WireMockProxy::$verifyCallbacks = [];
    }

    public static function addScenario(string $scenario): void
    {
        if (self::$wireMock === null) {
            return;
        }

        if (!in_array($scenario, WireMockContext::$scenarios, true)) {
            WireMockContext::$scenarios[] = $scenario;
        }
    }

    public static function verify(string $test): void
    {
        $thrownExceptions = [];
        $failedStubs = [];

        foreach (WireMockProxy::$verifyCallbacks as $stub) {
            try {
                ($stub->verificationCallback)($stub->createdAt);

                if ($stub->reset) {
                    self::cleanStub($stub);
                }
            } catch (RequestVerificationException $exception) {
                $thrownExceptions[] = $exception;
                $failedStubs[] = $stub;
            }
        }

        WireMockProxy::$verifyCallbacks = [];

        if (count($thrownExceptions) > 0) {
            foreach ($failedStubs as $failedStub) {
                unset(self::$verifyCallbacks[$failedStub->id]);

                if ($failedStub->reset) {
                    self::cleanStub($failedStub);
                }
            }

            throw new VerifyException($test, ...$thrownExceptions);
        }
    }

    public static function instance(): WireMock
    {
        if (self::$wireMock === null) {
            throw new \RuntimeException('Missing wiremock instance');
        }

        return self::$wireMock;
    }

    private static function cleanStub(Stub $stub): void
    {
        if (self::$wireMock === null) {
            return;
        }

        try {
            self::$wireMock->getSingleStubMapping($stub->id);
        } catch (ClientException $exception) {
            if ($exception->getResponseCode() === 404) {
                return;
            }

            throw $exception;
        }

        self::$wireMock->removeStub($stub->id);
        $serveEvents = self::$wireMock->getAllServeEvents(
            (new ServeEventQuery())
                ->withStubMapping($stub->id)
        );

        foreach ($serveEvents->getRequests() as $serveEvent) {
            self::$wireMock->removeServeEvent($serveEvent->getId());
        }

        unset(WireMockContext::$stubServedRequestCount[$stub->id]);

        if (
            self::$testToken !== null &&
            isset(WireMockContext::$stubTestTokensMappings[$stub->id][self::$testToken])
        ) {
            unset(WireMockContext::$stubTestTokensMappings[$stub->id][self::$testToken]);
        }
    }
}
