<?php

declare(strict_types=1);

namespace Arud;

final class PoemAnalysis implements \JsonSerializable
{
    /**
     * @param list<VerseAnalysis> $verses
     * @param list<array<string,mixed>> $meters ranked poem-level meter support
     * @param list<int> $deviations 1-based verse numbers that do not fit the dominant meter
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly array $verses,
        public readonly array $meters,
        public readonly ?string $dominantMeter,
        public readonly ?string $dominantForm,
        public readonly float $consistency,
        public readonly array $deviations,
        public readonly string $confidence,
        public readonly array $warnings,
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'verse_count' => count($this->verses),
            'dominant_meter' => $this->dominantMeter,
            'dominant_form' => $this->dominantForm,
            'consistency' => $this->consistency,
            'deviations' => $this->deviations,
            'confidence' => $this->confidence,
            'meters' => $this->meters,
            'warnings' => $this->warnings,
            'verses' => $this->verses,
        ];
    }
}
