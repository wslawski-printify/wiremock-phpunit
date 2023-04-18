<?php

declare(strict_types=1);

namespace WireMock\Phpunit\Extension\Subscriber;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use WireMock\Phpunit\WireMockProxy;

final class Reset implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        WireMockProxy::reset();
    }
}
