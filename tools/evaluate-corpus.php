<?php

declare(strict_types=1);

/**
 * Regression gate over a verse corpus.
 *
 *   php tools/evaluate-corpus.php [corpus.json] [--json] [--min-top1=1.0] [--min-hemistich=0.9]
 *
 * Exit code 0 when thresholds are met, 1 when below, 2 on bad input.
 */

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        if (str_starts_with($class, 'Arud\\')) {
            $file = $root . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

use Arud\Analyzer;

$args = array_slice($argv, 1);
$opts = [];
$files = [];
foreach ($args as $a) {
    if (str_starts_with($a, '--')) {
        $kv = explode('=', substr($a, 2), 2);
        $opts[$kv[0]] = $kv[1] ?? true;
    } else {
        $files[] = $a;
    }
}
$path = $files[0] ?? $root . '/tests/fixtures/reference-verses.json';
if (!is_file($path)) {
    fwrite(STDERR, "Corpus file not found: {$path}\n");
    exit(2);
}
try {
    $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fwrite(STDERR, 'Invalid corpus JSON: ' . $e->getMessage() . "\n");
    exit(2);
}
$records = $payload['records'] ?? null;
if (!is_array($records) || $records === []) {
    fwrite(STDERR, "Corpus must contain a non-empty records array.\n");
    exit(2);
}

$analyzer = new Analyzer();
$known = array_keys($analyzer->meters());
$rows = [];
$stats = ['verses' => 0, 'top1' => 0, 'high_top1' => 0, 'high_wrong' => 0, 'ambiguous' => 0, 'hemistichs' => 0, 'hemistich_top1' => 0];
$per = [];
$failures = [];

foreach ($records as $r) {
    foreach (['id', 'meter', 'sadr', 'ajuz'] as $k) {
        if (!isset($r[$k]) || !is_string($r[$k]) || $r[$k] === '') {
            fwrite(STDERR, "Record is missing '{$k}'.\n");
            exit(2);
        }
    }
    if (!in_array($r['meter'], $known, true)) {
        fwrite(STDERR, "Record {$r['id']}: unknown meter '{$r['meter']}'.\n");
        exit(2);
    }
    $v = $analyzer->analyzeVerse($r['sadr'], $r['ajuz'], 5);
    $best = $v->bestMatch();
    $ok = $best !== null && $best['key'] === $r['meter'] && in_array($best['match_type'], ['exact', 'compatible'], true);
    $stats['verses']++;
    $per[$r['meter']]['n'] = ($per[$r['meter']]['n'] ?? 0) + 1;
    if ($ok) {
        $stats['top1']++;
        $per[$r['meter']]['ok'] = ($per[$r['meter']]['ok'] ?? 0) + 1;
        if ($v->confidence === 'high') {
            $stats['high_top1']++;
        }
    } else {
        $failures[] = $r['id'];
    }
    if ($v->confidence === 'high' && !$ok) {
        $stats['high_wrong']++;
    }
    if ($best !== null && ($best['ambiguous_with'] ?? []) !== []) {
        $stats['ambiguous']++;
    }
    foreach (['sadr' => $v->sadr, 'ajuz' => $v->ajuz] as $half) {
        $stats['hemistichs']++;
        $hb = $half->bestMatch();
        if ($hb !== null && $hb['key'] === $r['meter'] && in_array($hb['match_type'], ['exact', 'compatible'], true)) {
            $stats['hemistich_top1']++;
        }
    }
    $rows[] = [
        'id' => $r['id'], 'expected' => $r['meter'], 'got' => $best['key'] ?? null,
        'match' => $best['match_type'] ?? null, 'confidence' => $v->confidence,
        'label' => $best['label_ar'] ?? null, 'ok' => $ok,
    ];
}

$top1 = $stats['top1'] / max($stats['verses'], 1);
$hem = $stats['hemistich_top1'] / max($stats['hemistichs'], 1);
$report = [
    'corpus' => $path,
    'verses' => $stats['verses'],
    'meters_covered' => count($per),
    'meters_supported' => count($known),
    'verse_top1_accuracy' => round($top1, 4),
    'hemistich_top1_accuracy' => round($hem, 4),
    'high_confidence_correct' => $stats['high_top1'],
    'high_confidence_wrong' => $stats['high_wrong'],
    'ambiguous_verses' => $stats['ambiguous'],
    'failures' => $failures,
    'per_meter' => $per,
    'caveat' => 'Regression gate on a small unreviewed corpus; not a measure of real-world accuracy.',
];

if (isset($opts['json'])) {
    echo json_encode($report + ['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
} else {
    printf("Verses: %d (%d of %d meters covered)\n", $report['verses'], $report['meters_covered'], $report['meters_supported']);
    printf("Verse top-1: %.1f%%   Hemistich top-1: %.1f%%\n", $top1 * 100, $hem * 100);
    printf("High-confidence correct: %d   wrong: %d   ambiguous: %d\n", $stats['high_top1'], $stats['high_wrong'], $stats['ambiguous']);
    foreach ($rows as $row) {
        printf("  %-4s %-24s expected=%-10s got=%-10s %s/%s\n", $row['ok'] ? 'ok' : 'FAIL', $row['id'], $row['expected'], $row['got'] ?? '-', $row['match'] ?? '-', $row['confidence']);
    }
    echo $report['caveat'], "\n";
}

$minTop1 = isset($opts['min-top1']) ? (float) $opts['min-top1'] : 0.0;
$minHem = isset($opts['min-hemistich']) ? (float) $opts['min-hemistich'] : 0.0;
exit(($top1 + 1e-9 < $minTop1 || $hem + 1e-9 < $minHem || $stats['high_wrong'] > 0) ? 1 : 0);
