# Grammar model and verification status

## Representation
* `1` = mutaḥarrik (vowelled consonant), `0` = sākin (sukūn or long-vowel letter).
* A **foot** has a base pattern (`FootCatalog::FEET`). A **variant** (zihāf/ʿilla) is derived from it by operations on 1-based positions: `sakin`, `del`, `append`. Nothing is typed as a bit string, so a variant cannot disagree with its rule.
* A **form** (`MeterCatalog`) lists its feet and, per position, the licensed variants: `hashw` (inner feet), `arud` (last foot of the sadr), `darb` (last foot of the ajuz or of a single line). The sound foot (`s`) is always allowed.
* `shape: bayt` = two hemistichs with different endings; `shape: line` = one self-contained line (mashṭūr, manhūk).
* `tier: classical | rare`. Rare forms are attested but uncommon; their sets are deliberately permissive.
* `labels.darb` gives traditional sub-meter names (أحذ الكامل، الكامل المقطوع).
* Taṣrīʿ: if the sadr does not fit the ʿarūḍ set but fits the ḍarb set with the same kind of ʿilla, the verse is accepted with `tasri = true` (cost +1).

## Matching
Deterministic depth-first parse of the observed pattern against the slots; `?` (unknown letter) matches either bit and downgrades the result to `compatible`. Candidates are ranked by: match type → score → tier → number of modified feet → popularity prior → key. The prior (see THIRD_PARTY_NOTICES.md) is only a last tie-breaker; when several meters match, all are listed in `ambiguous_with` and confidence is lowered.

## Confidence
* `high` — exact parse, classical form, ≥ 90 % diacritic coverage, no other classical meter matches.
* `medium` — exact/compatible, ≥ 80 % coverage, and unique or strictly fewer modifications.
* `low` — everything else, including every approximate result.
* Poems: `high` needs ≥ 3 verses, ≥ 90 % consistency, ≥ 1 verse matching only that meter, and no tie.

## Verification status (please read)
| Layer | Status |
|---|---|
| Foot variants | Derived by rule; checked against an independent hand-written table (`tests/FootCatalogTest.php`). Names and rule definitions follow the common textbook account. **Not scholar-reviewed.** |
| Classical tamm forms | Encoded from the standard tables. Arūḍ/ḍarb sets are permissive where sources differ. **Not scholar-reviewed.** |
| Rare forms (`tier: rare`) | Partially verified. Confidence capped at `medium`. |
| Corpus | 15 verses / 6 meters, `review_status: unreviewed`. Hand-typed vocalization. |
| Meters with **no** corpus verse yet | madid, hazaj, rajaz, saree, munsarih, khafif, mudari, muqtadib, mujtath, mutadarik, and all majzuʾ/mashṭūr/manhūk forms. They are covered only by self-consistency tests (every licensed variant parses), which prove the code, not the linguistics. |

## Review protocol (for a prosody scholar)
1. Run `php bin/arud meters --json` and review `hashw/arud/darb` per form in `src/MeterCatalog.php`.
2. For each foot variant, compare `src/FootCatalog.php` with the reference you trust.
3. Add verses with citations to `tests/fixtures/reference-verses.json`, set `review_status` to `reviewed` and record the reviewer.
4. `composer test:corpus` must stay at 100 % verse top-1 with zero wrong high-confidence results.
