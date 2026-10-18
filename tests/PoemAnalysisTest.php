<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\Analyzer;
use PHPUnit\Framework\TestCase;

final class PoemAnalysisTest extends TestCase
{
    /** @return list<array{string,string}> */
    private function basit(): array
    {
        return [
            ['إِنَّ الْعُيُونَ الَّتِي فِي طَرْفِهَا حَوَرٌ', 'قَتَلْنَنَا ثُمَّ لَمْ يُحْيِينَ قَتْلَانَا'],
            ['السَّيْفُ أَصْدَقُ إِنْبَاءً مِنَ الْكُتُبِ', 'فِي حَدِّهِ الْحَدُّ بَيْنَ الْجِدِّ وَاللَّعِبِ'],
            ['لَيْسَ الْغَرِيبُ غَرِيبَ الشَّأْمِ وَالْيَمَنِ', 'إِنَّ الْغَرِيبَ غَرِيبُ اللَّحْدِ وَالْكَفَنِ'],
        ];
    }

    public function testPairsFlatListAndTextAreEquivalent(): void
    {
        $a = new Analyzer();
        $pairs = $this->basit();
        $flat = array_merge(...$pairs);
        $text = implode("\n", array_map(static fn (array $p): string => $p[0] . ' *** ' . $p[1], $pairs));
        $twoLines = implode("\n", $flat);
        $assoc = array_map(static fn (array $p): array => ['sadr' => $p[0], 'ajuz' => $p[1]], $pairs);
        foreach ([$pairs, $flat, $text, $twoLines, $assoc, ['verses' => $pairs]] as $input) {
            $poem = $a->analyzePoem($input);
            $this->assertCount(3, $poem->verses);
            $this->assertSame('basit', $poem->dominantMeter);
            $this->assertSame('basit:tam', $poem->dominantForm);
            $this->assertSame(1.0, $poem->consistency);
            $this->assertSame('high', $poem->confidence);
            $this->assertSame([], $poem->deviations);
        }
    }

    public function testDeviatingVerseIsReported(): void
    {
        $pairs = $this->basit();
        $pairs[] = ['قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ', 'بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ فَحَوْمَلِ'];
        $poem = (new Analyzer())->analyzePoem($pairs);
        $this->assertSame('basit', $poem->dominantMeter);
        $this->assertSame([4], $poem->deviations);
        $this->assertSame(0.75, $poem->consistency);
        $this->assertNotSame('high', $poem->confidence);
        $this->assertNotEmpty($poem->warnings);
    }

    public function testSingleVerseCapsPoemConfidence(): void
    {
        $poem = (new Analyzer())->analyzePoem([$this->basit()[0]]);
        $this->assertNotSame('high', $poem->confidence);
    }

    public function testVotesAreReportedPerMeter(): void
    {
        $poem = (new Analyzer())->analyzePoem($this->basit());
        $this->assertSame('basit', $poem->meters[0]['key']);
        $this->assertSame(3, $poem->meters[0]['verses_matched']);
    }

    public function testBadPoemInputsThrow(): void
    {
        $a = new Analyzer();
        foreach ([[], ['a', 'b', 'c'], [['x']], "one line only"] as $bad) {
            try {
                $a->analyzePoem($bad);
                $this->fail('Expected InvalidArgumentException for ' . json_encode($bad, JSON_UNESCAPED_UNICODE));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testPoemDetectMeterThroughTheCommonEntryPoint(): void
    {
        $this->assertSame('basit', (new Analyzer())->detectMeter($this->basit()));
    }
}
