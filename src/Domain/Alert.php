<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

use Carbon\CarbonImmutable;

/** Versandfertige Nachricht: Vorfall plus Kontext, Wiederholungen und Freigabe-Link. */
final readonly class Alert
{
    public function __construct(
        public Vorfall $vorfall,
        public string $projekt,
        public string $umgebung,
        public int $wiederholungen,
        public CarbonImmutable $zeitpunkt,
        public string $freigabeLink,
    ) {}
}
