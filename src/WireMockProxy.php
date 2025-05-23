<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

use WireMock\Client\ClientException;
use WireMock\Client\ServeEventQuery;
use WireMock\Phpunit\Exception\RequestVerificationException;
use WireMock\Phpunit\Exception\StartException;
use WireMock\Phpunit\Exception\VerifyException;
use WireMock\Client\WireMock;

final class WireMockProxy
{
    /** @var array<callable> */
    public static array $verifyCallbacks = [];
    public static array $scenarios = [];
    public static ?WireMock $wireMock = null;

    public static function startWireMock(
        string $host,
        string $port,
        int $timeout
    ): void {
        if (self::$wireMock !== null) {
            return;
        }

        if ($host === '' || $port === '') {
            throw StartException::missingParameters();
        }

        self::$wireMock = WireMock::create($host, $port);
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

        foreach (self::$scenarios as $scenario) {
            self::$wireMock->resetScenario($scenario);
        }

        WireMockProxy::$verifyCallbacks = [];
    }

    public static function addScenario(string $scenario): void
    {
        if (self::$wireMock === null) {
            return;
        }

        if (!in_array($scenario, self::$scenarios, true)) {
            self::$scenarios[] = $scenario;
        }
    }

    public static function verify(string $test): void
    {
        $thrownExceptions = [];
        $failedStubs = [];

        foreach (WireMockProxy::$verifyCallbacks as $key => $verifyCallback) {
            try {
                $verifyCallback();
                self::cleanStub($key);
            } catch (RequestVerificationException $exception) {
                $thrownExceptions[] = $exception;
                $failedStubs[] = $exception->stubId;
            }
        }

        WireMockProxy::$verifyCallbacks = [];


        if (count($thrownExceptions) > 0) {
            foreach ($failedStubs as $failedStub) {
                unset(WireMockProxy::$verifyCallbacks[$failedStub]);
                self::cleanStub($failedStub);
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

    private static function cleanStub(string $stubId): void
    {
        if (self::$wireMock === null) {
            return;
        }

        try {
            self::$wireMock->getSingleStubMapping($stubId);
        } catch (ClientException $exception) {
            if ($exception->getResponseCode() === 404) {
                return;
            }

            throw $exception;
        }

        self::$wireMock->removeStub($stubId);
        $serveEvents = self::$wireMock->getAllServeEvents(
            (new ServeEventQuery())
                ->withStubMapping($stubId)
        );

        foreach ($serveEvents->getRequests() as $serveEvent) {
            self::$wireMock->removeServeEvent($serveEvent->getId());
        }
    }
}
