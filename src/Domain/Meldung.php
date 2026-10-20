<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

use ITHilbert\Alerts\Exceptions\UngueltigerSchluessel;

/** Öffentliche Eingabe des Ports: eine Störung, die ein Package oder die App melden will. */
final readonly class Meldung
{
    /** @param array<string, scalar|null> $details */
    public function __construct(
        public string $schluessel,
        public string $titel,
        public ?string $fehlertext = null,
        public array $details = [],
    ) {
        if (! Fingerabdruck::istGueltig($schluessel)) {
            throw UngueltigerSchluessel::fuer($schluessel);
        }
    }
}
