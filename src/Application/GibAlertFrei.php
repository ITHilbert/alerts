<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Domain\Fingerabdruck;

final class GibAlertFrei
{
    public function __construct(private readonly Drosselung $drosselung) {}

    /** @return bool true, wenn der Fingerabdruck bekannt war und freigegeben wurde */
    public function ausfuehren(string $fingerabdruck): bool
    {
        return Fingerabdruck::istGueltig($fingerabdruck) && $this->drosselung->gibFrei($fingerabdruck);
    }

    public function alle(): int
    {
        return $this->drosselung->gibAlleFrei();
    }
}
