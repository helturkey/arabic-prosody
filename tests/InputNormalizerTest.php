<?php

declare(strict_types=1);

namespace Arud\Tests;

use Arud\InputNormalizer;
use PHPUnit\Framework\TestCase;

final class InputNormalizerTest extends TestCase
{
    public function testKinds(): void
    {
        $this->assertSame('shatr', InputNormalizer::parse('قَدْ جَاءَ')['kind']);
        $this->assertSame('shatr', InputNormalizer::parse(['قَدْ جَاءَ'])['kind']);
        $this->assertSame('bayt', InputNormalizer::parse(['أ', 'ب'])['kind']);
        $this->assertSame('bayt', InputNormalizer::parse('أ' . "\t" . 'ب')['kind']);
        $this->assertSame('bayt', InputNormalizer::parse('أ ... ب')['kind']);
        $this->assertSame('bayt', InputNormalizer::parse('أ', 'ب')['kind']);
        $this->assertSame('poem', InputNormalizer::parse(['أ', 'ب', 'ج', 'د'])['kind']);
        $this->assertSame('poem', InputNormalizer::parse("أ\nب\nج\nد")['kind']);
        $this->assertSame('poem', InputNormalizer::parse("أ * ب\nج * د")['kind']);
    }

    public function testRejectsAmbiguousOrBrokenShapes(): void
    {
        $bad = [
            '', "  \n ", [], ['a', 'b', 'c'], "a\nb\nc", "a * b\nc", 'a * b * c',
            [['a']], [['a', 'b', 'c']], ['sadr' => 'a'], ['verses' => []], [1, 2], ['a', ''],
        ];
        foreach ($bad as $input) {
            try {
                InputNormalizer::parse($input);
                $this->fail('Expected rejection of ' . json_encode($input, JSON_UNESCAPED_UNICODE));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSizeLimits(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputNormalizer::parse(['a', str_repeat('ب', InputNormalizer::MAX_HEMISTICH_BYTES)]);
    }

    public function testTooManyVerses(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputNormalizer::parse(array_fill(0, (InputNormalizer::MAX_VERSES + 1) * 2, 'ب'));
    }

    public function testInvalidUtf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputNormalizer::parse(["\xB1\x31", 'a']);
    }

    public function testTwoArgumentFormRequiresStringSadr(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputNormalizer::parse(['a'], 'b');
    }
}
