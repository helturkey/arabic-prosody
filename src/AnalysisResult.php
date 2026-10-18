<?php

declare(strict_types=1);

namespace Arud;

final class AnalysisResult implements \JsonSerializable
{
    /**
     * @param list<array<string,mixed>> $candidates
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $input,
        public readonly string $normalized,
        public readonly string $prosodicSpelling,
        public readonly string $pattern,
        public readonly float $diacriticCoverage,
        public readonly int $inferredLetters,
        public readonly array $candidates,
        public readonly string $confidence,
        public readonly array $warnings,
        public readonly string $side = 'either',
    ) {
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'input' => $this->input,
            'normalized' => $this->normalized,
            'prosodic_spelling' => $this->prosodicSpelling,
            'pattern' => $this->pattern,
            'diacritic_coverage' => $this->diacriticCoverage,
            'inferred_letters' => $this->inferredLetters,
            'side' => $this->side,
            'best_match' => $this->candidates[0] ?? null,
            'candidates' => $this->candidates,
            'confidence' => $this->confidence,
            'warnings' => $this->warnings,
        ];
    }

    /** @return array<string,mixed>|null */
    public function bestMatch(): ?array
    {
        return $this->candidates[0] ?? null;
    }

    /** Strict accessor for code that needs a decision: high confidence AND an exact/compatible parse. */
    public function reliableMatch(): ?array
    {
        $best = $this->bestMatch();

        return $best !== null
            && $this->confidence === 'high'
            && in_array($best['match_type'] ?? null, ['exact', 'compatible'], true)
            ? $best
            : null;
    }
}
