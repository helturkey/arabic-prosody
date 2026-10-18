<?php

declare(strict_types=1);

namespace Arud;

/**
 * Arabic prosody analyzer.
 *
 * Inputs: one hemistich (shatr), one verse (bayt: array [sadr, ajuz] or two
 * arguments), a whole poem (array of pairs / multi-line string), or free-verse lines.
 */
final class Analyzer
{
    private const SIDES = ['either', 'sadr', 'ajuz'];
    private const MAX_APPROX_DISTANCE = 8;

    /** @var array<string,array{0:string,1:list<string>,2:list<string>}> foot, inner variants, last-foot variants */
    private const FREE_VERSE = [
        'mutaqarib' => ['faulun', ['qabd', 'thalm'], ['qabd', 'hadhf', 'qasr', 'batr']],
        'mutadarik' => ['failun', ['khabn', 'qat'], ['khabn', 'qat', 'tadhyil', 'khabn_qat']],
        'rajaz' => ['mustafilun', ['khabn', 'tay', 'khabal'], ['khabn', 'tay', 'khabal', 'qat', 'khabn_qat', 'tadhyil']],
        'kamil' => ['mutafailun', ['idmar', 'waqs', 'khazl'], ['idmar', 'qat', 'idmar_qat', 'hadhadh', 'tadhyil']],
        'ramal' => ['failatun', ['khabn', 'kaff', 'shakl'], ['khabn', 'kaff', 'shakl', 'hadhf', 'hadhf_khabn', 'qasr', 'batr', 'tasbigh']],
        'hazaj' => ['mafailun', ['qabd', 'kaff', 'kharm'], ['qabd', 'kaff', 'hadhf', 'qasr']],
        'wafir' => ['mafaalatun', ['aql', 'asb', 'naqs'], ['aql', 'asb', 'naqs', 'qataf']],
    ];

    public function __construct(
        private readonly bool $includeRareForms = true,
        private readonly bool $allowApproximate = true,
    ) {
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /** @return array<string,array<string,mixed>> */
    public function meters(): array
    {
        return MeterCatalog::all();
    }

    public function normalize(string $text): string
    {
        return ArabicText::normalize($text);
    }

    public function prosodicSpelling(string $text): string
    {
        return ArabicText::prosodicSpelling($text);
    }

    /** @return array{pattern:string, coverage:float, letter_count:int, inferred_count:int, warnings:list<string>} */
    public function scan(string $text, bool $inferUnmarked = false, bool $saturateEnd = true): array
    {
        return ArabicText::scan($text, $inferUnmarked, $saturateEnd);
    }

    /**
     * Rank an already-computed 1/0/? pattern (useful for tests and tooling).
     *
     * @return list<array<string,mixed>>
     */
    public function rankPattern(string $pattern, int $limit = 5, string $side = 'either'): array
    {
        $this->assertSide($side);
        if (preg_match('/^[01?]+$/', $pattern) !== 1) {
            throw new \InvalidArgumentException('Pattern must contain only 1, 0 and ?.');
        }

        return array_slice($this->rankHemistich($pattern, $side), 0, $this->clampLimit($limit));
    }

    /** Analyze one hemistich. */
    public function analyze(
        string $hemistich,
        int $limit = 5,
        bool $inferUnmarked = false,
        string $side = 'either',
        bool $saturateEnd = true,
    ): AnalysisResult {
        $this->assertSide($side);
        $this->assertHemistich($hemistich);
        $limit = $this->clampLimit($limit);

        $scan = ArabicText::scan($hemistich, $inferUnmarked, $saturateEnd);
        $pattern = $scan['pattern'];
        $all = $pattern === '' ? [] : $this->rankHemistich($pattern, $side);
        $candidates = array_slice($all, 0, $limit);
        $confidence = $this->confidenceFor($all, $scan['coverage']);
        $warnings = $scan['warnings'];
        $this->addCandidateWarnings($warnings, $all);

        return new AnalysisResult(
            $hemistich,
            ArabicText::normalize($hemistich),
            ArabicText::prosodicSpelling($hemistich),
            $pattern,
            $scan['coverage'],
            $scan['inferred_count'],
            $candidates,
            $confidence,
            array_values(array_unique($warnings)),
            $side,
        );
    }

    /**
     * Analyze one verse. Accepts (sadr, ajuz), or a single array: [sadr, ajuz] / ['sadr'=>…, 'ajuz'=>…].
     *
     * @param string|array<mixed> $sadr
     */
    public function analyzeVerse(
        string|array $sadr,
        ?string $ajuz = null,
        int $limit = 5,
        bool $inferUnmarked = false,
        bool $saturateEnd = true,
    ): VerseAnalysis {
        $parsed = InputNormalizer::parse($sadr, $ajuz);
        if ($parsed['kind'] !== 'bayt') {
            throw new \InvalidArgumentException('analyzeVerse() expects exactly one verse (sadr + ajuz); use analyzePoem() or analyzeAuto().');
        }

        return $this->verse($parsed['verses'][0]['sadr'], $parsed['verses'][0]['ajuz'], $this->clampLimit($limit), $inferUnmarked, $saturateEnd);
    }

    /**
     * Analyze a poem: array of [sadr, ajuz] pairs, a flat even list of hemistichs,
     * ['verses' => …], or a multi-line string.
     *
     * @param string|array<mixed> $verses
     */
    public function analyzePoem(string|array $verses, int $limit = 5, bool $inferUnmarked = false, bool $saturateEnd = true): PoemAnalysis
    {
        $parsed = InputNormalizer::parse($verses);
        if ($parsed['kind'] === 'shatr') {
            throw new \InvalidArgumentException('A poem needs at least one complete verse (sadr + ajuz).');
        }

        $results = [];
        foreach ($parsed['verses'] as $pair) {
            $results[] = $this->verse($pair['sadr'], $pair['ajuz'], 16, $inferUnmarked, $saturateEnd);
        }

        return $this->summarizePoem($results, $this->clampLimit($limit));
    }

    /**
     * Detect the input shape and analyze accordingly.
     *
     * @param string|array<mixed> $input
     */
    public function analyzeAuto(string|array $input, int $limit = 5, bool $inferUnmarked = false, bool $saturateEnd = true): AnalysisResult|VerseAnalysis|PoemAnalysis
    {
        $parsed = InputNormalizer::parse($input);

        return match ($parsed['kind']) {
            'shatr' => $this->analyze((string) $parsed['shatr'], $limit, $inferUnmarked, 'either', $saturateEnd),
            'bayt' => $this->verse($parsed['verses'][0]['sadr'], $parsed['verses'][0]['ajuz'], $this->clampLimit($limit), $inferUnmarked, $saturateEnd),
            default => $this->analyzePoem($input, $limit, $inferUnmarked, $saturateEnd),
        };
    }

    /**
     * Meter key for any input shape, or null when there is no trustworthy answer.
     *
     * @param string|array<mixed> $input
     */
    public function detectMeter(string|array $input, bool $inferUnmarked = false, bool $allowLowConfidence = false): ?string
    {
        $result = $this->analyzeAuto($input, 5, $inferUnmarked);
        if ($result instanceof PoemAnalysis) {
            return $result->dominantMeter !== null && ($allowLowConfidence || $result->confidence !== 'low')
                ? $result->dominantMeter : null;
        }
        $best = $result->bestMatch();
        if ($best === null || !in_array($best['match_type'], ['exact', 'compatible'], true)) {
            return null;
        }

        return $allowLowConfidence || $result->confidence !== 'low' ? $best['key'] : null;
    }

    /** @param string|array<mixed> $sadr */
    public function detectVerseMeter(string|array $sadr, ?string $ajuz = null, bool $inferUnmarked = false): ?string
    {
        return $this->detectMeter($ajuz === null ? $sadr : [(string) $sadr, $ajuz], $inferUnmarked);
    }

    /**
     * Free-verse (shiʿr al-tafʿīla) line: one foot family repeated any number of times.
     */
    public function analyzeFreeVerse(string $line, int $limit = 5, bool $inferUnmarked = false, bool $saturateEnd = true): AnalysisResult
    {
        $this->assertHemistich($line);
        $scan = ArabicText::scan($line, $inferUnmarked, $saturateEnd);
        $pattern = $scan['pattern'];
        $candidates = [];
        if ($pattern !== '') {
            foreach (self::FREE_VERSE as $key => [$foot, $inner, $last]) {
                $parse = Matcher::parseRepeated($pattern, FootCatalog::options($foot, $inner), FootCatalog::options($foot, $last));
                if ($parse === null) {
                    continue;
                }
                $path = [];
                $count = count($parse['path']);
                foreach ($parse['path'] as $i => $option) {
                    $option['index'] = $i + 1;
                    $option['position'] = $i === $count - 1 ? 'darb' : 'hashw';
                    $path[] = $option;
                }
                $meter = MeterCatalog::get($key);
                $candidates[] = [
                    'key' => $key,
                    'name_ar' => $meter['name_ar'],
                    'name_en' => $meter['name_en'],
                    'form_id' => 'free:' . $key,
                    'form_type' => 'free',
                    'shape' => 'free',
                    'label_ar' => 'تفعيلة ' . $meter['name_ar'],
                    'tier' => 'classical',
                    'match_type' => str_contains($pattern, '?') ? 'compatible' : 'exact',
                    'score' => 1.0,
                    'distance' => 0,
                    'expected_pattern' => implode('', array_column($path, 'pattern')),
                    'feet' => array_map(static fn (array $o): array => Matcher::footRow($o), $path),
                    'modifications' => $this->modifications($path),
                    'cost' => $parse['cost'],
                    'foot_count' => $count,
                ];
            }
        }
        usort($candidates, fn (array $a, array $b): int => $this->compare($a, $b));
        $confidence = $this->confidenceFor($candidates, $scan['coverage']);
        $warnings = $scan['warnings'];
        $warnings[] = 'Free-verse analysis checks only that the line decomposes into one repeated foot family; it does not validate a full meter.';
        $this->addCandidateWarnings($warnings, $candidates);

        return new AnalysisResult(
            $line,
            ArabicText::normalize($line),
            ArabicText::prosodicSpelling($line),
            $pattern,
            $scan['coverage'],
            $scan['inferred_count'],
            array_slice($candidates, 0, $this->clampLimit($limit)),
            $confidence,
            array_values(array_unique($warnings)),
            'free',
        );
    }

    // ------------------------------------------------------------------
    // Hemistich ranking
    // ------------------------------------------------------------------

    /** @return list<array<string,mixed>> */
    private function rankHemistich(string $pattern, string $side): array
    {
        $roles = match ($side) {
            'sadr' => ['sadr'],
            'ajuz' => ['ajuz'],
            default => ['sadr', 'ajuz'],
        };

        $perForm = [];
        foreach ($this->forms() as $meterKey => $meter) {
            foreach ($meter['forms'] as $form) {
                if (!$this->formEnabled($form)) {
                    continue;
                }
                $formRoles = $form['shape'] === 'line' ? ($side === 'either' ? ['line'] : []) : $roles;
                foreach ($formRoles as $role) {
                    $parse = Matcher::parse($pattern, Grammar::slots($form, $role));
                    if ($parse === null) {
                        continue;
                    }
                    $candidate = $this->candidate($meterKey, $meter, $form, $role, $pattern, $parse['path'], $parse['cost'], str_contains($pattern, '?') ? 'compatible' : 'exact', 0, 1.0);
                    $id = $form['id'];
                    if (!isset($perForm[$id]) || $candidate['cost'] < $perForm[$id]['cost']) {
                        $perForm[$id] = $candidate;
                    }
                }
            }
        }

        if ($perForm === [] && $this->allowApproximate) {
            $entries = [];
            foreach ($this->forms() as $meterKey => $meter) {
                foreach ($meter['forms'] as $form) {
                    if (!$this->formEnabled($form)) {
                        continue;
                    }
                    $formRoles = $form['shape'] === 'line' ? ($side === 'either' ? ['line'] : []) : $roles;
                    foreach ($formRoles as $role) {
                        $slots = Grammar::slots($form, $role);
                        $entries[] = [Grammar::distanceBound(strlen($pattern), $slots), $meterKey, $meter, $form, $role, $slots];
                    }
                }
            }
            usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            $limit = self::MAX_APPROX_DISTANCE;
            foreach ($entries as [$bound, $meterKey, $meter, $form, $role, $slots]) {
                if ($bound > $limit) {
                    break;
                }
                $near = Matcher::nearest($pattern, Grammar::templates($form['id'] . '#' . $role, $slots), $limit);
                if ($near === null) {
                    continue;
                }
                $limit = min($limit, $near['distance'] + 2);
                $candidate = $this->candidate($meterKey, $meter, $form, $role, $pattern, $near['path'], $near['cost'], 'approximate', $near['distance'], $near['score']);
                $candidate['feet'] = Matcher::align($pattern, $near['path']);
                $id = $form['id'];
                if (!isset($perForm[$id]) || $this->compare($candidate, $perForm[$id]) < 0) {
                    $perForm[$id] = $candidate;
                }
            }
        }

        return $this->bestPerMeter(array_values($perForm));
    }

    // ------------------------------------------------------------------
    // Verse analysis
    // ------------------------------------------------------------------

    private function verse(string $sadr, string $ajuz, int $limit, bool $infer, bool $saturate): VerseAnalysis
    {
        $sadrResult = $this->analyze($sadr, $limit, $infer, 'sadr', $saturate);
        $ajuzResult = $this->analyze($ajuz, $limit, $infer, 'ajuz', $saturate);
        $sp = $sadrResult->pattern;
        $ap = $ajuzResult->pattern;
        $coverage = min($sadrResult->diacriticCoverage, $ajuzResult->diacriticCoverage);

        $joint = [];
        if ($sp !== '' && $ap !== '') {
            foreach ($this->forms() as $meterKey => $meter) {
                foreach ($meter['forms'] as $form) {
                    if (!$this->formEnabled($form)) {
                        continue;
                    }
                    [$r1, $r2] = $form['shape'] === 'line' ? ['line', 'line'] : ['sadr', 'ajuz'];
                    $left = Matcher::parse($sp, Grammar::slots($form, $r1));
                    $right = Matcher::parse($ap, Grammar::slots($form, $r2));
                    if ($right === null) {
                        continue;
                    }
                    $tasri = false;
                    if ($left === null && $form['shape'] === 'bayt') {
                        // Taṣrīʿ: in a poem's opening verse the ʿarūḍ takes the same shape as the ḍarb.
                        $alt = Matcher::parse($sp, Grammar::slots($form, 'ajuz'));
                        $altLast = $alt === null ? null : end($alt['path']);
                        $rightLast = end($right['path']);
                        if ($altLast !== null && $altLast['kind'] === 'illa' && $rightLast['kind'] === 'illa') {
                            $left = $alt;
                            $left['cost']++;
                            $tasri = true;
                        }
                    }
                    if ($left === null) {
                        continue;
                    }
                    $joint[] = $this->jointCandidate($meterKey, $meter, $form, $sp, $ap, $left, $right, 'exact', 0, 1.0, $tasri);
                }
            }

            if ($joint === [] && $this->allowApproximate) {
                $entries = [];
                foreach ($this->forms() as $meterKey => $meter) {
                    foreach ($meter['forms'] as $form) {
                        if (!$this->formEnabled($form)) {
                            continue;
                        }
                        [$r1, $r2] = $form['shape'] === 'line' ? ['line', 'line'] : ['sadr', 'ajuz'];
                        $s1 = Grammar::slots($form, $r1);
                        $s2 = Grammar::slots($form, $r2);
                        $entries[] = [Grammar::distanceBound(strlen($sp), $s1) + Grammar::distanceBound(strlen($ap), $s2), $meterKey, $meter, $form, $r1, $r2, $s1, $s2];
                    }
                }
                usort($entries, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
                $limit = self::MAX_APPROX_DISTANCE * 2;
                foreach ($entries as [$bound, $meterKey, $meter, $form, $r1, $r2, $s1, $s2]) {
                    if ($bound > $limit) {
                        break;
                    }
                    $l = Matcher::nearest($sp, Grammar::templates($form['id'] . '#' . $r1, $s1), $limit);
                    $r = Matcher::nearest($ap, Grammar::templates($form['id'] . '#' . $r2, $s2), $limit);
                    if ($l === null || $r === null) {
                        continue;
                    }
                    $den = max(strlen($sp) + strlen($ap), 1);
                    $distance = $l['distance'] + $r['distance'];
                    $limit = min($limit, $distance + 2);
                    $c = $this->jointCandidate(
                        $meterKey, $meter, $form, $sp, $ap,
                        ['path' => $l['path'], 'cost' => $l['cost']],
                        ['path' => $r['path'], 'cost' => $r['cost']],
                        'approximate', $distance, round(max(0.0, 1 - $distance / $den), 4)
                    );
                    $c['feet'] = ['sadr' => Matcher::align($sp, $l['path']), 'ajuz' => Matcher::align($ap, $r['path'])];
                    $joint[] = $c;
                }
            }
        }

        $all = $this->bestPerMeter($joint);
        $confidence = $this->confidenceFor($all, $coverage);
        $warnings = array_merge($sadrResult->warnings, $ajuzResult->warnings);
        $this->addCandidateWarnings($warnings, $all);

        if ($all === [] || ($all[0]['match_type'] ?? '') === 'approximate') {
            $a = $sadrResult->bestMatch();
            $b = $ajuzResult->bestMatch();
            if ($a !== null && $b !== null && in_array($a['match_type'], ['exact', 'compatible'], true)
                && in_array($b['match_type'], ['exact', 'compatible'], true) && $a['key'] !== $b['key']) {
                $warnings[] = 'The two hemistichs fit different meters (' . $a['name_ar'] . ' / ' . $b['name_ar'] . '); check for a textual or vocalization error.';
            }
        }

        return new VerseAnalysis($sadrResult, $ajuzResult, array_slice($all, 0, $limit), $confidence, array_values(array_unique($warnings)));
    }

    /**
     * @param array<string,mixed> $meter
     * @param array<string,mixed> $form
     * @param array{path:list<array<string,mixed>>,cost:int} $left
     * @param array{path:list<array<string,mixed>>,cost:int} $right
     * @return array<string,mixed>
     */
    private function jointCandidate(string $key, array $meter, array $form, string $sp, string $ap, array $left, array $right, string $matchType, int $distance, float $score, bool $tasri = false): array
    {
        $cost = $left['cost'] + $right['cost'];
        $label = $this->label($form, $right['path']);
        $mods = array_values(array_unique([...$this->modifications($left['path']), ...$this->modifications($right['path'])]));
        if ($tasri) {
            $mods[] = 'التصريع';
        }

        return [
            'key' => $key,
            'name_ar' => $meter['name_ar'],
            'name_en' => $meter['name_en'],
            'form_id' => $form['id'],
            'form_type' => $form['type'],
            'shape' => $form['shape'],
            'label_ar' => $label,
            'tier' => $form['tier'],
            'match_type' => $matchType,
            'score' => $score,
            'distance' => $distance,
            'expected_pattern' => implode('', array_column($left['path'], 'pattern')) . ' | ' . implode('', array_column($right['path'], 'pattern')),
            'observed_pattern' => $sp . ' | ' . $ap,
            'feet' => [
                'sadr' => array_map(static fn (array $o): array => Matcher::footRow($o), $left['path']),
                'ajuz' => array_map(static fn (array $o): array => Matcher::footRow($o), $right['path']),
            ],
            'modifications' => $mods,
            'tasri' => $tasri,
            'cost' => $cost,
            'prior' => $meter['prior'],
        ];
    }

    // ------------------------------------------------------------------
    // Poem analysis
    // ------------------------------------------------------------------

    /** @param list<VerseAnalysis> $verses */
    private function summarizePoem(array $verses, int $limit): PoemAnalysis
    {
        $support = [];
        $exclusive = [];
        $forms = [];
        $matchedVerses = [];
        foreach ($verses as $i => $v) {
            $keys = [];
            foreach ($v->candidates as $c) {
                if (in_array($c['match_type'], ['exact', 'compatible'], true)) {
                    $keys[$c['key']] = $c['form_id'];
                }
            }
            $matchedVerses[$i] = array_keys($keys);
            foreach ($keys as $key => $formId) {
                $support[$key] = ($support[$key] ?? 0) + 1;
                $forms[$key][$formId] = ($forms[$key][$formId] ?? 0) + 1;
                if (count($keys) === 1) {
                    $exclusive[$key] = ($exclusive[$key] ?? 0) + 1;
                }
            }
        }

        $total = count($verses);
        $ranked = [];
        foreach ($support as $key => $count) {
            $meter = MeterCatalog::get($key);
            arsort($forms[$key]);
            $ranked[] = [
                'key' => $key,
                'name_ar' => $meter['name_ar'],
                'name_en' => $meter['name_en'],
                'verses_matched' => $count,
                'verses_exclusive' => $exclusive[$key] ?? 0,
                'ratio' => round($count / max($total, 1), 4),
                'form_id' => array_key_first($forms[$key]),
                'prior' => $meter['prior'],
            ];
        }
        usort($ranked, static fn (array $a, array $b): int
            => [$b['verses_matched'], $b['verses_exclusive'], $a['prior']] <=> [$a['verses_matched'], $a['verses_exclusive'], $b['prior']]);

        $warnings = [];
        $dominant = $ranked[0] ?? null;
        $deviations = [];
        if ($dominant !== null) {
            foreach ($verses as $i => $v) {
                if (!in_array($dominant['key'], $matchedVerses[$i], true)) {
                    $deviations[] = $i + 1;
                }
            }
        } else {
            $warnings[] = 'No verse matched any meter exactly.';
        }

        $confidence = 'low';
        if ($dominant !== null) {
            $tied = isset($ranked[1]) && $ranked[1]['verses_matched'] === $dominant['verses_matched']
                && $ranked[1]['verses_exclusive'] === $dominant['verses_exclusive'];
            if ($tied) {
                $warnings[] = sprintf('%s and %s are equally supported; more vocalized verses are needed to decide.', $dominant['name_ar'], $ranked[1]['name_ar']);
            } elseif ($dominant['ratio'] >= 0.9 && $total >= 3 && $dominant['verses_exclusive'] >= 1) {
                $confidence = 'high';
            } elseif ($dominant['ratio'] >= 0.75) {
                $confidence = 'medium';
            }
            if ($deviations !== []) {
                $warnings[] = sprintf('%d verse(s) do not fit %s: #%s.', count($deviations), $dominant['name_ar'], implode(', #', array_slice($deviations, 0, 20)));
            }
        }
        if ($total < 3 && $dominant !== null) {
            $warnings[] = 'Fewer than three verses: poem-level confidence is capped.';
            $confidence = $confidence === 'high' ? 'medium' : $confidence;
        }

        return new PoemAnalysis(
            $verses,
            array_slice($ranked, 0, $limit),
            $dominant['key'] ?? null,
            $dominant['form_id'] ?? null,
            $dominant['ratio'] ?? 0.0,
            $deviations,
            $confidence,
            $warnings,
        );
    }

    // ------------------------------------------------------------------
    // Candidate helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $meter
     * @param array<string,mixed> $form
     * @param list<array<string,mixed>> $path
     * @return array<string,mixed>
     */
    private function candidate(string $key, array $meter, array $form, string $role, string $pattern, array $path, int $cost, string $matchType, int $distance, float $score): array
    {
        return [
            'key' => $key,
            'name_ar' => $meter['name_ar'],
            'name_en' => $meter['name_en'],
            'form_id' => $form['id'],
            'form_type' => $form['type'],
            'shape' => $form['shape'],
            'role' => $role,
            'label_ar' => $this->label($form, $path),
            'tier' => $form['tier'],
            'match_type' => $matchType,
            'score' => $score,
            'distance' => $distance,
            'expected_pattern' => implode('', array_column($path, 'pattern')),
            'feet' => array_map(static fn (array $o): array => Matcher::footRow($o), $path),
            'modifications' => $this->modifications($path),
            'cost' => $cost,
            'prior' => $meter['prior'],
        ];
    }

    /** @param array<string,mixed> $form @param list<array<string,mixed>> $path */
    private function label(array $form, array $path): string
    {
        $last = $path === [] ? null : $path[count($path) - 1];
        if ($last !== null && isset($form['labels'][$last['key']])) {
            return $form['labels'][$last['key']];
        }

        return $form['display_ar'];
    }

    /** @param list<array<string,mixed>> $path @return list<string> */
    private function modifications(array $path): array
    {
        $out = [];
        foreach ($path as $o) {
            if ($o['key'] !== 's') {
                $out[] = $o['rule_ar'];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param list<array<string,mixed>> $candidates
     * @return list<array<string,mixed>>
     */
    private function bestPerMeter(array $candidates): array
    {
        $best = [];
        foreach ($candidates as $c) {
            $k = $c['key'];
            if (!isset($best[$k]) || $this->compare($c, $best[$k]) < 0) {
                $best[$k] = $c;
            }
        }
        $list = array_values($best);
        usort($list, fn (array $a, array $b): int => $this->compare($a, $b));

        $exact = array_values(array_filter($list, static fn (array $c): bool => $c['match_type'] !== 'approximate'));
        foreach ($list as &$c) {
            $c['ambiguous_with'] = $c['match_type'] === 'approximate' ? [] : array_values(array_map(
                static fn (array $o): string => $o['key'],
                array_filter($exact, static fn (array $o): bool => $o['key'] !== $c['key'])
            ));
        }
        unset($c);

        return $list;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function compare(array $a, array $b): int
    {
        $rank = ['exact' => 3, 'compatible' => 2, 'approximate' => 1];
        $tier = ['classical' => 0, 'rare' => 1];

        return [$rank[$b['match_type']], $b['score'], $tier[$a['tier']], $a['cost'], $a['prior'] ?? 99, $a['key']]
            <=> [$rank[$a['match_type']], $a['score'], $tier[$b['tier']], $b['cost'], $b['prior'] ?? 99, $b['key']];
    }

    /** @param list<array<string,mixed>> $candidates */
    private function confidenceFor(array $candidates, float $coverage): string
    {
        $top = $candidates[0] ?? null;
        if ($top === null || $top['match_type'] === 'approximate') {
            return 'low';
        }
        $others = array_values(array_filter(
            array_slice($candidates, 1),
            static fn (array $c): bool => in_array($c['match_type'], ['exact', 'compatible'], true)
        ));
        $classicalOthers = array_filter($others, static fn (array $c): bool => $c['tier'] === 'classical');

        if ($top['match_type'] === 'exact' && $top['tier'] === 'classical' && $coverage >= 0.9 && $classicalOthers === []) {
            return 'high';
        }
        if ($coverage >= 0.8) {
            if ($others === []) {
                return 'medium';
            }
            $minOther = min(array_column($others, 'cost'));
            if ($top['tier'] === 'classical' && $top['cost'] < $minOther) {
                return 'medium';
            }
        }

        return 'low';
    }

    /**
     * @param list<string> $warnings
     * @param list<array<string,mixed>> $all
     */
    private function addCandidateWarnings(array &$warnings, array $all): void
    {
        $top = $all[0] ?? null;
        if ($top === null) {
            $warnings[] = 'No meter matched.';
            return;
        }
        if ($top['match_type'] === 'approximate') {
            $warnings[] = 'No meter matched exactly; candidates are approximate (edit distance) and may reflect a text/vocalization error.';
        }
        if (($top['ambiguous_with'] ?? []) !== []) {
            $names = array_map(static fn (string $k): string => MeterCatalog::get($k)['name_ar'], $top['ambiguous_with']);
            $warnings[] = 'The pattern is also compatible with: ' . implode('، ', $names) . '.';
        }
        if ($top['tier'] === 'rare') {
            $warnings[] = 'Best match is a rare form whose grammar is only partially verified.';
        }
        if ($top['match_type'] === 'compatible') {
            $warnings[] = 'Some letters are unmarked; the match assumes any vowelling for them.';
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function forms(): array
    {
        return MeterCatalog::all();
    }

    /** @param array<string,mixed> $form */
    private function formEnabled(array $form): bool
    {
        return $this->includeRareForms || $form['tier'] === 'classical';
    }

    private function clampLimit(int $limit): int
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('limit must be at least 1.');
        }

        return min($limit, 50);
    }

    private function assertSide(string $side): void
    {
        if (!in_array($side, self::SIDES, true)) {
            throw new \InvalidArgumentException("side must be one of: " . implode(', ', self::SIDES) . '.');
        }
    }

    private function assertHemistich(string $text): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('Input must be valid UTF-8.');
        }
        if (strlen($text) > InputNormalizer::MAX_HEMISTICH_BYTES) {
            throw new \InvalidArgumentException(sprintf('A hemistich is limited to %d bytes.', InputNormalizer::MAX_HEMISTICH_BYTES));
        }
    }
}
