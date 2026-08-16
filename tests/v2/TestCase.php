<?php

declare(strict_types=1);

namespace Tests\v2;

use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

abstract class TestCase extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
