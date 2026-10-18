# Security

* The library performs no I/O, `eval`, or network access; CLI I/O is limited to the file/STDIN you name.
* Untrusted text is limited by `InputNormalizer` (UTF-8 only, 2000 bytes per hemistich, 2000 verses, 2 MB total).
* Approximate matching is bounded (distance cap, length pruning, ≤ 20 000 templates per form).
* Report vulnerabilities privately to the maintainers (use the repository's private advisory feature). Please do not open public issues for security reports.
