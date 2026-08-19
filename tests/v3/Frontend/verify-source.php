<?php

declare(strict_types=1);
$root = dirname(__DIR__, 3);
$v = [];
$scanRoots = [$root.'/app', $root.'/routes'];
foreach ($scanRoots as $sr) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sr)) as $f) {
        if (! $f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }$c = (string) file_get_contents($f->getPathname());
        preg_match_all('/Inertia::render\(\s*[\'\"]([^\'\"]+)[\'\"]/', $c, $m);
        foreach ($m[1] ?? [] as $page) {
            if (! is_file($root.'/resources/js/pages/'.$page.'.vue')) {
                $v[] = "Missing Inertia page $page referenced by ".str_replace($root.'/', '', $f->getPathname());
            }
        }
    }
}
$source = $root.'/resources/js';
$ext = ['', '.ts', '.vue', '.js', '.mjs', '/index.ts', '/index.vue'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $f) {
    if (! $f->isFile() || ! in_array($f->getExtension(), ['ts', 'vue'], true)) {
        continue;
    }$c = (string) file_get_contents($f->getPathname());
    preg_match_all('/(?:from\s+|import\s*\()\s*[\'\"]([^\'\"]+)[\'\"]/', $c, $m);
    foreach ($m[1] ?? [] as $i) {
        if (! str_starts_with($i, '@/') && ! str_starts_with($i, './') && ! str_starts_with($i, '../')) {
            continue;
        }$base = str_starts_with($i, '@/') ? $source.'/'.substr($i, 2) : dirname($f->getPathname()).'/'.$i;
        $ok = false;
        foreach ($ext as $e) {
            if (is_file($base.$e)) {
                $ok = true;
                break;
            }
        }if (! $ok) {
            $v[] = "Unresolved import $i in ".str_replace($root.'/', '', $f->getPathname());
        }
    }
}
$refs = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $f) {
    if (! $f->isFile() || ! in_array($f->getExtension(), ['ts', 'vue'], true)) {
        continue;
    }$c = (string) file_get_contents($f->getPathname());
    preg_match_all('#/images/kingshot/([A-Za-z0-9._/-]+)#', $c, $m);
    foreach ($m[1] ?? [] as $a) {
        $refs[$a] = 1;
        if (! is_file($root.'/public/images/kingshot/'.$a)) {
            $v[] = "Missing art $a";
        }
    }if (str_contains($c, 'docs/frontend/reference')) {
        $v[] = 'Reference screenshot used at runtime in '.str_replace($root.'/', '', $f->getPathname());
    }
}
foreach ([$root.'/resources/js/pages', $root.'/resources/js/layouts', $root.'/resources/js/components'] as $t) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t)) as $f) {
        if (! $f->isFile() || ! in_array($f->getExtension(), ['vue', 'ts'], true)) {
            continue;
        }if (preg_match('/\b(?:bg|text|border|ring|divide)-(?:slate|sky|blue|white)(?:-|\b)/', (string) file_get_contents($f->getPathname()))) {
            $v[] = 'Legacy SaaS palette in '.str_replace($root.'/', '', $f->getPathname());
        }
    }
}
$vite = (string) file_get_contents($root.'/vite.config.ts');
foreach (['inertia()', 'tailwindcss()', 'vue({', 'laravel({'] as $n) {
    if (! str_contains($vite, $n)) {
        $v[] = "Missing Vite plugin $n";
    }
}
$package = json_decode((string) file_get_contents($root.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
$deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);
foreach (['@inertiajs/vue3' => '^3.', '@inertiajs/vite' => '^3.', 'vue' => '^3.', 'vite' => '^8.', 'tailwindcss' => '^4.', '@tailwindcss/vite' => '^4.', 'typescript' => '^5.'] as $name => $prefix) {
    $version = $deps[$name] ?? '';
    if (! str_starts_with($version, $prefix)) {
        $v[] = "Unexpected frontend dependency $name=$version (expected $prefix...)";
    }
}
$tsconfig = json_decode((string) file_get_contents($root.'/tsconfig.json'), true, 512, JSON_THROW_ON_ERROR);
foreach (['strict', 'noImplicitAny', 'noUncheckedIndexedAccess', 'exactOptionalPropertyTypes'] as $flag) {
    if (($tsconfig['compilerOptions'][$flag] ?? false) !== true) {
        $v[] = "TypeScript flag $flag must be true";
    }
}
if (count($refs) < 6) {
    $v[] = 'Fewer than six standalone Kingshot runtime art assets referenced.';
}
if ($v) {
    fwrite(STDERR, "FRONTEND-V3 source violations:\n - ".implode("\n - ", array_values(array_unique($v)))."\n");
    exit(1);
}fwrite(STDOUT, 'FRONTEND-V3 source gate: PASS ('.count($refs)." runtime art assets referenced)\n");
