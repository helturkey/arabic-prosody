# PHP Arud

Dependency-free Arabic prosody (ʿarūḍ) for PHP 8.1+: from **vocalized** Arabic text to the binary scansion, then rule-based meter detection for **a hemistich, a verse, a whole poem, or a free-verse line**, with foot-by-foot explanations. [العربية](README.ar.md)

> **Status: 1.0.0-rc.1.** Engineering is production-grade (typed, validated, tested, bounded). The *linguistic grammar* is a faithful encoding of the textbook rules but has **not** been reviewed by a prosody scholar, and the regression corpus is small (15 verses, 6 meters). Read [docs/GRAMMAR.md](docs/GRAMMAR.md) and [RELEASE_STATUS.md](RELEASE_STATUS.md) before relying on it for anything beyond assistance.

## Install

```bash
composer require arud/arabic-prosody
```

## Use

```php
use Arud\Analyzer;

$a = new Analyzer();

// One hemistich
$a->analyze('قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ', side: 'sadr');

// One verse — any of these
$a->analyzeVerse($sadr, $ajuz);
$a->analyzeVerse([$sadr, $ajuz]);
$a->analyzeVerse(['sadr' => $sadr, 'ajuz' => $ajuz]);
$a->analyzeVerse("$sadr *** $ajuz");

// A poem — array of pairs, flat even list, ['verses' => …], or text
$poem = $a->analyzePoem([[$s1, $a1], [$s2, $a2], [$s3, $a3]]);
$poem->dominantMeter;   // 'basit'
$poem->consistency;     // 1.0
$poem->deviations;      // [] (1-based verse numbers that do not fit)

// Shape detection / quick answer
$a->analyzeAuto($anything);          // AnalysisResult | VerseAnalysis | PoemAnalysis
$a->detectMeter($anything);          // 'tawil' | … | null (null = no trustworthy answer)

// Free-verse (tafʿīla) line
$a->analyzeFreeVerse('فَعُولُنْ فَعُولُنْ فَعُولُنْ');
```

Every candidate carries `key`, `label_ar` (e.g. «أحذ الكامل»), `match_type` (`exact` | `compatible` | `approximate`), `tier`, `modifications`, `ambiguous_with`, `tasri`, and `feet` — one row per foot with its role (`hashw`/`arud`/`darb`), the rule applied (e.g. القبض) and a status (`ok`, `broken`, `missing`, `extra_bits`).

## CLI

```bash
vendor/bin/arud verse "قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ" "بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ فَحَوْمَلِ"
vendor/bin/arud poem poem.txt --json        # one verse per line "sadr *** ajuz"
vendor/bin/arud meters
vendor/bin/arud resolve "مجزوء الكامل"
```

## What it does well, and what it does not

* **Input must be vocalized.** Unvocalized text yields `?` in the pattern, `compatible` matches at best, and never `high` confidence. It does **not** diacritize; pair it with a diacritizer if needed.
* **Honest about ambiguity.** Kamil with *idmār* is binary-identical to Rajaz; both are returned and flagged (`ambiguous_with`) instead of silently picking one.
* **Position-aware grammar** per meter (ḥashw / ʿarūḍ / ḍarb), with *taṣrīʿ* for opening verses.
* **31 forms**: 16 classical meters with tamm, majzūʾ, mashṭūr, manhūk and mukhallaʿ al-basīṭ where attested. Forms marked `rare` have permissive, partially verified grammars and cap confidence at `medium`. Names from the poetry encyclopedia that are not modelled (muwashshaḥ, dūbayt, mawāliyā …) are listed by `MeterCatalog::extended()` with the reason.
* Not covered: qāfiya analysis, diacritization, non-Khalilian forms, tadwīr across hemistichs.

## Quality gates

```bash
composer ci          # lint + 61 PHPUnit tests + corpus regression gate
composer analyse     # PHPStan level 5
```

Performance (PHP 8.3, laptop-class CPU): ≈0.7 ms per verse when an exact parse exists; ≈5 ms with the bounded approximate fallback; a 500-verse poem in ≈0.4 s, ≈12 MB.

## Security & limits

Inputs are validated (UTF-8, ≤ 2000 bytes per hemistich, ≤ 2000 verses, ≤ 2 MB total). The library itself does no `eval`, filesystem or network access. See [SECURITY.md](SECURITY.md).

## License

MIT. See [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
