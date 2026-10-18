# Release status — 1.0.0-rc.1

**Ready:** API shape, input validation and limits, deterministic matcher, rule-derived feet, position-aware grammar, CLI, 61 tests (all green on PHP 8.3 with PHPUnit 9.6; CI matrix targets 8.1–8.4 with PHPUnit 9.6/10/11), corpus regression gate, PHPStan (level 5) config.

**Not yet done (blocks a final 1.0.0):**
1. Scholar review of `MeterCatalog` / `FootCatalog` (see docs/GRAMMAR.md).
2. Reviewed corpus: ≥ 5 verses per meter (10 meters have none).
3. PHPStan **level 5 passes** (2.3.1); level 8 needs the array-shaped candidate rows turned into value objects (planned). Tests were executed on PHP 8.3 / PHPUnit 9.6 only; the 8.1/8.2/8.4 and PHPUnit 10/11 CI legs are configured but unexecuted — run `composer ci && composer analyse` once on your CI.
4. Qāfiya analysis, tadwīr, and diacritization are out of scope.
