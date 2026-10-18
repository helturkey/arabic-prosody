# Contributing

1. `composer install`, then `composer ci` and `composer analyse` must pass.
2. **Rules are data, not code**: change meters in `src/MeterCatalog.php`, feet/zihāf in `src/FootCatalog.php` (operations on the base pattern, never hand-typed bit strings).
3. Every new/changed zihāf needs an entry in the independent hand-written table in `tests/FootCatalogTest.php`.
4. Every new meter form needs verses in `tests/fixtures/reference-verses.json` with provenance (see `docs/CORPUS.md`).
5. Do not paste text, tables, or data from other projects without checking their license (see `THIRD_PARTY_NOTICES.md`).
