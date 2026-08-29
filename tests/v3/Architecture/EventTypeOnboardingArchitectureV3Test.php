<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use Tests\v3\TestCase;

final class EventTypeOnboardingArchitectureV3Test extends TestCase
{
    public function test_verified_event_onboarding_uses_catalogue_resolver_and_typed_workflow_guard(): void
    {
        $catalogue = $this->source('app/Contexts/Operations/Events/Catalog/KingShotEventTypeCatalog.php');
        $resolver = $this->source('app/Contexts/Operations/Events/Services/EventTypeProfileResolver.php');
        $guard = $this->source('app/Contexts/Operations/Events/Services/EventWorkflowGuard.php');

        self::assertStringContainsString('verification_state', $catalogue);
        self::assertStringContainsString('profile_state', $catalogue);
        self::assertStringContainsString('workflow_dimensions', $catalogue);
        self::assertStringContainsString("'profile_enabled'", $resolver);
        self::assertStringContainsString("'workflow_dimensions'", $resolver);
        self::assertStringContainsString('EventWorkflowDimension $dimension', $guard);
        self::assertStringContainsString('supportsWorkflow', $guard);
        self::assertStringNotContainsString('EventCapability', $guard);
    }

    public function test_generic_event_management_adapter_has_no_name_or_category_branching(): void
    {
        $source = $this->source('app/ReadModels/EventManagement/Http/Controllers/EventManagementPageController.php');

        self::assertStringContainsString('EventWorkflowDimension::', $source);
        self::assertStringNotContainsString('eventType->slug', $source);
        self::assertStringNotContainsString('eventType->category', $source);
        self::assertStringNotContainsString('EventCapability', $source);
    }

    public function test_legacy_mutable_scope_and_poll_materializer_contracts_are_absent(): void
    {
        self::assertFileDoesNotExist(base_path('app/Contexts/Operations/Events/Actions/ConfigureEventTypeScope.php'));
        self::assertFileDoesNotExist(base_path('app/Contexts/Platform/EventAdministration/Actions/UpdateEventTypeScope.php'));
        self::assertFileDoesNotExist(base_path('app/Contexts/Operations/Polls/Services/EventPollTemplateMaterializer.php'));

        $routes = $this->source('routes/platform.php');
        self::assertStringNotContainsString('EventTypeAdministrationController', $routes);
        self::assertStringNotContainsString("Route::patch('/event-types", $routes);
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(base_path($relativePath));
        self::assertIsString($source);

        return $source;
    }
}
