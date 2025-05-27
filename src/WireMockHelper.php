<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

use DateTime;
use Ramsey\Uuid\Uuid;
use WireMock\Client\ServeEventQuery;
use WireMock\Stubbing\StubImport;
use WireMock\Stubbing\StubImportOptions;
use WireMock\Stubbing\StubMapping;

/**
 * @internal
 */
final class WireMockHelper
{
    public static function stubId(StubMapping|string $stub): string
    {
        $originStubId = $stub instanceof StubMapping ? $stub->getId() : $stub;

        if (WireMockProxy::$testToken === null) {
            return $originStubId;
        }

        if (isset(WireMockContext::$stubTestTokensMappings[$originStubId][WireMockProxy::$testToken])) {
            return WireMockContext::$stubTestTokensMappings[$originStubId][WireMockProxy::$testToken];
        }

        if (!$stub instanceof StubMapping) {
            $stub = WireMockProxy::instance()->getSingleStubMapping($originStubId);
        }

        $newStub = clone $stub;
        $newStubId = Uuid::uuid4()->toString();
        $newStub->setId($newStubId);
        WireMockProxy::instance()->importStubs(new StubImport([$newStub], new StubImportOptions(
            StubImportOptions::IGNORE,
            false
        )));
        WireMockContext::$stubTestTokensMappings[$originStubId][WireMockProxy::$testToken] = $newStubId;

        return $newStubId;
    }

    public static function serveEventsStubCount(string $stubId, ?DateTime $since = null): int
    {
        $query = (new ServeEventQuery())
            ->withStubMapping($stubId);

        if ($since !== null) {
            $query->withSince($since);
        }

        $serveEvents = WireMockProxy::instance()->getAllServeEvents($query);

        return count($serveEvents->getRequests());
    }

    public static function appendTestToken(string $value): string
    {
        $testToken = WireMockProxy::$testToken;

        if ($testToken !== null) {
            return sprintf('%s_%s', $testToken, $value);
        }

        return $value;
    }
}
