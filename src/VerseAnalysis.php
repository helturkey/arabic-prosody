<?php

declare(strict_types=1);

namespace Arud;

/** Result for one bayt (two hemistichs). Also readable like an array for backward compatibility. */
final class VerseAnalysis implements \JsonSerializable, \ArrayAccess
{
    /**
     * @param list<array<string,mixed>> $candidates
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly AnalysisResult $sadr,
        public readonly AnalysisResult $ajuz,
        public readonly array $candidates,
        public readonly string $confidence,
        public readonly array $warnings,
    ) {
    }

    /** @return array<string,mixed>|null */
    public function bestMatch(): ?array
    {
        return $this->candidates[0] ?? null;
    }

    public function reliableMatch(): ?array
    {
        $best = $this->bestMatch();

        return $best !== null && $this->confidence === 'high'
            && in_array($best['match_type'] ?? null, ['exact', 'compatible'], true) ? $best : null;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'sadr' => $this->sadr,
            'ajuz' => $this->ajuz,
            'best_match' => $this->bestMatch(),
            'candidates' => $this->candidates,
            'confidence' => $this->confidence,
            'warnings' => $this->warnings,
        ];
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('VerseAnalysis is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('VerseAnalysis is immutable.');
    }
}
