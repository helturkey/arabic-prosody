<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\ArabicText;
use PHPUnit\Framework\TestCase;

final class ArabicTextTest extends TestCase
{
    private function p(string $text): string
    {
        return ArabicText::scan($text)['pattern'];
    }

    /** @return array<string,array{0:string,1:string}> */
    private function cases(): array
    {
        return [
            'hamzat qat: إلى is not the article' => ['ذَهَبَ إِلَى', '111110'],
            'إلى before the article' => ['ذَهَبَ إِلَى الْبَيْتِ', '1111101010'],
            'ألا is not the article' => ['قَالَ أَلَا', '101110'],
            'sakin + moon article (iltiqa)' => ['مِنْ الْبَيْتِ', '1101010'],
            'sakin + sun article' => ['مِنْ الشَّمْسِ', '1101010'],
            'long vowel shortened before wasl' => ['فِي الدَّارِ', '101010'],
            'article after waw' => ['وَالشَّمْسُ', '101010'],
            'wasl in ابن after vowel' => ['قَالَ ابْنُ', '101010'],
            'wasl in باسم' => ['بِاسْمِ', '1010'],
            'omitted alif in هذا' => ['هَذَا', '1010'],
            'dagger alif equals written alif' => ['هَٰذَا', '1010'],
            'shadda + tanween' => ['مُحَمَّدٌ', '110110'],
            'plural waw alif is silent' => ['كَتَبُوا', '1110'],
            'tanween on the letter before alif' => ['كِتَابًا', '11010'],
            'الله at line start' => ['اللهُ', '101010'],
            'الله mid line' => ['قَالَ اللهُ', '10101010'],
            'relative pronoun الَّذِي at start' => ['الَّذِي', '10110'],
            'relative pronoun mid line' => ['ذَهَبَ الَّذِي', '1110110'],
        ];
    }

    public function testCuratedCases(): void
    {
        foreach ($this->cases() as $label => [$text, $expected]) {
            $this->assertSame($expected, $this->p($text), $label);
        }
    }

    public function testTanweenOnTheCarrierAlifEqualsTanweenOnTheLetter(): void
    {
        $onLetter = "كِتَاب\u{064B}ا";
        $onAlif = "كِتَابا\u{064B}";
        $this->assertSame($this->p($onLetter), $this->p($onAlif));
        $this->assertSame('11010', $this->p($onAlif));
    }

    public function testDaggerAlifEqualsExplicitAlif(): void
    {
        $this->assertSame($this->p('الرَّحْمَانِ'), $this->p('الرَّحْمٰنِ'));
        $this->assertSame($this->p('هَاذَا'), $this->p('هَٰذَا'));
    }

    public function testNoImpossibleDoubleSakinInsideWords(): void
    {
        foreach (['مِنْ الْبَيْتِ', 'عَنْ الشَّمْسِ', 'قَدْ الْتَقَى', 'كِتَابٌ الْبَيْتِ', 'كِتَابًا الْبَيْتِ', 'هَلْ الْقَوْمُ'] as $text) {
            $this->assertStringNotContainsString('00', substr($this->p($text), 0, -1), $text);
        }
    }

    public function testHamzaFormsAreConsonantsNotLongVowels(): void
    {
        $this->assertSame('1110', $this->p('سَأَلَ'));
        $this->assertSame('10110', $this->p('يَأْكُلُ'));
    }

    public function testTatweelAndMarkOrderDoNotChangeTheResult(): void
    {
        $this->assertSame($this->p('مُحَمَّدٌ'), $this->p("مُحَـمَّـدٌ"));
        $shaddaThenFatha = "\u{0645}\u{0651}\u{064E}\u{062F}";
        $fathaThenShadda = "\u{0645}\u{064E}\u{0651}\u{062F}";
        $this->assertSame($this->p($shaddaThenFatha), $this->p($fathaThenShadda));
    }

    public function testUnvocalizedTextIsNotPretendedToBeKnown(): void
    {
        $scan = ArabicText::scan('قفا نبك من ذكرى حبيب ومنزل');
        $this->assertLessThan(0.5, $scan['coverage']);
        $this->assertStringContainsString('?', $scan['pattern']);
        $this->assertNotEmpty($scan['warnings']);
        $inferred = ArabicText::scan('قفا نبك', true);
        $this->assertStringNotContainsString('?', $inferred['pattern']);
    }

    public function testEmptyAndNonArabicInput(): void
    {
        $this->assertSame('', $this->p(''));
        $this->assertSame('', $this->p('   '));
        $this->assertSame('', $this->p('hello world 123'));
        $this->assertSame(0.0, ArabicText::scan('abc')['coverage']);
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ArabicText::scan("\xB1\x31");
    }

    public function testPunctuationAndBidiMarksAreIgnored(): void
    {
        $this->assertSame($this->p('قَدْ جَاءَ'), $this->p("\u{202B}«قَدْ، جَاءَ!»\u{202C}"));
    }

    public function testNormalizeFoldsAlefForms(): void
    {
        $this->assertSame('احمد', ArabicText::normalize('أحمد'));
        $this->assertSame('اسلام', ArabicText::normalize('إسلام'));
    }

    public function testStripDiacritics(): void
    {
        $this->assertSame('محمد', ArabicText::stripDiacritics('مُحَمَّدٌ'));
        $this->assertSame("محمّد", ArabicText::stripDiacritics('مُحَمَّدٌ', true));
    }
}
