<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PhaseBoundaryTest extends TestCase
{
    public function test_phase_one_authentication_route_is_not_exposed(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNotFound();
    }
}
