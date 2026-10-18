<?php

declare(strict_types=1);

namespace Arud;

/**
 * Dependency-free Arabic orthography → arudi (prosodic) pattern conversion.
 *
 * Pipeline: clean → split words → lexical fixes (silent/omitted letters) →
 * dagger alif / tanween carriers → hamzat al-wasl & definite-article handling
 * (with iltiqā' al-sākinayn bridging) → hamza folding → shadda/tanween
 * expansion. The result is scanned into a 1/0/? pattern.
 *
 * Scope: classical fully vocalized text. It does NOT diacritize.
 */
final class ArabicText
{
    private const TATWEEL = "\u{0640}";
    private const SHADDA = "\u{0651}";
    private const SUKUN = "\u{0652}";
    private const FATHA = "\u{064E}";
    private const DAMMA = "\u{064F}";
    private const KASRA = "\u{0650}";
    private const FATHATAN = "\u{064B}";
    private const DAMMATAN = "\u{064C}";
    private const KASRATAN = "\u{064D}";
    private const DAGGER = "\u{0670}";
    private const SUN_LETTERS = 'تثدذرزسشصضطظلن';
    private const PROCLITICS = 'وفبكل';

    /** @var array<string,string> */
    private const PRESENTATION_FORMS = [
        "\u{FEF5}" => 'آل', "\u{FEF6}" => 'آل',
        "\u{FEF7}" => 'لأ', "\u{FEF8}" => 'لأ',
        "\u{FEF9}" => 'لإ', "\u{FEFA}" => 'لإ',
        "\u{FEFB}" => 'لا', "\u{FEFC}" => 'لا',
    ];

    /** Nouns whose initial alif is hamzat al-wasl (plain, alef-folded). */
    private const LEXICAL_WASL = [
        'ابن', 'ابنة', 'ابنت', 'اسم', 'امرؤ', 'امرأ', 'امرئ', 'امرأة',
        'اثنان', 'اثنين', 'اثنتان', 'اثنتين', 'ايم', 'است',
    ];

    /** Unvocalized words beginning with ال that are NOT the definite article. */
    private const NOT_ARTICLE_BARE = [
        'الى', 'الا', 'الم', 'الف', 'الي', 'اله', 'الاه', 'الان', 'الهي', 'الهة', 'الاف',
    ];

    /**
     * Conventional arudi spelling: letters pronounced but not written
     * (insert a long alif after the given 0-based letter) or written but
     * silent (drop the given 0-based letter). Keys are plain, alef-folded stems;
     * up to two proclitic letters (و ف ب ك ل ت) may precede them.
     *
     * @var array<string,array{insert?:list<int>,drop?:list<int>}>
     */
    private const LEXICAL_FIXES = [
        'هذا' => ['insert' => [0]], 'هذه' => ['insert' => [0]], 'هذي' => ['insert' => [0]],
        'هذان' => ['insert' => [0]], 'هذين' => ['insert' => [0]], 'هؤلاء' => ['insert' => [0]],
        'ذلك' => ['insert' => [0]], 'ذلكم' => ['insert' => [0]], 'ذلكما' => ['insert' => [0]], 'ذلكن' => ['insert' => [0]],
        'اولئك' => ['drop' => [1], 'insert' => [2]], 'اولاء' => ['drop' => [1]], 'اولات' => ['drop' => [1]],
        'اولو' => ['drop' => [1]], 'اولي' => ['drop' => [1]],
        'عمرو' => ['drop' => [3]],
        'لكن' => ['insert' => [0]],
        'رحمن' => ['insert' => [2]], 'الرحمن' => ['insert' => [4]],
        'اله' => ['insert' => [1]],
        'طه' => ['insert' => [0, 1]],
    ];

    public static function normalize(string $text, bool $normalizeHamza = false): string
    {
        $text = self::clean($text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا']);
        if ($normalizeHamza) {
            $text = str_replace('ء', 'ا', $text);
        }

        return $text;
    }

    public static function stripDiacritics(string $text, bool $keepShadda = false): string
    {
        self::assertValidUtf8($text);
        if (!$keepShadda && class_exists(\ArabicSupport\Arabic::class) && method_exists(\ArabicSupport\Arabic::class, 'stripDiacritics')) {
            return \ArabicSupport\Arabic::stripDiacritics($text, true);
        }

        $pattern = $keepShadda
            ? '/[\x{064B}-\x{0650}\x{0652}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u'
            : '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u';

        return preg_replace($pattern, '', $text) ?? $text;
    }

    /** Pronunciation-oriented spelling (see class docblock). */
    public static function prosodicSpelling(string $text): string
    {
        $text = self::clean($text);
        $text = str_replace('آ', 'ءَا', $text);
        $text = self::normalizeMarkOrder($text);
        $raw = $text === '' ? [] : (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        $words = [];
        foreach ($raw as $word) {
            $clusters = self::parseWord($word);
            $clusters = self::lexicalFixes($clusters);
            $clusters = self::daggerAlif($clusters);
            $clusters = self::tanweenCarriers($clusters);
            $wasl = self::detectWasl($clusters);
            if ($wasl !== null) {
                $index = count($words);
                $elide = $wasl['k'] > 0 || $index > 0;
                $clusters = self::applyWasl($clusters, $wasl, $elide);
                if ($elide && $wasl['k'] === 0 && $index > 0) {
                    $words[$index - 1] = self::bridge($words[$index - 1]);
                }
            }
            $words[] = self::dropSilentAlif($clusters);
        }

        return self::render($words);
    }

    /**
     * Convert text to the traditional arudi pattern:
     * 1 = vowelled consonant; 0 = sakin consonant or long vowel; ? = unknown in strict mode.
     *
     * @return array{pattern:string, coverage:float, letter_count:int, inferred_count:int, warnings:list<string>}
     */
    public static function scan(string $text, bool $inferUnmarked = false, bool $saturateEnd = true): array
    {
        $clusters = self::clusters(self::prosodicSpelling($text));
        $pattern = '';
        $letterCount = 0;
        $knownCount = 0;
        $inferredCount = 0;
        $count = count($clusters);

        for ($i = 0; $i < $count; $i++) {
            $item = $clusters[$i];
            if ($item['type'] !== 'letter' || !self::isArabicLetter($item['char'])) {
                continue;
            }

            $marks = $item['marks'];
            $letterCount++;
            $hasShadda = str_contains($marks, self::SHADDA);
            $hasSukun = str_contains($marks, self::SUKUN);
            $hasShortVowel = preg_match('/[\x{064B}-\x{0650}\x{0670}]/u', $marks) === 1;
            $isLongVowel = self::isLongVowelAt($clusters, $i) && !$hasShortVowel && !$hasSukun;
            if ($hasSukun || $hasShortVowel || $isLongVowel || $hasShadda) {
                $knownCount++;
            }

            if ($hasShadda) {
                $pattern .= '0';
            }
            if ($hasSukun) {
                $pattern .= '0';
                continue;
            }
            if ($hasShortVowel) {
                $pattern .= '1';
                if (preg_match('/[\x{064B}-\x{064D}]/u', $marks) === 1) {
                    $pattern .= '0';
                }
                continue;
            }
            if ($isLongVowel) {
                $pattern .= '0';
                continue;
            }

            $next = $clusters[$i + 1] ?? null;
            $nextIsLongVowel = $next !== null
                && $next['type'] === 'letter'
                && self::isLongVowelAt($clusters, $i + 1)
                && $next['marks'] === '';

            if ($inferUnmarked) {
                $pattern .= '1';
                $inferredCount++;
            } elseif ($nextIsLongVowel) {
                $pattern .= '1';
                $inferredCount++;
            } else {
                $pattern .= '?';
            }
        }

        if ($saturateEnd && $pattern !== '' && $clusters !== []) {
            for ($j = count($clusters) - 1; $j >= 0; $j--) {
                if (($clusters[$j]['type'] ?? '') === 'space') {
                    continue;
                }
                if (($clusters[$j]['type'] ?? '') === 'letter'
                    && self::isArabicLetter($clusters[$j]['char'] ?? '')
                    && preg_match('/[\x{064E}-\x{0650}]/u', $clusters[$j]['marks'] ?? '') === 1
                    && !str_contains($clusters[$j]['marks'] ?? '', self::SUKUN)
                    && str_ends_with($pattern, '1')) {
                    $pattern .= '0';
                }
                break;
            }
        }

        $coverage = $letterCount === 0 ? 0.0 : $knownCount / $letterCount;
        $warnings = [];
        if ($letterCount === 0) {
            $warnings[] = 'No Arabic letters were found.';
        } elseif ($coverage < 0.8) {
            $warnings[] = 'The text is missing diacritics; the binary pattern contains assumptions and meter ranking is approximate.';
        }
        if ($inferUnmarked && $inferredCount > 0) {
            $warnings[] = sprintf('%d unmarked letter(s) were assumed to be vowelled.', $inferredCount);
        }

        return [
            'pattern' => $pattern,
            'coverage' => round($coverage, 4),
            'letter_count' => $letterCount,
            'inferred_count' => $inferredCount,
            'warnings' => $warnings,
        ];
    }

    // ------------------------------------------------------------------
    // Cleaning / parsing
    // ------------------------------------------------------------------

    private static function clean(string $text): string
    {
        self::assertValidUtf8($text);

        if (class_exists(\ArabicSupport\Arabic::class)) {
            if (method_exists(\ArabicSupport\Arabic::class, 'stripTatweel')) {
                $text = \ArabicSupport\Arabic::stripTatweel($text);
            }
            if (method_exists(\ArabicSupport\Arabic::class, 'deepTrim')) {
                $text = \ArabicSupport\Arabic::deepTrim($text);
            }
        }

        $text = strtr($text, self::PRESENTATION_FORMS);
        $text = str_replace([
            self::TATWEEL, "\u{200B}", "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{FEFF}",
            "\u{202A}", "\u{202B}", "\u{202C}", "\u{202D}", "\u{202E}",
            "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}",
        ], '', $text);
        $text = preg_replace('/[،؛؟!,.:;"“”‘’«»()\[\]{}<>…—–\-_\/\\\\|*]+/u', ' ', $text) ?? $text;

        return preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);
    }

    /** Canonical mark order: shadda before vowels/tanween. */
    private static function normalizeMarkOrder(string $text): string
    {
        $text = preg_replace('/([\x{064B}-\x{0650}\x{0652}])\x{0651}/u', self::SHADDA . '$1', $text) ?? $text;

        return preg_replace('/\x{0651}\x{0651}+/u', self::SHADDA, $text) ?? $text;
    }

    /** @return list<array{char:string,marks:string}> */
    private static function parseWord(string $word): array
    {
        preg_match_all('/(.)(\p{M}*)/su', $word, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $m) {
            $out[] = ['char' => $m[1], 'marks' => $m[2]];
        }

        return $out;
    }

    private static function fold(string $char): string
    {
        return match ($char) {
            'أ', 'إ', 'آ', 'ٱ' => 'ا',
            default => $char,
        };
    }

    /** @param list<array{char:string,marks:string}> $clusters */
    private static function plain(array $clusters): string
    {
        $s = '';
        foreach ($clusters as $c) {
            $s .= self::fold($c['char']);
        }

        return $s;
    }

    /** @return list<string> */
    private static function chars(string $s): array
    {
        return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @param list<array{char:string,marks:string}> $clusters */
    private static function isBare(array $clusters): bool
    {
        foreach ($clusters as $c) {
            if ($c['marks'] !== '') {
                return false;
            }
        }

        return true;
    }

    private static function hasVowel(string $marks): bool
    {
        return preg_match('/[\x{064B}-\x{0650}\x{0670}]/u', $marks) === 1;
    }

    private static function isSun(string $char): bool
    {
        return $char !== '' && str_contains(self::SUN_LETTERS, $char);
    }

    /** @param array{char:string,marks:string} $c @return array{char:string,marks:string} */
    private static function ensureVowel(array $c, string $vowel): array
    {
        if (!self::hasVowel($c['marks']) && !str_contains($c['marks'], self::SUKUN)) {
            $c['marks'] .= $vowel;
        }

        return $c;
    }

    // ------------------------------------------------------------------
    // Word-level rules
    // ------------------------------------------------------------------

    /**
     * Match a lexicon stem allowing up to two proclitic letters before it.
     *
     * @param array<string,mixed> $table
     * @return array{stem:string,k:int}|null
     */
    private static function matchStem(array $table, string $plain): ?array
    {
        $chars = self::chars($plain);
        for ($k = 0; $k <= 2 && $k < count($chars); $k++) {
            if ($k > 0 && !str_contains(self::PROCLITICS . 'ت', $chars[$k - 1])) {
                break;
            }
            $stem = implode('', array_slice($chars, $k));
            if (isset($table[$stem])) {
                return ['stem' => $stem, 'k' => $k];
            }
        }

        return null;
    }

    /**
     * @param list<array{char:string,marks:string}> $clusters
     * @return list<array{char:string,marks:string}>
     */
    private static function lexicalFixes(array $clusters): array
    {
        $plain = self::plain($clusters);
        $insert = [];
        $drop = [];

        $match = self::matchStem(self::LEXICAL_FIXES, $plain);
        if ($match !== null) {
            $spec = self::LEXICAL_FIXES[$match['stem']];
            foreach ($spec['insert'] ?? [] as $i) {
                $insert[] = $i + $match['k'];
            }
            foreach ($spec['drop'] ?? [] as $i) {
                $drop[] = $i + $match['k'];
            }
        } elseif (preg_match('/^[وفبكلت]{0,2}(?:ال|ل)له(?:م)?$/u', $plain) === 1) {
            // الله / بالله / لله / اللهم: pronounced with a long ā after the second lām.
            $letters = self::chars($plain);
            $h = array_search('ه', array_reverse($letters, true), true);
            if ($h !== false) {
                $insert[] = $h - 1;
            }
        }
        if ($insert === [] && $drop === []) {
            return $clusters;
        }

        rsort($drop);
        foreach ($drop as $i) {
            if (isset($clusters[$i])) {
                array_splice($clusters, $i, 1);
                $insert = array_map(static fn (int $x): int => $x > $i ? $x - 1 : $x, $insert);
            }
        }
        rsort($insert);
        foreach (array_unique($insert) as $i) {
            if (!isset($clusters[$i])) {
                continue;
            }
            $following = $clusters[$i + 1] ?? null;
            if (str_contains($clusters[$i]['marks'], self::DAGGER)
                || ($following !== null && $following['char'] === 'ا' && !str_contains($following['marks'], self::FATHATAN))) {
                continue;
            }
            $clusters[$i] = self::ensureVowel($clusters[$i], self::FATHA);
            array_splice($clusters, $i + 1, 0, [['char' => 'ا', 'marks' => '']]);
        }

        return $clusters;
    }

    /**
     * Dagger alif (ٰ) = fatha + long ā; on alif maqsura it is redundant.
     *
     * @param list<array{char:string,marks:string}> $clusters
     * @return list<array{char:string,marks:string}>
     */
    private static function daggerAlif(array $clusters): array
    {
        $out = [];
        foreach ($clusters as $c) {
            if (!str_contains($c['marks'], self::DAGGER)) {
                $out[] = $c;
                continue;
            }
            $c['marks'] = str_replace(self::DAGGER, '', $c['marks']);
            if ($c['char'] === 'ى' || $c['char'] === 'ا') {
                $out[] = $c;
                continue;
            }
            $c = self::ensureVowel($c, self::FATHA);
            $out[] = $c;
            $out[] = ['char' => 'ا', 'marks' => ''];
        }

        return $out;
    }

    /**
     * Fathatan may sit on the preceding letter (…بًا) or on the carrier alif (…با ً).
     * Both are pronounced as fatha + nun-sakin; the carrier is not a long vowel.
     *
     * @param list<array{char:string,marks:string}> $clusters
     * @return list<array{char:string,marks:string}>
     */
    private static function tanweenCarriers(array $clusters): array
    {
        $count = count($clusters);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $c = $clusters[$i];
            $isCarrier = ($c['char'] === 'ا' || $c['char'] === 'ى' || $c['char'] === 'ٱ')
                && str_contains($c['marks'], self::FATHATAN)
                && $out !== [];
            if ($isCarrier) {
                $last = count($out) - 1;
                $out[$last] = self::ensureVowel($out[$last], self::FATHA);
                $out[] = ['char' => 'ن', 'marks' => self::SUKUN];
                continue;
            }
            $out[] = $c;
            if (str_contains($c['marks'], self::FATHATAN)
                && isset($clusters[$i + 1])
                && in_array($clusters[$i + 1]['char'], ['ا', 'ى'], true)
                && $clusters[$i + 1]['marks'] === ''
                && $i + 2 === $count) {
                $i++;
            }
        }

        return $out;
    }

    /**
     * @param list<array{char:string,marks:string}> $c
     * @return array{k:int,article:bool,sun:bool,fatha?:bool}|null
     */
    private static function detectWasl(array $c): ?array
    {
        $n = count($c);
        $bare = self::isBare($c);
        for ($k = 0; $k <= 2 && $k + 1 < $n; $k++) {
            if ($k > 0) {
                $p = $c[$k - 1];
                if ($p['char'] === '' || !str_contains(self::PROCLITICS, $p['char'])
                    || preg_match('/[\x{064E}\x{0650}]/u', $p['marks']) !== 1) {
                    break;
                }
            }
            $a = $c[$k];
            if ($a['char'] !== 'ا' && $a['char'] !== 'ٱ') {
                continue;
            }
            if (str_contains($a['marks'], self::SUKUN) || str_contains($a['marks'], self::SHADDA)) {
                continue;
            }
            $b = $c[$k + 1];
            $c2 = $c[$k + 2] ?? null;

            if ($b['char'] === 'ل') {
                $lamSukun = str_contains($b['marks'], self::SUKUN);
                $lamBare = !self::hasVowel($b['marks']) && !$lamSukun;
                $sunShadda = $c2 !== null && str_contains($c2['marks'], self::SHADDA) && self::isSun($c2['char']);
                $assumed = $k === 0 && $lamBare && $n >= 3
                    && !in_array(self::plain($c), self::NOT_ARTICLE_BARE, true);
                if ($lamSukun || ($lamBare && $sunShadda) || $a['char'] === 'ٱ' || $assumed) {
                    $sun = $c2 !== null && self::isSun($c2['char']) && !$lamSukun
                        && ($sunShadda || $assumed);
                    return ['k' => $k, 'article' => true, 'sun' => $sun];
                }
                if (str_contains($b['marks'], self::SHADDA)) {
                    // الَّذِي / الَّتِي / الَّذِينَ: a single geminated lām (wasl alif, no article lām to drop).
                    return ['k' => $k, 'article' => false, 'sun' => false, 'fatha' => true];
                }
                continue;
            }
            if (str_contains($b['marks'], self::SUKUN)) {
                return ['k' => $k, 'article' => false, 'sun' => false];
            }
            if ($k === 0 && $bare && in_array(self::plain($c), self::LEXICAL_WASL, true)) {
                return ['k' => 0, 'article' => false, 'sun' => false];
            }
        }

        return null;
    }

    /**
     * @param list<array{char:string,marks:string}> $c
     * @param array{k:int,article:bool,sun:bool,fatha?:bool} $w
     * @return list<array{char:string,marks:string}>
     */
    private static function applyWasl(array $c, array $w, bool $elide): array
    {
        $k = $w['k'];
        if ($w['article']) {
            $lam = $k + 1;
            if ($w['sun']) {
                array_splice($c, $lam, 1);
                $marks = str_replace(self::SHADDA, '', $c[$lam]['marks']);
                $c[$lam]['marks'] = self::SHADDA . $marks;
            } elseif ($c[$lam]['marks'] === '') {
                $c[$lam]['marks'] = self::SUKUN;
            }
        }
        if ($elide) {
            array_splice($c, $k, 1);
        } else {
            $c[$k] = self::ensureVowel($c[$k], ($w['article'] || ($w['fatha'] ?? false)) ? self::FATHA : self::KASRA);
        }

        return $c;
    }

    /**
     * Iltiqā' al-sākinayn: the word before an elided wasl alif.
     *
     * @param list<array{char:string,marks:string}> $c
     * @return list<array{char:string,marks:string}>
     */
    private static function bridge(array $c): array
    {
        $last = count($c) - 1;
        if ($last < 0) {
            return $c;
        }
        $marks = $c[$last]['marks'];
        $char = $c[$last]['char'];

        if (preg_match('/[\x{064B}-\x{064D}]/u', $marks) === 1) {
            $c[$last]['marks'] = strtr($marks, [
                self::FATHATAN => self::FATHA,
                self::DAMMATAN => self::DAMMA,
                self::KASRATAN => self::KASRA,
            ]);
            $c[] = ['char' => 'ن', 'marks' => self::KASRA];

            return $c;
        }
        if (str_contains($marks, self::SUKUN)) {
            $c[$last]['marks'] = str_replace(self::SUKUN, self::KASRA, $marks);

            return $c;
        }
        if ($marks === '' && $last > 0 && self::isLongVowelCluster($c[$last - 1], $char)) {
            array_pop($c);
        }

        return $c;
    }

    /** @param array{char:string,marks:string} $prev */
    private static function isLongVowelCluster(array $prev, string $char): bool
    {
        return match ($char) {
            'ا', 'ى' => str_contains($prev['marks'], self::FATHA),
            'و' => str_contains($prev['marks'], self::DAMMA),
            'ي' => str_contains($prev['marks'], self::KASRA),
            default => false,
        };
    }

    /**
     * Silent alif after plural waw (كَتَبُوا / دَعَوْا).
     *
     * @param list<array{char:string,marks:string}> $c
     * @return list<array{char:string,marks:string}>
     */
    private static function dropSilentAlif(array $c): array
    {
        $n = count($c);
        if ($n >= 2 && $c[$n - 1]['char'] === 'ا' && $c[$n - 1]['marks'] === ''
            && $c[$n - 2]['char'] === 'و'
            && preg_match('/[\x{064E}\x{0650}\x{064F}]/u', $c[$n - 2]['marks']) !== 1
            && $n >= 3) {
            array_pop($c);
        }

        return $c;
    }

    /**
     * @param list<list<array{char:string,marks:string}>> $words
     */
    private static function render(array $words): string
    {
        $parts = [];
        foreach ($words as $clusters) {
            $out = '';
            foreach ($clusters as $item) {
                $ch = match ($item['char']) {
                    'أ', 'إ' => 'ء',
                    'ٱ' => 'ا',
                    default => $item['char'],
                };
                $marks = $item['marks'];
                if (!self::isArabicLetter($ch)) {
                    $out .= $ch . $marks;
                    continue;
                }

                $tanween = null;
                foreach ([self::FATHATAN => self::FATHA, self::DAMMATAN => self::DAMMA, self::KASRATAN => self::KASRA] as $mark => $vowel) {
                    if (str_contains($marks, $mark)) {
                        $tanween = $vowel;
                        $marks = str_replace($mark, '', $marks);
                        break;
                    }
                }
                if ($tanween !== null) {
                    $hasShadda = str_contains($marks, self::SHADDA);
                    $marks = str_replace(self::SHADDA, '', $marks);
                    $out .= $hasShadda
                        ? $ch . self::SUKUN . $ch . $tanween . 'ن' . self::SUKUN
                        : $ch . $tanween . 'ن' . self::SUKUN;
                    continue;
                }
                if (str_contains($marks, self::SHADDA)) {
                    $out .= $ch . self::SUKUN . $ch . str_replace(self::SHADDA, '', $marks);
                } else {
                    $out .= $ch . $marks;
                }
            }
            $parts[] = $out;
        }

        return implode(' ', $parts);
    }

    // ------------------------------------------------------------------
    // Scan helpers
    // ------------------------------------------------------------------

    private static function assertValidUtf8(string $text): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('Arabic text must be valid UTF-8.');
        }
    }

    /** @return list<array{type:string,value:string,char?:string,marks?:string}> */
    private static function clusters(string $text): array
    {
        preg_match_all('/(\p{L})(\p{M}*)|(\s+)|(.)(\p{M}*)/u', $text, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $m) {
            if (($m[3] ?? '') !== '') {
                $out[] = ['type' => 'space', 'value' => $m[3]];
            } elseif (($m[1] ?? '') !== '') {
                $out[] = ['type' => 'letter', 'value' => $m[1] . ($m[2] ?? ''), 'char' => $m[1], 'marks' => $m[2] ?? ''];
            } else {
                $out[] = ['type' => 'other', 'value' => ($m[4] ?? '') . ($m[5] ?? ''), 'char' => $m[4] ?? '', 'marks' => $m[5] ?? ''];
            }
        }

        return $out;
    }

    private static function isArabicLetter(string $char): bool
    {
        return preg_match('/\p{Arabic}/u', $char) === 1 && preg_match('/\p{L}/u', $char) === 1;
    }

    /** @param list<array{type:string,value:string,char?:string,marks?:string}> $clusters */
    private static function isLongVowelAt(array $clusters, int $index): bool
    {
        $item = $clusters[$index] ?? null;
        if ($item === null || $item['type'] !== 'letter' || $item['marks'] !== '') {
            return false;
        }
        $previous = $clusters[$index - 1] ?? null;
        if ($previous === null || $previous['type'] !== 'letter') {
            return false;
        }

        return match ($item['char']) {
            'ا', 'ى' => str_contains($previous['marks'], self::FATHA),
            'و' => str_contains($previous['marks'], self::DAMMA),
            'ي' => str_contains($previous['marks'], self::KASRA),
            default => false,
        };
    }
}
