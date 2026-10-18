# Third-party notices and research references

PHP Arud is an independently written implementation. The projects below were reviewed as design, API, or prosody-pattern references; this repository does not vendor their source code or their corpus files.

- **PyArud** — <https://github.com/cnemri/pyarud>, MIT License. Referenced for its explanation of binary scansion, meter terminology, and example verses. No PyArud source files are copied into this package.
- **TAFILAT** — <https://github.com/NoorBayan/Tafilat>, whose repository README states the dataset is MIT-licensed and requests attribution. TAFILAT files are not bundled in this release; verify the source data and its specific attribution before importing any of it.
- **PHP Arabic Support** — <https://github.com/helturkey/php-arabic-support>, MIT License. Optional runtime interoperability is through its documented public methods; this package does not copy its implementation.

The small reference-verse JSON fixture cites the source projects for each record. Some text examples reproduce short lines from classical poetry (public-domain underlying works); digital vocalization and source labels may need scholar review. They are engineering regression fixtures, not an expert-certified gold dataset.

## Poetspedia (موسوعة الشعراء)

<https://poetspedia.com/meters> (pages 1–3) was consulted **only** for the list of meter names (to build `MeterCatalog::extended()` / `resolveName()`) and for the relative number of poems per meter, which defines the `prior` rank used as a last tie-breaker (tawil, kamil, basit, wafir, khafif, saree, mutaqarib, rajaz, munsarih, ramal, mujtath, hazaj, madid, mutadarik, muqtadib, mudari). No text, verses, tables or rules were copied from that site. Verify their terms before adding any of their content.

## 1.0 rewrite
The 1.0 rule tables were written from the standard textbook account of the Khalilian meters and are independent of the projects above. Comparison with PyArud and Aruudy was based on their public READMEs only; neither project was executed.
