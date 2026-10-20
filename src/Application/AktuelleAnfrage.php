<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

interface AktuelleAnfrage
{
    /** URL der laufenden HTTP-Anfrage ohne Query-String; null außerhalb von HTTP. */
    public function url(): ?string;
}
