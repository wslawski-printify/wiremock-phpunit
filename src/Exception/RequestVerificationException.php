<?php

declare(strict_types=1);

namespace WireMock\Phpunit\Exception;

final class RequestVerificationException extends \Exception
{
    private function __construct(
        string $message,
        \Throwable $previous,
        public readonly string $stubId
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function verificationFailed(
        string $url,
        string $method,
        \Throwable $wireMockException,
        string $stubId
    ): self {
        $url = str_replace('%', '%%', $url);

        return new self(
            sprintf(
                "Failed to verify interactions for path $url and method $method due to: %s. For more check wiremock logs.",
                $wireMockException->getMessage() . PHP_EOL
            ),
            $wireMockException,
            $stubId
        );
    }

    public static function clientException(
        string $url,
        string $method,
        \Throwable $exception,
        string $stubId
    ): self {
        return new self(
            "Request to path $url and method $method failed due to: {$exception->getMessage()}e",
            $exception,
            $stubId
        );
    }
}
