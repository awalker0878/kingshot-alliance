<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Services;

/**
 * One presentation registry for Governor-context navigation and post-switch routing.
 *
 * This registry shapes UX only. Destination requests and every mutation remain
 * independently authorized by their owning context.
 */
final readonly class GameRouteRegistry
{
    /**
     * @var list<array{
     *     path:string,
     *     scope:'platform'|'governor'|'alliance',
     *     capability:?string,
     *     navigation:bool,
     *     key:?string,
     *     icon:?string,
     *     exact:bool
     * }>
     */
    private const DEFINITIONS = [
        ['path' => '/dashboard', 'scope' => 'platform', 'capability' => null, 'navigation' => true, 'key' => 'dashboard', 'icon' => 'dashboard', 'exact' => true],
        ['path' => '/profile', 'scope' => 'platform', 'capability' => null, 'navigation' => false, 'key' => null, 'icon' => null, 'exact' => true],
        ['path' => '/events', 'scope' => 'governor', 'capability' => null, 'navigation' => true, 'key' => 'events', 'icon' => 'events', 'exact' => false],
        ['path' => '/alliance', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'alliance', 'icon' => 'alliance', 'exact' => true],
        ['path' => '/alliance/recruitment', 'scope' => 'alliance', 'capability' => 'recruitment.manage', 'navigation' => true, 'key' => 'recruitment', 'icon' => 'recruitment', 'exact' => false],
        ['path' => '/alliance/kingdom-alliances', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'kingdom', 'icon' => 'kingdom', 'exact' => false],
        ['path' => '/alliance/roster', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'roster', 'icon' => 'roster', 'exact' => false],
        ['path' => '/alliance/contributions', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'contributions', 'icon' => 'contributions', 'exact' => false],
        ['path' => '/alliance/transfers', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'transfers', 'icon' => 'transfers', 'exact' => false],
        ['path' => '/alliance/content', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'content', 'icon' => 'content', 'exact' => false],
        ['path' => '/alliance/integrations', 'scope' => 'alliance', 'capability' => null, 'navigation' => true, 'key' => 'integrations', 'icon' => 'integrations', 'exact' => false],
    ];

    /**
     * @param  array{capabilities:list<string>}|null  $allianceContext
     * @return list<array{key:string,href:string,icon:string,exact:bool}>
     */
    public function navigation(bool $hasGovernor, ?array $allianceContext): array
    {
        $items = [];

        foreach (self::DEFINITIONS as $definition) {
            if (! $definition['navigation'] || ! $this->allowed($definition, $hasGovernor, $allianceContext)) {
                continue;
            }

            $key = $definition['key'];
            $icon = $definition['icon'];
            if (! is_string($key) || ! is_string($icon)) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'href' => $definition['path'],
                'icon' => $icon,
                'exact' => $definition['exact'],
            ];
        }

        return $items;
    }

    /**
     * The requested path is a non-authoritative UX hint. Resource routes collapse
     * to the longest permitted registered parent under the target Governor.
     *
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    public function resolveSwitchDestination(?string $requestedPath, ?array $allianceContext): string
    {
        $path = $this->normalize($requestedPath);
        if ($path === null) {
            return '/dashboard';
        }

        $definitions = self::DEFINITIONS;
        usort(
            $definitions,
            static fn (array $left, array $right): int => strlen($right['path']) <=> strlen($left['path']),
        );

        foreach ($definitions as $definition) {
            if (! $this->allowed($definition, true, $allianceContext)) {
                continue;
            }

            $base = $definition['path'];
            if ($path === $base) {
                return $base;
            }

            if (str_starts_with($path, $base.'/')) {
                return $base;
            }
        }

        if (str_starts_with($path, '/alliance/') && $allianceContext !== null) {
            return '/alliance';
        }

        return '/dashboard';
    }

    /**
     * @param  array{scope:string,capability:?string}  $definition
     * @param  array{capabilities:list<string>}|null  $allianceContext
     */
    private function allowed(array $definition, bool $hasGovernor, ?array $allianceContext): bool
    {
        if ($definition['scope'] === 'platform') {
            return true;
        }

        if ($definition['scope'] === 'governor') {
            return $hasGovernor;
        }

        if (! $hasGovernor || $allianceContext === null) {
            return false;
        }

        $required = $definition['capability'];

        return $required === null || in_array($required, $allianceContext['capabilities'], true);
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
