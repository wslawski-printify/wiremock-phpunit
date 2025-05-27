<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

/**
 * @internal
 */
final class WireMockContext
{
    /** @var array<string, array<string, string>> */
    public static array $stubTestTokensMappings = [];

    /** @var array<string, int> */
    public static array $stubServedRequestCount = [];

    public static array $scenarios = [];
}
