<?php

declare(strict_types=1);

namespace WireMock\Phpunit\Dto;

use Closure;
use DateTime;

final class Stub
{
    public function __construct(
        public readonly string $id,
        public readonly Closure $verificationCallback,
        public readonly bool $reset,
        public readonly DateTime $createdAt
    ) {
    }
}
