<?php

declare(strict_types=1);

namespace Arud;

/** Compiles a meter form into per-position option "slots" for one hemistich role. */
final class Grammar
{
    /** @var array<string,list<list<array<string,mixed>>>> */
    private static array $slotCache = [];

    /** @var array<string,list<array{pattern:string,path:list<array<string,mixed>>,cost:int}>> */
    private static array $templateCache = [];

    /**
     * @param array<string,mixed> $form
     * @param 'sadr'|'ajuz'|'line' $role
     * @return list<list<array<string,mixed>>>
     */
    public static function slots(array $form, string $role): array
    {
        $cacheKey = $form['id'] . '#' . $role;
        if (isset(self::$slotCache[$cacheKey])) {
            return self::$slotCache[$cacheKey];
        }

        $count = count($form['feet']);
        $slots = [];
        foreach ($form['feet'] as $i => $foot) {
            if ($i < $count - 1) {
                $keys = $form['hashw'][$i];
                $position = 'hashw';
            } elseif ($role === 'sadr') {
                $keys = $form['arud'];
                $position = 'arud';
            } else {
                $keys = $form['darb'];
                $position = 'darb';
            }
            $options = [];
            foreach (FootCatalog::options($foot, $keys) as $option) {
                $option['index'] = $i + 1;
                $option['position'] = $position;
                $options[] = $option;
            }
            $slots[] = $options;
        }

        return self::$slotCache[$cacheKey] = $slots;
    }

    /**
     * Every template a slot list can generate (bounded; used for approximate ranking).
     *
     * @param list<list<array<string,mixed>>> $slots
     * @return list<array{pattern:string,path:list<array<string,mixed>>,cost:int}>
     */
    public static function templates(string $cacheKey, array $slots): array
    {
        if (isset(self::$templateCache[$cacheKey])) {
            return self::$templateCache[$cacheKey];
        }
        $templates = [['pattern' => '', 'path' => [], 'cost' => 0]];
        foreach ($slots as $options) {
            $next = [];
            foreach ($templates as $t) {
                foreach ($options as $option) {
                    $next[] = [
                        'pattern' => $t['pattern'] . $option['pattern'],
                        'path' => [...$t['path'], $option],
                        'cost' => $t['cost'] + ($option['key'] === 's' ? 0 : 1),
                    ];
                }
            }
            if (count($next) > 20000) {
                throw new \LogicException('Grammar produces too many templates for ' . $cacheKey);
            }
            $templates = $next;
        }

        return self::$templateCache[$cacheKey] = $templates;
    }

    /**
     * Shortest and longest pattern a slot list can produce.
     *
     * @param list<list<array<string,mixed>>> $slots
     * @return array{0:int,1:int}
     */
    public static function lengthRange(array $slots): array
    {
        $min = 0;
        $max = 0;
        foreach ($slots as $options) {
            $lengths = array_map(static fn (array $o): int => strlen($o['pattern']), $options);
            $min += min($lengths);
            $max += max($lengths);
        }

        return [$min, $max];
    }

    /** Lower bound on the edit distance between a pattern of length $n and any template of the slots. */
    public static function distanceBound(int $n, array $slots): int
    {
        [$min, $max] = self::lengthRange($slots);

        return max(0, $min - $n, $n - $max);
    }
}
