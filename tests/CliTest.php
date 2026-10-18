<?php

declare(strict_types=1);

namespace Arud\Tests;

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private const SADR = 'قِفَا نَبْكِ مِنْ ذِكْرَى حَبِيبٍ وَمَنْزِلِ';
    private const AJUZ = 'بِسِقْطِ اللِّوَى بَيْنَ الدَّخُولِ فَحَوْمَلِ';

    /** @return array{0:int,1:string,2:string} */
    private function cli(array $args, ?string $stdin = null): array
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is disabled.');
        }
        $cmd = array_merge([PHP_BINARY, dirname(__DIR__) . '/bin/arud'], $args);
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($proc);
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proc), $out, $err];
    }

    public function testVerseJson(): void
    {
        [$code, $out] = $this->cli(['verse', self::SADR, self::AJUZ, '--json']);
        $this->assertSame(0, $code);
        $data = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('tawil', $data['best_match']['key']);
        $this->assertSame('high', $data['confidence']);
    }

    public function testPoemFromStdin(): void
    {
        [$code, $out] = $this->cli(['poem', '-', '--json'], self::SADR . ' *** ' . self::AJUZ . "\n" . self::SADR . ' *** ' . self::AJUZ . "\n");
        $this->assertSame(0, $code);
        $this->assertSame('tawil', json_decode($out, true)['dominant_meter']);
    }

    public function testAnalyzeAndMetersAndResolve(): void
    {
        [$code, $out] = $this->cli(['analyze', self::SADR, '--json', '--side=sadr']);
        $this->assertSame(0, $code);
        $this->assertSame('tawil', json_decode($out, true)['best_match']['key']);
        [$code, $out] = $this->cli(['meters', '--json']);
        $this->assertSame(0, $code);
        $this->assertGreaterThan(25, count(json_decode($out, true)));
        [$code, $out] = $this->cli(['resolve', 'مجزوء الكامل', '--json']);
        $this->assertSame('kamil:majzu', json_decode($out, true)['form_id']);
    }

    public function testErrorsUseExitCodeOne(): void
    {
        [$code, , $err] = $this->cli(['verse', 'only-one']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('error:', $err);
        [$code] = $this->cli(['nonsense']);
        $this->assertSame(1, $code);
        [$code] = $this->cli([]);
        $this->assertSame(1, $code);
        [$code, $out] = $this->cli(['--help']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Usage', $out);
    }
}
