<?php

declare(strict_types=1);

namespace Tests\Shared\Testing\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Shared\Testing\Support\JUnitTimingReport;

final class JUnitTimingReportTest extends TestCase
{
    public function test_nested_suite_totals_are_not_double_counted_and_provider_names_are_retained(): void
    {
        $report = JUnitTimingReport::parse('<testsuites><testsuite name="Feature" tests="2"><testsuite name="A" tests="2"><testcase name="test_x with data set &quot;first&quot;" class="A" file="/repo/tests/A.php" time="1.5"/><testcase name="test_y" class="A" file="/repo/tests/A.php" time="0.5"/></testsuite></testsuite></testsuites>');
        self::assertSame(2, $report['counts']['cases']);
        self::assertSame(2.0, $report['seconds']);
        self::assertCount(1, $report['files']);
        self::assertSame('tests/A.php', $report['files'][0]['name']);
        self::assertSame('Feature', $report['suites'][0]['name']);
        self::assertSame('A::test_x with data set "first"', $report['cases'][0]['name']);
        self::assertTrue($report['reconciled']);
        self::assertSame(0, JUnitTimingReport::exitCode($report));
    }

    public function test_failures_errors_skips_and_absent_durations_remain_visible(): void
    {
        $report = JUnitTimingReport::parse('<testsuite name="Unit" tests="3"><testcase name="failed" time="0"><failure/></testcase><testcase name="errored" time="2"><error/></testcase><testcase name="skipped"><skipped/></testcase></testsuite>');
        self::assertSame(['cases' => 3, 'failures' => 1, 'errors' => 1, 'skipped' => 1, 'untimed' => 1], $report['counts']);
        self::assertSame(0.0, $report['cases'][0]['seconds']);
        self::assertNull($report['cases'][2]['seconds']);
        self::assertSame(1, JUnitTimingReport::exitCode($report));
        self::assertStringContainsString('not wall-clock time', JUnitTimingReport::markdown($report));
    }

    public function test_declared_failures_are_not_hidden_when_case_details_have_no_markers(): void
    {
        $report = JUnitTimingReport::parse('<testsuite tests="1" errors="1"><testcase name="partial" time="1"/></testsuite>');
        self::assertSame(1, JUnitTimingReport::exitCode($report));
    }

    public function test_suite_only_skips_are_distinct_from_missing_unexplained_cases(): void
    {
        $report = JUnitTimingReport::parse('<testsuite tests="3" skipped="2"><testcase name="ran" time="1"/></testsuite>');
        self::assertSame(2, $report['suite_only_skips']);
        self::assertTrue($report['reconciled']);
        $incomplete = JUnitTimingReport::parse('<testsuite tests="3" skipped="0"><testcase name="ran" time="1"/></testsuite>');
        self::assertFalse($incomplete['reconciled']);
        self::assertSame(2, JUnitTimingReport::exitCode($incomplete));
    }

    public function test_markdown_escapes_report_content_and_ranks_slow_cases_first(): void
    {
        $report = JUnitTimingReport::parse('<testsuite><testcase name="fast" time="0"/><testcase name="&lt;script&gt;|`slow" time="4"/></testsuite>');
        $markdown = JUnitTimingReport::markdown($report, 1);
        self::assertStringContainsString('&lt;script&gt;&#124;&#96;slow', $markdown);
        self::assertStringNotContainsString('<script>', $markdown);
        self::assertStringNotContainsString('<code>fast</code>', $markdown);
        self::assertNull($report['reconciled']);
    }

    public function test_owner_local_and_legacy_types_use_paths_and_unknown_paths_remain_visible(): void
    {
        $report = JUnitTimingReport::parse('<testsuite><testcase name="owned" file="tests/ReadModels/X/Feature/ATest.php" time="2"/><testcase name="legacy" file="tests/Unit/ATest.php" time="1"/><testcase name="unknown" time="0"/></testsuite>', ['Unit', 'Feature']);
        self::assertSame(['Feature', 'Unit', '[type not identifiable from path]'], array_column($report['types'], 'name'));
        self::assertSame([1, 1, 1], array_column($report['types'], 'cases'));
    }

    public function test_grouped_results_retain_markers_without_allocating_suite_only_skips(): void
    {
        $report = JUnitTimingReport::parse('<testsuite tests="5" failures="1" errors="1" skipped="2"><testcase name="failed" file="tests/Contexts/A/B/Feature/ATest.php" time="3"><failure/></testcase><testcase name="errored" file="tests/Contexts/A/B/Feature/ATest.php" time="2"><error/></testcase><testcase name="skipped" file="tests/Shared/A/Unit/BTest.php"><skipped/></testcase><testcase name="ran" file="tests/Shared/A/Unit/BTest.php" time="1"/></testsuite>', ['Unit', 'Feature']);
        foreach (['files', 'types'] as $group) {
            self::assertSame([1, 0], array_column($report[$group], 'failures'));
            self::assertSame([1, 0], array_column($report[$group], 'errors'));
            self::assertSame([0, 1], array_column($report[$group], 'skipped'));
            self::assertSame([0, 1], array_column($report[$group], 'untimed'));
        }
        self::assertSame(1, $report['suite_only_skips']);
        self::assertSame([], $report['counter_mismatches']);
        self::assertTrue($report['reconciled']);
        self::assertSame(1, JUnitTimingReport::exitCode($report));
        self::assertStringContainsString('Failure markers | Error markers | Skip markers', JUnitTimingReport::markdown($report));
    }

    public function test_a_declared_skip_counter_cannot_hide_observed_skips(): void
    {
        $report = JUnitTimingReport::parse('<testsuite tests="1" skipped="0"><testcase name="skipped"><skipped/></testcase></testsuite>');
        self::assertTrue($report['reconciled']);
        self::assertSame(['skipped'], $report['counter_mismatches']);
        self::assertSame(2, JUnitTimingReport::exitCode($report));
        self::assertStringContainsString('Declared counters below observed markers: **skipped**', JUnitTimingReport::markdown($report));
    }

    #[DataProvider('invalidReports')]
    public function test_invalid_or_empty_reports_fail_instead_of_claiming_success(string $xml): void
    {
        $this->expectException(RuntimeException::class);
        JUnitTimingReport::parse($xml);
    }

    public static function invalidReports(): iterable
    {
        yield 'empty input' => [''];
        yield 'malformed XML' => ['<testsuite>'];
        yield 'wrong root' => ['<report/>'];
        yield 'zero case records' => ['<testsuites/>'];
        yield 'DTD' => ['<!DOCTYPE testsuite [<!ENTITY x "value">]><testsuite/>'];
        yield 'negative duration' => ['<testsuite><testcase name="x" time="-1"/></testsuite>'];
        yield 'non-finite duration' => ['<testsuite><testcase name="x" time="1e999"/></testsuite>'];
        yield 'non-numeric duration' => ['<testsuite><testcase name="x" time="unknown"/></testsuite>'];
        yield 'missing identity' => ['<testsuite><testcase time="1"/></testsuite>'];
        yield 'invalid count' => ['<testsuite tests="-1"><testcase name="x" time="1"/></testsuite>'];
    }
}
