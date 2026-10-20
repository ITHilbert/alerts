<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

use ITHilbert\Alerts\Application\AktuelleAnfrage;

final class LaravelAktuelleAnfrage implements AktuelleAnfrage
{
    public function __construct(private readonly AnfrageHalter $halter) {}

    public function url(): ?string
    {
        return $this->halter->anfrage()?->url();
    }
}
