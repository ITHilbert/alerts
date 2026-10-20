<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Contracts;

use ITHilbert\Alerts\Domain\Meldung;

interface AlertReporter
{
    /** Meldet eine Störung. Wirft nie wegen eines Versandfehlers. */
    public function melde(Meldung $meldung): void;
}
