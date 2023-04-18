<?php

declare(strict_types=1);

namespace WireMock\Phpunit\Exception;

use Throwable;
use WireMock\Client\FindNearMissesResult;

final class RequestVerificationException extends \Exception
{
    private function __construct(
        string $message,
        public readonly string $stubId,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function verificationFailed(
        string $url,
        string $method,
        FindNearMissesResult $findNearMissesResult,
        string $stubId,
        Throwable|string $exception = null
    ): self {
        $url = str_replace('%', '%%', $url);

        $allNearMisses = [];

        foreach ($findNearMissesResult->getNearMisses() as $nearMiss) {
            $nearMissResult = sprintf(
                '%s %s with actual request body %s and headers %s matches distance %s',
                $method,
                $url,
                $nearMiss->getRequest()->getBody(),
                implode(',', $nearMiss->getRequest()->getHeaders()),
                $nearMiss->getMatchResult()->getDistance(),
            );

            if ($nearMiss->getMapping() !== null) {
                $nearMissResult .= sprintf(
                    ' (stub id: %s)',
                    $nearMiss->getMapping()->getId()
                );
            }
            $allNearMisses[] = $nearMissResult;
        }

        $message = sprintf(
            "Failed to verify interactions for path %s and method %s (stub %s). Reason: %s. For more check wiremock logs.",
            $url,
            $method,
            $stubId,
            $exception instanceof Throwable ? $exception->getMessage() : $exception
        );

        if ($allNearMisses !== []) {
            $message .= ' Near misses:' . PHP_EOL . implode(PHP_EOL, $allNearMisses);
        }

        return new self(
            $message,
            $stubId,
            $exception instanceof Throwable ? $exception : null,
        );
    }

    public static function clientException(
        string $url,
        string $method,
        Throwable $exception,
        string $stubId
    ): self {
        return new self(
            "Request to path $url and method $method failed due to: {$exception->getMessage()}",
            $stubId,
            $exception,
        );
    }
}
