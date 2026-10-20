<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Domain\Alert;

interface AlertVersand
{
    /** Verschickt über alle konfigurierten Kanäle; ein Kanalfehler stoppt die übrigen nicht. */
    public function sende(Alert $alert): void;
}
