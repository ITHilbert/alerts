<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

use Illuminate\Http\Request;

/** Hält die gerade verarbeitete HTTP-Anfrage; als scoped Binding pro Anfrage zurückgesetzt (Octane-sicher). */
final class AnfrageHalter
{
    private ?Request $anfrage = null;

    public function setze(Request $anfrage): void
    {
        $this->anfrage = $anfrage;
    }

    public function vergiss(): void
    {
        $this->anfrage = null;
    }

    public function anfrage(): ?Request
    {
        return $this->anfrage;
    }
}
