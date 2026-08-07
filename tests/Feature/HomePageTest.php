<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Alliances\Models\Alliance;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HomePageTest extends TestCase
{
    public function test_home_page_exposes_only_non_sensitive_foundation_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Home')
                ->where('application', [
                    'name' => 'Kingshot Alliance',
                ])
                ->where('applicationName', 'Kingshot Alliance'));
    }
}
