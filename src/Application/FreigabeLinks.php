<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Domain\Fingerabdruck;

interface FreigabeLinks
{
    public function erzeuge(Fingerabdruck $fingerabdruck): string;
}
