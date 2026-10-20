<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

/** Erfasste Störung, unabhängig von der Quelle. */
final readonly class Vorfall
{
    /** @param array<string, scalar|null> $details */
    public function __construct(
        public Fingerabdruck $fingerabdruck,
        public AlertQuelle $quelle,
        public string $titel,
        public ?string $fehlertext = null,
        public ?string $ort = null,
        public ?string $url = null,
        public array $details = [],
    ) {}
}
