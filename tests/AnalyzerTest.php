<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\AnalysisResult;
use Arud\Analyzer;
use Arud\Grammar;
use Arud\MeterCatalog;
use Arud\PoemAnalysis;
use Arud\VerseAnalysis;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    private const IMRU_SADR = 'قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ';
    private const IMRU_AJUZ = 'بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ فَحَوْمَلِ';

    /** @return list<array<string,mixed>> */
    private function corpus(): array
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/fixtures/reference-verses.json'), true, 512, JSON_THROW_ON_ERROR);

        return $payload['records'];
    }

    public function testEveryCorpusVerseIsClassifiedExactlyWithHighConfidence(): void
    {
        $analyzer = new Analyzer();
        foreach ($this->corpus() as $r) {
            $v = $analyzer->analyzeVerse($r['sadr'], $r['ajuz']);
            $best = $v->bestMatch();
            $this->assertNotNull($best, $r['id']);
            $this->assertSame($r['meter'], $best['key'], $r['id']);
            $this->assertSame('exact', $best['match_type'], $r['id']);
            $this->assertSame('high', $v->confidence, $r['id']);
            $this->assertSame($r['opening_verse'], $best['tasri'], $r['id'] . ' tasri flag');
        }
    }

    public function testVerseInputShapesAreEquivalent(): void
    {
        $a = new Analyzer();
        $two = $a->analyzeVerse(self::IMRU_SADR, self::IMRU_AJUZ);
        $list = $a->analyzeVerse([self::IMRU_SADR, self::IMRU_AJUZ]);
        $assoc = $a->analyzeVerse(['sadr' => self::IMRU_SADR, 'ajuz' => self::IMRU_AJUZ]);
        $arabicKeys = $a->analyzeVerse(['صدر' => self::IMRU_SADR, 'عجز' => self::IMRU_AJUZ]);
        $oneLine = $a->analyzeVerse(self::IMRU_SADR . ' *** ' . self::IMRU_AJUZ);
        foreach ([$list, $assoc, $arabicKeys, $oneLine] as $other) {
            $this->assertSame($two->bestMatch()['key'], $other->bestMatch()['key']);
            $this->assertSame($two->sadr->pattern, $other->sadr->pattern);
            $this->assertSame($two->ajuz->pattern, $other->ajuz->pattern);
        }
        $this->assertInstanceOf(VerseAnalysis::class, $two);
        $this->assertSame('tawil', $two['best_match']['key'], 'array-style access still works');
        $this->assertTrue(isset($two['candidates']));
    }

    public function testAnalyzeAutoDetectsTheShape(): void
    {
        $a = new Analyzer();
        $this->assertInstanceOf(AnalysisResult::class, $a->analyzeAuto(self::IMRU_SADR));
        $this->assertInstanceOf(AnalysisResult::class, $a->analyzeAuto([self::IMRU_SADR]));
        $this->assertInstanceOf(VerseAnalysis::class, $a->analyzeAuto([self::IMRU_SADR, self::IMRU_AJUZ]));
        $this->assertInstanceOf(VerseAnalysis::class, $a->analyzeAuto(self::IMRU_SADR . '   ***   ' . self::IMRU_AJUZ));
        $poem = [[self::IMRU_SADR, self::IMRU_AJUZ], [self::IMRU_SADR, self::IMRU_AJUZ]];
        $this->assertInstanceOf(PoemAnalysis::class, $a->analyzeAuto($poem));
        $this->assertSame('tawil', $a->detectMeter(self::IMRU_SADR));
        $this->assertSame('tawil', $a->detectMeter([self::IMRU_SADR, self::IMRU_AJUZ]));
        $this->assertSame('tawil', $a->detectVerseMeter(self::IMRU_SADR, self::IMRU_AJUZ));
    }

    public function testAnalyzeVerseRejectsNonVerseInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Analyzer())->analyzeVerse(self::IMRU_SADR);
    }

    public function testHemistichSideSelectsTheRole(): void
    {
        $a = new Analyzer();
        $sadr = $a->analyze(self::IMRU_SADR, 5, false, 'sadr');
        $this->assertSame('tawil', $sadr->bestMatch()['key']);
        // Tawil ʿarūḍ cannot be maḥdhūf, but its ḍarb can: the ajuz of the next verse proves the role matters.
        $ajuzAsSadr = $a->analyze(self::IMRU_AJUZ, 5, false, 'sadr');
        $this->assertSame('tawil', $a->analyze(self::IMRU_AJUZ, 5, false, 'ajuz')->bestMatch()['key']);
        $this->assertNotNull($ajuzAsSadr);
    }

    public function testEveryFormAcceptsItsOwnSoundAndSingleVariantPatterns(): void
    {
        $a = new Analyzer();
        $checked = 0;
        foreach (MeterCatalog::all() as $key => $meter) {
            foreach ($meter['forms'] as $form) {
                $role = $form['shape'] === 'line' ? 'line' : 'sadr';
                $side = $form['shape'] === 'line' ? 'either' : 'sadr';
                $slots = Grammar::slots($form, $role);
                // every single option at every position, other positions sound
                foreach ($slots as $position => $options) {
                    foreach ($options as $option) {
                        $pattern = '';
                        foreach ($slots as $i => $opts) {
                            $pattern .= $i === $position ? $option['pattern'] : $opts[0]['pattern'];
                        }
                        $keys = array_column($a->rankPattern($pattern, 50, $side), 'key');
                        $this->assertContains($key, $keys, "{$form['id']} position " . ($position + 1) . " {$option['key']} => {$pattern}");
                        $checked++;
                    }
                }
            }
        }
        $this->assertGreaterThan(200, $checked);
    }

    public function testRajazAndKamilOverlapIsReportedNotHidden(): void
    {
        $a = new Analyzer();
        $rajazSound = '1010110' . '1010110' . '1010110';
        $ranked = $a->rankPattern($rajazSound, 5, 'sadr');
        $keys = array_column($ranked, 'key');
        $this->assertSame('rajaz', $keys[0], 'fewest modifications wins');
        $this->assertContains('kamil', $keys, 'Kamil with idmar is binary-identical to Rajaz');
        $this->assertContains('kamil', $ranked[0]['ambiguous_with']);

        $kamilSound = '1110110' . '1110110' . '1110110';
        $this->assertSame('kamil', $a->rankPattern($kamilSound, 5, 'sadr')[0]['key']);
        $this->assertNotContains('rajaz', array_column($a->rankPattern($kamilSound, 5, 'sadr'), 'key'), 'mutafailun is not a rajaz foot');
    }

    public function testRajazWithKhabnIsRecognized(): void
    {
        $keys = array_column((new Analyzer())->rankPattern('110110110110110110', 10, 'sadr'), 'key');
        $this->assertContains('rajaz', $keys);
    }

    public function testNoMatchIsHonest(): void
    {
        $strict = new Analyzer(true, false);
        $this->assertSame([], $strict->rankPattern('1111111111111', 5));
        $approx = (new Analyzer())->rankPattern('1111111111111', 5);
        $this->assertNotEmpty($approx);
        $this->assertSame('approximate', $approx[0]['match_type']);
    }

    public function testUnvocalizedTextNeverGetsHighConfidence(): void
    {
        $a = new Analyzer();
        $r = $a->analyze('قفا نبك من ذكرى حبيب ومنزل');
        $this->assertNotSame('high', $r->confidence);
        $this->assertNull($a->detectMeter('قفا نبك من ذكرى حبيب ومنزل'));
        $this->assertNotEmpty($r->warnings);
    }

    public function testInfersUnmarkedLettersOnlyWhenAsked(): void
    {
        $a = new Analyzer();
        $this->assertGreaterThan(0, $a->analyze('قفا نبك', 5, true)->inferredLetters);
    }

    public function testRareFormsCanBeDisabled(): void
    {
        $pattern = '1010110' . '10110' . '11010'; // basit mukhalla' sadr
        $withRare = array_column((new Analyzer(true))->rankPattern($pattern, 20, 'sadr'), 'form_id');
        $without = array_column((new Analyzer(false))->rankPattern($pattern, 20, 'sadr'), 'form_id');
        $this->assertContains('basit:mukhalla', $withRare);
        $this->assertNotContains('basit:mukhalla', $without);
    }

    public function testOpeningVerseTasriIsDetected(): void
    {
        $a = new Analyzer();
        $v = $a->analyzeVerse('قُمْ لِلْمُعَلِّمِ وَفِّهِ التَّبْجِيلَا', 'كَادَ الْمُعَلِّمُ أَنْ يَكُونَ رَسُولَا');
        $this->assertSame('kamil', $v->bestMatch()['key']);
        $this->assertTrue($v->bestMatch()['tasri']);
        $this->assertContains('التصريع', $v->bestMatch()['modifications']);
    }

    public function testMismatchedHalvesProduceAWarning(): void
    {
        $a = new Analyzer();
        $v = $a->analyzeVerse(self::IMRU_SADR, 'إِذَا الشَّعْبُ يَوْمًا أَرَادَ الْحَيَاةَ');
        $this->assertNotSame('high', $v->confidence);
        $this->assertNotEmpty(array_filter($v->warnings, static fn (string $w): bool => str_contains($w, 'different meters')));
    }

    public function testFeetAreExplained(): void
    {
        $best = (new Analyzer())->analyzeVerse(self::IMRU_SADR, self::IMRU_AJUZ)->bestMatch();
        $this->assertCount(4, $best['feet']['sadr']);
        $this->assertSame('mafailun', $best['feet']['sadr'][3]['base']);
        $this->assertSame('qabd', $best['feet']['sadr'][3]['variant']);
        $this->assertSame('arud', $best['feet']['sadr'][3]['role']);
        $this->assertSame('darb', $best['feet']['ajuz'][3]['role']);
        $this->assertSame(['القبض'], $best['modifications']);
    }

    public function testApproximateFootStatuses(): void
    {
        $a = new Analyzer();
        $v = $a->analyzeVerse(self::IMRU_SADR, 'بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ');
        $best = $v->bestMatch();
        $this->assertSame('approximate', $best['match_type']);
        $statuses = array_column($best['feet']['ajuz'], 'status');
        $this->assertNotSame(['ok', 'ok', 'ok', 'ok'], $statuses);
    }

    public function testSubMeterLabelsFollowTheDarb(): void
    {
        $a = new Analyzer();
        // Kamil majzu' with a hadhadh ḍarb is traditionally «أحذ الكامل».
        $ajuz = array_column($a->rankPattern('1110110' . '1110', 5, 'ajuz'), 'label_ar', 'key');
        $this->assertSame('أحذ الكامل', $ajuz['kamil']);
    }

    public function testFreeVerse(): void
    {
        $a = new Analyzer();
        $r = $a->analyzeFreeVerse('فَعُولُنْ فَعُولُنْ فَعُولُنْ');
        $this->assertSame('free', $r->side);
        $this->assertSame('mutaqarib', $r->bestMatch()['key']);
        $this->assertSame(3, $r->bestMatch()['foot_count']);
        $this->assertNotEmpty($r->warnings);
        $none = $a->analyzeFreeVerse('بَيْتٌ');
        $this->assertIsArray($none->candidates);
    }

    public function testValidation(): void
    {
        $a = new Analyzer();
        foreach ([
            fn () => $a->analyze('قَدْ', 0),
            fn () => $a->analyze('قَدْ', 5, false, 'middle'),
            fn () => $a->analyze(str_repeat('ب', 3000)),
            fn () => $a->analyze("\xB1\x31"),
            fn () => $a->rankPattern('10x'),
            fn () => $a->analyzePoem([self::IMRU_SADR]),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testResultsAreJsonSerializable(): void
    {
        $a = new Analyzer();
        $v = $a->analyzeVerse(self::IMRU_SADR, self::IMRU_AJUZ);
        $poem = $a->analyzePoem([[self::IMRU_SADR, self::IMRU_AJUZ], [self::IMRU_SADR, self::IMRU_AJUZ]]);
        foreach ([$a->analyze(self::IMRU_SADR), $v, $poem] as $result) {
            $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->assertJson($json);
        }
    }

    public function testNormalizeAndSpellingProxies(): void
    {
        $a = new Analyzer();
        $this->assertSame('احمد', $a->normalize('أحمد'));
        $this->assertSame('1010', $a->scan('هَذَا')['pattern']);
        $this->assertSame('هَاذَا', $a->prosodicSpelling('هَذَا'));
    }
}
