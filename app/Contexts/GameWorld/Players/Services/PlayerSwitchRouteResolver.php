<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

/**
 * Resolves a conservative post-switch destination for a newly active Player.
 *
 * The requested path is only a UX hint from the browser. This resolver never
 * grants access: the destination request is still authorized normally under
 * the newly resolved active Player context. Only stable collection-level
 * routes are preserved; resource-specific paths collapse to a safe parent.
 */
final readonly class PlayerSwitchRouteResolver
{
    /** @var list<string> */
    private const PLATFORM_PATHS = [
        '/dashboard',
        '/profile',
    ];

    /** @var list<string> */
    private const PLAYER_PATHS = [
        '/events',
    ];

    /** @var list<string> */
    private const ALLIANCE_PATHS = [
        '/alliance',
        '/alliance/roster',
        '/alliance/kingdom-alliances',
        '/alliance/contributions',
        '/alliance/transfers',
        '/alliance/content',
        '/alliance/integrations',
    ];

    /** @var array<string, string> */
    private const CAPABILITY_PATHS = [
        '/alliance/recruitment' => 'recruitment.manage',
    ];

    /** @var array<string, string> */
    private const SAFE_PARENTS = [
        '/alliance/recruitment/' => '/alliance/recruitment',
        '/alliance/roster/' => '/alliance/roster',
        '/alliance/kingdom-alliances/' => '/alliance/kingdom-alliances',
        '/alliance/contributions/' => '/alliance/contributions',
        '/alliance/transfers/' => '/alliance/transfers',
        '/alliance/content/' => '/alliance/content',
        '/alliance/integrations/' => '/alliance/integrations',
        '/events/' => '/events',
        '/profile/' => '/profile',
    ];

    /**
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    public function resolve(?string $requestedPath, ?array $allianceContext): string
    {
        $path = $this->normalize($requestedPath);
        if ($path === null) {
            return '/dashboard';
        }

        if ($this->allowed($path, $allianceContext)) {
            return $path;
        }

        foreach (self::SAFE_PARENTS as $prefix => $parent) {
            if (str_starts_with($path, $prefix) && $this->allowed($parent, $allianceContext)) {
                return $parent;
            }
        }

        if (str_starts_with($path, '/alliance/') && $allianceContext !== null) {
            return '/alliance';
        }

        return '/dashboard';
    }

    /**
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    private function allowed(string $path, ?array $allianceContext): bool
    {
        if (in_array($path, self::PLATFORM_PATHS, true)) {
            return true;
        }

        if (in_array($path, self::PLAYER_PATHS, true)) {
            return true;
        }

        if (in_array($path, self::ALLIANCE_PATHS, true)) {
            return $allianceContext !== null;
        }

        $requiredCapability = self::CAPABILITY_PATHS[$path] ?? null;
        if ($requiredCapability === null || $allianceContext === null) {
            return false;
        }

        return in_array($requiredCapability, $allianceContext['capabilities'], true);
    }

    private function normalize(?string $requestedPath): ?string
    {
        if (
            $requestedPath === null
            || $requestedPath === ''
            || ! str_starts_with($requestedPath, '/')
            || str_starts_with($requestedPath, '//')
        ) {
            return null;
        }

        $path = parse_url($requestedPath, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $normalized = rtrim($path, '/');

        return $normalized === '' ? '/' : $normalized;
    }
}
