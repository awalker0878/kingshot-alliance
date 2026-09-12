<?php

declare(strict_types=1);

namespace Tests\Shared\Testing\Support;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use RuntimeException;

/** Reads existing result data only; never discovers or executes tests. */
final class JUnitTimingReport
{
    /**
     * @param  list<string>  $suiteNames  Execution names from the repository layout, not another test registry.
     * @return array<string, mixed>
     */
    public static function parse(string $xml, array $suiteNames = []): array
    {
        if (trim($xml) === '' || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $xml)) {
            throw new RuntimeException('Empty reports and XML document/entity declarations are not supported.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new RuntimeException('Cannot parse the JUnit XML report.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($document->doctype !== null) {
            throw new RuntimeException('XML document declarations are not supported.');
        }
        $root = $document->documentElement;
        if (! $root instanceof DOMElement || ! in_array($root->tagName, ['testsuites', 'testsuite'], true)) {
            throw new RuntimeException('Expected a testsuites or testsuite document.');
        }

        $cases = [];
        $counts = ['cases' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0, 'untimed' => 0];
        foreach ($root->getElementsByTagName('testcase') as $case) {
            $name = $case->getAttribute('name');
            if ($name === '') {
                throw new RuntimeException('A reported testcase has no name.');
            }
            $time = $case->getAttribute('time');
            $seconds = $time === '' ? null : (is_numeric($time) ? (float) $time : NAN);
            if ($seconds !== null && (! is_finite($seconds) || $seconds < 0)) {
                throw new RuntimeException('A testcase contains an invalid duration.');
            }
            $suite = '[suite not reported]';
            $file = $case->getAttribute('file');
            for ($parent = $case->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                if ($parent->tagName !== 'testsuite') {
                    continue;
                }
                if ($parent->getAttribute('name') !== '') {
                    $suite = $parent->getAttribute('name');
                }
                if ($file === '') {
                    $file = $parent->getAttribute('file');
                }
            }
            $class = $case->getAttribute('class') ?: $case->getAttribute('classname');
            $file = str_replace('\\', '/', $file);
            $offset = strpos($file, '/tests/');
            if ($offset !== false) {
                $file = substr($file, $offset + 1);
            }
            $types = array_values(array_intersect(explode('/', $file), $suiteNames));
            $type = count($types) === 1 ? $types[0] : '[type not identifiable from path]';
            $row = ['type' => $type, 'name' => $class === '' ? $name : $class.'::'.$name, 'file' => $file ?: '[file not reported]', 'suite' => $suite, 'seconds' => $seconds];
            foreach (['failures' => 'failure', 'errors' => 'error', 'skipped' => 'skipped'] as $key => $element) {
                $row[$key] = $case->getElementsByTagName($element)->length;
                $counts[$key] += $row[$key];
            }
            $counts['cases']++;
            $counts['untimed'] += (int) ($seconds === null);
            $cases[] = $row;
        }
        if ($cases === []) {
            throw new RuntimeException('The JUnit report contains no testcase records.');
        }

        $declared = [];
        $totals = $root->tagName === 'testsuite' || $root->hasAttribute('tests') ? [$root] : iterator_to_array($root->childNodes);
        $totals = array_values(array_filter($totals, static fn ($node): bool => $node instanceof DOMElement && in_array($node->tagName, ['testsuite', 'testsuites'], true)));
        foreach (['tests', 'failures', 'errors', 'skipped'] as $key) {
            $declared[$key] = 0;
            foreach ($totals as $total) {
                if (! $total->hasAttribute($key)) {
                    $declared[$key] = null;
                    break;
                }
                $number = filter_var($total->getAttribute($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($number === false) {
                    throw new RuntimeException('A suite contains an invalid declared counter.');
                }
                $declared[$key] += $number;
            }
        }
        $counterMismatches = [];
        foreach (['failures', 'errors', 'skipped'] as $key) {
            if ($declared[$key] !== null && $declared[$key] < $counts[$key]) {
                $counterMismatches[] = $key;
            }
        }
        // PHPUnit can report an entirely skipped class at suite level without case nodes.
        $suiteOnlySkips = max(0, ($declared['skipped'] ?? 0) - $counts['skipped']);
        $reconciled = $declared['tests'] === null ? null : $declared['tests'] === $counts['cases'] + $suiteOnlySkips;
        $seconds = array_sum(array_map(static fn (array $case): float => $case['seconds'] ?? 0.0, $cases));
        if (! is_finite($seconds)) {
            throw new RuntimeException('Aggregate testcase duration is not finite.');
        }

        return ['counts' => $counts, 'declared' => $declared, 'counter_mismatches' => $counterMismatches, 'suite_only_skips' => $suiteOnlySkips, 'reconciled' => $reconciled,
            'seconds' => $seconds, 'cases' => $cases, 'files' => self::group($cases, 'file'), 'suites' => self::group($cases, 'suite'), 'types' => self::group($cases, 'type')];
    }

    /** @param array<string, mixed> $report */
    public static function exitCode(array $report): int
    {
        if ($report['reconciled'] === false) {
            return 2;
        }
        foreach (['failures', 'errors'] as $key) {
            if ($report['counts'][$key] > 0 || ($report['declared'][$key] ?? 0) > 0) {
                return 1;
            }
        }

        return $report['counter_mismatches'] === [] ? 0 : 2;
    }

    /** @param array<string, mixed> $report */
    public static function markdown(array $report, int $limit = 15): string
    {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('The row limit must be between 1 and 100.');
        }
        $counts = $report['counts'];
        $lines = ['## Recorded PHPUnit timings', '',
            sprintf('Case records: **%d**; failure markers: **%d**; error markers: **%d**; case-level skips: **%d**.', $counts['cases'], $counts['failures'], $counts['errors'], $counts['skipped']),
            sprintf('Known aggregate case duration: **%.6f s**; missing case durations: **%d**. This is not wall-clock time.', $report['seconds'], $counts['untimed']),
            'Declared suite total: **'.($report['declared']['tests'] ?? 'not supplied').'**; suite-level skips without case identities: **'.$report['suite_only_skips'].'**.',
            'Declared failure/error/skip counters: **'.($report['declared']['failures'] ?? 'unknown').' / '.($report['declared']['errors'] ?? 'unknown').' / '.($report['declared']['skipped'] ?? 'unknown').'**.',
            'Declared counters below observed markers: **'.($report['counter_mismatches'] === [] ? 'none' : implode(', ', $report['counter_mismatches'])).'**.',
            'Case-count reconciliation: **'.match ($report['reconciled']) {
                true => 'matched', false => 'MISMATCH — incomplete or inconsistent report', null => 'unknown — total not supplied'
            }.'**.',
            '', 'Only existing XML records are summarized. This does not establish full discovery, passing regression, setup cost or a speedup.',
            'Grouped result markers describe case records only; suite-only skips are not assigned to files or execution types.', ''];
        foreach (['types' => 'Execution types (from file paths)', 'files' => 'Slowest files'] as $key => $title) {
            $lines[] = '### '.$title;
            $lines[] = '| Name | Cases | Known aggregate seconds | Untimed | Failure markers | Error markers | Skip markers |';
            $lines[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: |';
            $rows = $key === 'types' ? $report[$key] : array_slice($report[$key], 0, $limit);
            foreach ($rows as $row) {
                $lines[] = '| '.self::cell($row['name']).' | '.$row['cases'].' | '.sprintf('%.6f', $row['seconds']).' | '.$row['untimed'].' | '.$row['failures'].' | '.$row['errors'].' | '.$row['skipped'].' |';
            }
            $lines[] = '';
        }
        $lines[] = '### Slowest case records';
        $lines[] = '| Case, including provider label | Seconds | Result markers |';
        $lines[] = '| --- | ---: | --- |';
        $cases = $report['cases'];
        usort($cases, static fn (array $a, array $b): int => ($b['seconds'] ?? -1) <=> ($a['seconds'] ?? -1) ?: strcmp($a['name'], $b['name']));
        foreach (array_slice($cases, 0, $limit) as $case) {
            $markers = array_keys(array_filter(['error' => $case['errors'], 'failure' => $case['failures'], 'skipped' => $case['skipped']]));
            $status = $markers === [] ? 'none' : implode(', ', $markers);
            $lines[] = '| '.self::cell($case['name']).' | '.($case['seconds'] === null ? 'not reported' : sprintf('%.6f', $case['seconds'])).' | '.$status.' |';
        }

        return implode("\n", $lines)."\n";
    }

    /** @param list<array<string, mixed>> $cases
     * @return list<array<string, mixed>>
     */
    private static function group(array $cases, string $key): array
    {
        $groups = [];
        foreach ($cases as $case) {
            $name = $case[$key];
            $groups[$name] ??= ['name' => $name, 'cases' => 0, 'seconds' => 0.0, 'untimed' => 0, 'failures' => 0, 'errors' => 0, 'skipped' => 0];
            $groups[$name]['cases']++;
            $groups[$name]['seconds'] += $case['seconds'] ?? 0;
            $groups[$name]['untimed'] += (int) ($case['seconds'] === null);
            foreach (['failures', 'errors', 'skipped'] as $result) {
                $groups[$name][$result] += $case[$result];
            }
        }
        $groups = array_values($groups);
        usort($groups, static fn (array $a, array $b): int => $b['seconds'] <=> $a['seconds'] ?: strcmp($a['name'], $b['name']));

        return $groups;
    }

    private static function cell(string $text): string
    {
        $text = preg_replace('/[\x00-\x20]+/', ' ', $text) ?? '';

        return '<code>'.str_replace(['|', '`'], ['&#124;', '&#96;'], htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</code>';
    }
}
