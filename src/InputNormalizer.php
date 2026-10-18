<?php

declare(strict_types=1);

namespace Arud;

/**
 * Accepts every supported input shape and returns a uniform description.
 *
 *   "line"                             → shatr
 *   "sadr *** ajuz"  /  ["sadr","ajuz"] / ['sadr'=>…,'ajuz'=>…]  → bayt
 *   multi-line string / list of pairs / flat even list / ['verses'=>[…]] → poem
 */
final class InputNormalizer
{
    public const MAX_VERSES = 2000;
    public const MAX_HEMISTICH_BYTES = 2000;
    public const MAX_TOTAL_BYTES = 2_000_000;

    private const SEPARATOR = '/\s*(?:\*+|…|\.{3,}|\t+|\s{4,})\s*/u';
    private const SADR_KEYS = ['sadr', 'first', 'صدر'];
    private const AJUZ_KEYS = ['ajuz', 'second', 'عجز'];

    /**
     * @param string|array<mixed> $input
     * @return array{kind:string,shatr:?string,verses:list<array{sadr:string,ajuz:string}>}
     */
    public static function parse(string|array $input, ?string $second = null): array
    {
        if ($second !== null) {
            if (!is_string($input)) {
                throw new \InvalidArgumentException('When an ajuz is given, the first argument must be the sadr string.');
            }
            $input = [$input, $second];
        }

        $total = 0;
        if (is_string($input)) {
            $result = self::fromString($input);
        } else {
            $result = self::fromArray($input);
        }

        foreach ($result['verses'] as $verse) {
            $total += strlen($verse['sadr']) + strlen($verse['ajuz']);
        }
        $total += strlen($result['shatr'] ?? '');
        if (count($result['verses']) > self::MAX_VERSES) {
            throw new \InvalidArgumentException(sprintf('Too many verses (max %d).', self::MAX_VERSES));
        }
        if ($total > self::MAX_TOTAL_BYTES) {
            throw new \InvalidArgumentException('Input is too large.');
        }

        return $result;
    }

    /** @return array{kind:string,shatr:?string,verses:list<array{sadr:string,ajuz:string}>} */
    private static function fromString(string $text): array
    {
        self::assertText($text);
        $lines = preg_split('/\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
        if ($lines === []) {
            throw new \InvalidArgumentException('Input is empty.');
        }

        if (count($lines) === 1) {
            $parts = self::split($lines[0]);
            if (count($parts) === 1) {
                return ['kind' => 'shatr', 'shatr' => self::hemistich($parts[0]), 'verses' => []];
            }
            if (count($parts) === 2) {
                return ['kind' => 'bayt', 'shatr' => null, 'verses' => [self::pair($parts[0], $parts[1])]];
            }
            throw new \InvalidArgumentException('A line may contain at most one hemistich separator.');
        }

        $verses = [];
        $pending = null;
        foreach ($lines as $line) {
            $parts = self::split($line);
            if (count($parts) === 2) {
                if ($pending !== null) {
                    throw new \InvalidArgumentException('Mixed one-column and two-column lines are ambiguous.');
                }
                $verses[] = self::pair($parts[0], $parts[1]);
            } elseif (count($parts) === 1) {
                if ($pending === null) {
                    $pending = $parts[0];
                } else {
                    $verses[] = self::pair($pending, $parts[0]);
                    $pending = null;
                }
            } else {
                throw new \InvalidArgumentException('A line may contain at most one hemistich separator.');
            }
        }
        if ($pending !== null) {
            throw new \InvalidArgumentException('Odd number of hemistichs: every verse needs a sadr and an ajuz.');
        }

        return ['kind' => count($verses) === 1 ? 'bayt' : 'poem', 'shatr' => null, 'verses' => $verses];
    }

    /**
     * @param array<mixed> $input
     * @return array{kind:string,shatr:?string,verses:list<array{sadr:string,ajuz:string}>}
     */
    private static function fromArray(array $input): array
    {
        if ($input === []) {
            throw new \InvalidArgumentException('Input is empty.');
        }
        if (isset($input['verses']) && is_array($input['verses'])) {
            return self::fromArray(array_values($input['verses']));
        }
        if (!array_is_list($input)) {
            return ['kind' => 'bayt', 'shatr' => null, 'verses' => [self::fromAssoc($input)]];
        }

        $allStrings = true;
        foreach ($input as $item) {
            if (!is_string($item)) {
                $allStrings = false;
                break;
            }
        }

        if ($allStrings) {
            /** @var list<string> $input */
            if (count($input) === 1) {
                $parts = self::split($input[0]);
                if (count($parts) === 1) {
                    return ['kind' => 'shatr', 'shatr' => self::hemistich($parts[0]), 'verses' => []];
                }
            }
            if (count($input) === 2) {
                return ['kind' => 'bayt', 'shatr' => null, 'verses' => [self::pair($input[0], $input[1])]];
            }
            if (count($input) % 2 !== 0) {
                throw new \InvalidArgumentException('Odd number of hemistichs: every verse needs a sadr and an ajuz.');
            }
            $verses = [];
            for ($i = 0; $i < count($input); $i += 2) {
                $verses[] = self::pair($input[$i], $input[$i + 1]);
            }

            return ['kind' => 'poem', 'shatr' => null, 'verses' => $verses];
        }

        $verses = [];
        foreach ($input as $i => $item) {
            if (is_array($item) && array_is_list($item) && count($item) === 2 && is_string($item[0]) && is_string($item[1])) {
                $verses[] = self::pair($item[0], $item[1]);
            } elseif (is_array($item) && !array_is_list($item)) {
                $verses[] = self::fromAssoc($item);
            } elseif (is_string($item)) {
                $parts = self::split($item);
                if (count($parts) !== 2) {
                    throw new \InvalidArgumentException("Verse #" . ($i + 1) . ' must be a [sadr, ajuz] pair.');
                }
                $verses[] = self::pair($parts[0], $parts[1]);
            } else {
                throw new \InvalidArgumentException('Verse #' . ($i + 1) . ' must be a [sadr, ajuz] pair.');
            }
        }

        return ['kind' => count($verses) === 1 ? 'bayt' : 'poem', 'shatr' => null, 'verses' => $verses];
    }

    /**
     * @param array<mixed> $assoc
     * @return array{sadr:string,ajuz:string}
     */
    private static function fromAssoc(array $assoc): array
    {
        $sadr = $ajuz = null;
        foreach (self::SADR_KEYS as $k) {
            if (isset($assoc[$k]) && is_string($assoc[$k])) {
                $sadr = $assoc[$k];
            }
        }
        foreach (self::AJUZ_KEYS as $k) {
            if (isset($assoc[$k]) && is_string($assoc[$k])) {
                $ajuz = $assoc[$k];
            }
        }
        if ($sadr === null || $ajuz === null) {
            throw new \InvalidArgumentException("A verse array needs 'sadr' and 'ajuz' keys.");
        }

        return self::pair($sadr, $ajuz);
    }

    /** @return list<string> */
    private static function split(string $line): array
    {
        $parts = preg_split(self::SEPARATOR, trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new \InvalidArgumentException('Input is not valid UTF-8 text.');
        }

        return array_values(array_map('trim', $parts));
    }

    /** @return array{sadr:string,ajuz:string} */
    private static function pair(string $sadr, string $ajuz): array
    {
        return ['sadr' => self::hemistich($sadr), 'ajuz' => self::hemistich($ajuz)];
    }

    private static function hemistich(string $text): string
    {
        self::assertText($text);
        $text = trim($text);
        if ($text === '') {
            throw new \InvalidArgumentException('A hemistich must not be empty.');
        }
        if (strlen($text) > self::MAX_HEMISTICH_BYTES) {
            throw new \InvalidArgumentException(sprintf('A hemistich is limited to %d bytes.', self::MAX_HEMISTICH_BYTES));
        }

        return $text;
    }

    private static function assertText(string $text): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('Input must be valid UTF-8.');
        }
    }
}
