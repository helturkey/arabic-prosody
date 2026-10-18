# Corpus format and protocol

`tests/fixtures/reference-verses.json`:

```json
{ "schema_version": 2, "records": [
  { "id": "unique-slug", "meter": "tawil", "poet": "…", "sadr": "…", "ajuz": "…",
    "opening_verse": false, "review_status": "unreviewed|reviewed",
    "provenance": "where the text and the label come from", "source_note": "" } ] }
```

* Text must be fully vocalized; `meter` must be a key of `MeterCatalog::all()`.
* `opening_verse: true` marks a muṣarraʿ (taṣrīʿ) verse; the test suite asserts the flag.
* Use public-domain works only; record provenance honestly. Records typed from memory are marked as such and stay `unreviewed` until checked against a critical edition.
* `php tools/evaluate-corpus.php [file] --json --min-top1=1.0 --min-hemistich=0.9` is the regression gate (exit 1 on regression or any wrong high-confidence result). Pass a second corpus file to evaluate held-out data separately from the development set.

**Current gap:** target ≥ 5 reviewed verses per classical meter; today 6 of 16 meters are covered.
