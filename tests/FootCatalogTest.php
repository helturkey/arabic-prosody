<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\FootCatalog;
use PHPUnit\Framework\TestCase;

final class FootCatalogTest extends TestCase
{
    /**
     * Independent, hand-written expectations (NOT computed from the rules):
     * each entry was derived on paper from the traditional definition of the zihaf/'illa.
     *
     * @return array<string,array<string,string>>
     */
    private function golden(): array
    {
        return [
            'faulun' => ['qabd' => '1101', 'thalm' => '1010', 'tharm' => '101', 'hadhf' => '110', 'qasr' => '1100', 'batr' => '11'],
            'mafailun' => ['qabd' => '110110', 'kaff' => '110101', 'shakl' => '11011', 'kharm' => '101010', 'kharb' => '10101', 'hadhf' => '11010', 'qasr' => '110100'],
            'failun' => ['khabn' => '1110', 'qat' => '1010', 'khabn_qat' => '110', 'tadhyil' => '101100'],
            'mustafilun' => ['khabn' => '110110', 'tay' => '101110', 'khabal' => '11110', 'qat' => '101010', 'khabn_qat' => '11010', 'tadhyil' => '10101100'],
            'mutafailun' => ['idmar' => '1010110', 'waqs' => '110110', 'khazl' => '101110', 'qat' => '111010', 'idmar_qat' => '101010', 'hadhadh' => '1110', 'tadhyil' => '11101100', 'tarfil' => '111011010'],
            'mafaalatun' => ['asb' => '1101010', 'aql' => '110110', 'naqs' => '110101', 'qataf' => '11010'],
            'mafoolatu' => ['khabn' => '110101', 'tay' => '101101', 'khabal' => '11101', 'kasf' => '101010', 'waqf' => '1010100', 'tay_kasf' => '10110', 'salm' => '1010'],
            'failatun' => ['khabn' => '111010', 'kaff' => '101101', 'shakl' => '11101', 'hadhf' => '10110', 'hadhf_khabn' => '1110', 'qasr' => '101100', 'batr' => '1010', 'tasbigh' => '10110100'],
        ];
    }

    public function testBasePatterns(): void
    {
        $expected = [
            'faulun' => '11010', 'mafailun' => '1101010', 'failun' => '10110', 'mustafilun' => '1010110',
            'mutafailun' => '1110110', 'mafaalatun' => '1101110', 'mafoolatu' => '1010101', 'failatun' => '1011010',
        ];
        foreach ($expected as $key => $pattern) {
            $this->assertSame($pattern, FootCatalog::get($key)['pattern'], $key);
        }
    }

    public function testEveryVariantMatchesTheHandWrittenTable(): void
    {
        $golden = $this->golden();
        foreach (FootCatalog::all() as $foot => $info) {
            $this->assertArrayHasKey($foot, $golden, "golden table misses foot {$foot}");
            $this->assertEqualsCanonicalizing(array_keys($golden[$foot]), array_keys($info['variants']), "variant list of {$foot}");
            foreach ($info['variants'] as $key => $variant) {
                $this->assertSame($golden[$foot][$key], $variant['pattern'], "{$foot}/{$key}");
            }
        }
    }

    public function testStructuralSanity(): void
    {
        foreach (FootCatalog::all() as $foot => $info) {
            $this->assertMatchesRegularExpression('/^[01]+$/', $info['pattern']);
            foreach ($info['variants'] as $key => $variant) {
                $ops = $variant['ops'];
                $this->assertMatchesRegularExpression('/^[01]+$/', $variant['pattern'], "{$foot}/{$key}");
                $this->assertNotSame($info['pattern'], $variant['pattern'], "{$foot}/{$key} must differ from the sound foot");
                // A deletion can never make a foot longer; only tadhyil/tasbigh/tarfil append.
                $expectedLength = strlen($info['pattern']) - count($ops['del']) + strlen($ops['append']);
                $this->assertSame($expectedLength, strlen($variant['pattern']), "{$foot}/{$key} length");
                $this->assertContains($variant['kind'], ['zihaf', 'illa']);
            }
        }
    }

    public function testNoDeletedLetterIsMutaharrikUnlessRuleSaysSo(): void
    {
        // Rules that delete a mutaharrik letter by definition.
        $mutaharrikDeleters = ['waqs', 'thalm', 'tharm', 'kharm', 'kharb', 'aql', 'khazl', 'hadhf', 'hadhadh', 'batr', 'qataf', 'tay_kasf', 'kasf', 'salm', 'hadhf_khabn', 'shakl', 'khabn_qat'];
        foreach (FootCatalog::all() as $foot => $info) {
            foreach ($info['variants'] as $key => $variant) {
                if (in_array($key, $mutaharrikDeleters, true)) {
                    continue;
                }
                foreach ($variant['ops']['del'] as $position) {
                    $this->assertSame('0', $info['pattern'][$position - 1], "{$foot}/{$key} deletes a mutaharrik letter at {$position}");
                }
            }
        }
    }

    public function testOptionsAlwaysIncludeSalimAndRejectUnknownVariants(): void
    {
        $options = FootCatalog::options('faulun', ['qabd', 'qabd']);
        $this->assertSame(['s', 'qabd'], array_column($options, 'key'));
        $this->expectException(\InvalidArgumentException::class);
        FootCatalog::options('faulun', ['no_such_rule']);
    }

    public function testUnknownFootThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FootCatalog::get('nope');
    }
}
