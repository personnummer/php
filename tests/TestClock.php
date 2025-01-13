<?php

namespace Personnummer\Tests;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

class TestClock implements ClockInterface
{
    private readonly DateTimeImmutable $time;

    public function __construct(DateTimeImmutable $time = null)
    {
        $this->time = $time;
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }
}
