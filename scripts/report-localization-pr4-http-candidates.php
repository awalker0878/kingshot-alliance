<?php

declare(strict_types=1);

// Diagnostic only. Does not alter middleware policy, current normalization
// expectations, or deployment eligibility.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Shared\Infrastructure\Http\SupportedLocale;
use Illuminate\Http\Request;

$fixture = json_decode(file_get_contents(dirname(__DIR__).'/resources/data/localization/locale-normalization-cases.json'), true, 512, JSON_THROW_ON_ERROR);
$shorten = static function (?string $tag): string {
    if ($tag === null) {
        return '(null)';
    }

    return strlen($tag) <= 240 ? $tag : substr($tag, 0, 55).'…'.substr($tag, -35).' [bytes='.strlen($tag).']';
};
$probe = static function (string $raw, string $label) use ($shorten): array {
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => $raw]);
    $languages = $request->getLanguages();
    $candidateResults = [];
    foreach ($languages as $tag) {
        $candidateResults[] = [
            'receivedByNormalizer' => $tag,
            'receivedBytes' => strlen($tag),
            'normalized' => SupportedLocale::normalize($tag),
        ];
    }

    return [
        'label' => $label,
        'rawHeader' => $raw,
        'rawHeaderBytes' => strlen($raw),
        'getLanguages' => $languages,
        'normalizerInputsAndResults' => $candidateResults,
        'selected' => SupportedLocale::firstSupported($languages),
        'standaloneNormalization' => SupportedLocale::normalize($raw),
        'summary' => $shorten($raw),
    ];
};

$headerResults = [];
foreach ($fixture['malformedHeaders'] as $case) {
    $headerResults[] = $probe($case['header'], $case['description']);
}
foreach ($fixture['headers'] as $case) {
    $headerResults[] = $probe($case['header'], 'weighted-header-example');
}
$cases = [];
$differences = [];
foreach ($fixture['cases'] as $index => $case) {
    $input = $case['input'];
    try {
        $result = $probe($input ?? '', 'fixture-'.$index);
        $standalone = SupportedLocale::normalize($input);
        $expectedIfStandaloneIsOnlyCandidate = $standalone ?? 'en';
        $row = [
            'index' => $index,
            'input' => $input,
            'inputBytes' => is_string($input) ? strlen($input) : null,
            'standalone' => $standalone,
            'standaloneWithFallback' => $expectedIfStandaloneIsOnlyCandidate,
            'getLanguages' => $result['getLanguages'],
            'normalizerInputsAndResults' => $result['normalizerInputsAndResults'],
            'headerSelected' => $result['selected'],
            'differsFromStandaloneWithFallback' => $result['selected'] !== $expectedIfStandaloneIsOnlyCandidate,
        ];
        $cases[] = $row;
        if ($row['differsFromStandaloneWithFallback']) {
            $differences[] = $row;
        }
    } catch (Throwable $exception) {
        $cases[] = [
            'index' => $index,
            'input' => $input,
            'exceptionType' => get_class($exception),
            'exceptionMessage' => $exception->getMessage(),
        ];
    }
}
$report = [
    'purpose' => 'Diagnostic: Symfony/Laravel Accept-Language parsed candidates and strict SupportedLocale results. No behavioral policy decision.',
    'headSha' => getenv('GITHUB_SHA') ?: 'local',
    'phpVersion' => PHP_VERSION,
    'fixtureCount' => count($cases),
    'headerSelectedVsStandaloneFallbackDifferences' => count($differences),
    'probeCases' => $cases,
    'probeDifferences' => $differences,
    'rawHeaderCases' => $headerResults,
];
file_put_contents('localization-pr4-http-candidate-diagnostics.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
echo 'PR4 HTTP BOUNDARY DIAGNOSTIC: '.json_encode([
    'fixtureCount' => count($cases),
    'headerVsStandaloneDifferences' => count($differences),
], JSON_THROW_ON_ERROR).PHP_EOL;
foreach ($headerResults as $row) {
    echo 'PR4 RAW HEADER: '.json_encode([
        'case' => $row['label'],
        'rawBytes' => $row['rawHeaderBytes'],
        'getLanguages' => $row['getLanguages'],
        'normalizerInputsAndResults' => $row['normalizerInputsAndResults'],
        'selected' => $row['selected'],
        'standalone' => $row['standaloneNormalization'],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
}
foreach ($differences as $row) {
    echo 'PR4 HEADER VS STANDALONE: '.json_encode([
        'index' => $row['index'], 'input' => $shorten($row['input']),
        'getLanguages' => $row['getLanguages'], 'headerSelected' => $row['headerSelected'],
        'standaloneWithFallback' => $row['standaloneWithFallback'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
}
