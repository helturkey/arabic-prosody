<?php

declare(strict_types=1);

namespace Arud;

/** Deterministic foot-by-foot matching of a 1/0/? pattern against grammar slots. */
final class Matcher
{
    private const MAX_PARSES = 512;

    /**
     * Best exact/compatible parse (fewest modified feet), or null.
     *
     * @param list<list<array<string,mixed>>> $slots
     * @return array{path:list<array<string,mixed>>,cost:int,parses:int}|null
     */
    public static function parse(string $observed, array $slots): ?array
    {
        $best = null;
        $found = 0;
        self::walk($observed, strlen($observed), 0, 0, $slots, [], 0, $best, $found);

        return $best === null ? null : ['path' => $best['path'], 'cost' => $best['cost'], 'parses' => $found];
    }

    /**
     * @param list<list<array<string,mixed>>> $slots
     * @param list<array<string,mixed>> $path
     * @param array{path:list<array<string,mixed>>,cost:int}|null $best
     */
    private static function walk(string $obs, int $n, int $pos, int $slot, array $slots, array $path, int $cost, ?array &$best, int &$found): void
    {
        if ($found >= self::MAX_PARSES) {
            return;
        }
        if ($slot === count($slots)) {
            if ($pos === $n) {
                $found++;
                if ($best === null || $cost < $best['cost']) {
                    $best = ['path' => $path, 'cost' => $cost];
                }
            }
            return;
        }
        foreach ($slots[$slot] as $option) {
            $len = strlen($option['pattern']);
            if ($pos + $len > $n || !self::compatible(substr($obs, $pos, $len), $option['pattern'])) {
                continue;
            }
            self::walk($obs, $n, $pos + $len, $slot + 1, $slots, [...$path, $option], $cost + ($option['key'] === 's' ? 0 : 1), $best, $found);
        }
    }

    /**
     * Free-verse style line: one foot family repeated 1..$max times.
     *
     * @param list<array<string,mixed>> $inner
     * @param list<array<string,mixed>> $last
     * @return array{path:list<array<string,mixed>>,cost:int}|null
     */
    public static function parseRepeated(string $observed, array $inner, array $last, int $max = 16): ?array
    {
        $best = null;
        $n = strlen($observed);
        for ($count = 1; $count <= $max; $count++) {
            $slots = array_fill(0, $count - 1, $inner);
            $slots[] = $last;
            $parsed = self::parse($observed, $slots);
            if ($parsed !== null && ($best === null || $parsed['cost'] < $best['cost'])) {
                $best = ['path' => $parsed['path'], 'cost' => $parsed['cost']];
            }
            if ($count * 2 > $n) {
                break;
            }
        }

        return $best;
    }

    /**
     * Nearest template by '?'-tolerant edit distance.
     *
     * @param list<array{pattern:string,path:list<array<string,mixed>>,cost:int}> $templates
     * @return array{distance:int,score:float,pattern:string,path:list<array<string,mixed>>,cost:int}|null
     */
    public static function nearest(string $observed, array $templates, int $maxDistance = PHP_INT_MAX): ?array
    {
        $best = null;
        $n = strlen($observed);
        foreach ($templates as $t) {
            // Length difference is a lower bound on edit distance: skip hopeless templates.
            if (abs($n - strlen($t['pattern'])) > ($best['distance'] ?? $maxDistance)) {
                continue;
            }
            $d = self::distance($observed, $t['pattern']);
            if ($best === null || $d < $best['distance'] || ($d === $best['distance'] && $t['cost'] < $best['cost'])) {
                $best = ['distance' => $d, 'pattern' => $t['pattern'], 'path' => $t['path'], 'cost' => $t['cost']];
            }
        }
        if ($best === null || $best['distance'] > $maxDistance) {
            return null;
        }
        $den = max(strlen($observed), strlen($best['pattern']), 1);
        $best['score'] = round(max(0.0, 1 - $best['distance'] / $den), 4);

        return $best;
    }

    /**
     * Foot-by-foot status of an observed pattern against the chosen template.
     *
     * @param list<array<string,mixed>> $path
     * @return list<array<string,mixed>>
     */
    public static function align(string $observed, array $path, bool $strict = false): array
    {
        $feet = [];
        $pos = 0;
        $n = strlen($observed);
        foreach ($path as $option) {
            $len = strlen($option['pattern']);
            $slice = substr($observed, $pos, $len);
            if ($strict || self::compatible($slice, $option['pattern'])) {
                $status = 'ok';
            } elseif ($slice === '' || $pos >= $n) {
                $status = 'missing';
            } elseif (strlen($slice) < $len) {
                $status = 'missing_bits';
            } else {
                $status = 'broken';
            }
            $feet[] = self::footRow($option, $status, $slice);
            $pos += $len;
        }
        if ($pos < $n) {
            $feet[] = ['index' => count($path) + 1, 'role' => 'extra', 'status' => 'extra_bits', 'observed' => substr($observed, $pos)];
        }

        return $feet;
    }

    /** @param array<string,mixed> $option @return array<string,mixed> */
    public static function footRow(array $option, string $status = 'ok', ?string $observed = null): array
    {
        $info = FootCatalog::get($option['foot']);

        return [
            'index' => $option['index'],
            'role' => $option['position'],
            'base' => $option['foot'],
            'base_name_ar' => $info['name_ar'],
            'name_ar' => $option['name_ar'],
            'pattern' => $option['pattern'],
            'observed' => $observed,
            'variant' => $option['key'],
            'rule_ar' => $option['rule_ar'],
            'rule_en' => $option['rule_en'],
            'kind' => $option['kind'],
            'status' => $status,
        ];
    }

    public static function compatible(string $observed, string $expected): bool
    {
        $len = strlen($expected);
        if (strlen($observed) !== $len) {
            return false;
        }
        for ($i = 0; $i < $len; $i++) {
            if ($observed[$i] !== '?' && $observed[$i] !== $expected[$i]) {
                return false;
            }
        }

        return true;
    }

    public static function distance(string $observed, string $expected): int
    {
        if (!str_contains($observed, '?') && strlen($observed) <= 255 && strlen($expected) <= 255) {
            return levenshtein($observed, $expected);
        }
        $a = strlen($observed);
        $b = strlen($expected);
        $prev = range(0, $b);
        for ($i = 1; $i <= $a; $i++) {
            $cur = [$i];
            for ($j = 1; $j <= $b; $j++) {
                $sub = ($observed[$i - 1] === '?' || $observed[$i - 1] === $expected[$j - 1]) ? 0 : 1;
                $cur[$j] = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $sub);
            }
            $prev = $cur;
        }

        return $prev[$b];
    }
}
