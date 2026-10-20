<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Domain\Fingerabdruck;

interface Drosselung
{
    /**
     * Versucht, die Sperre für den Fingerabdruck zu setzen.
     *
     * @return int|null Anzahl unterdrückter Auftreten seit der letzten Meldung, wenn jetzt gemeldet werden
     *                  soll; null, wenn der Fingerabdruck gesperrt ist (Auftreten wurde gezählt).
     */
    public function betrete(Fingerabdruck $fingerabdruck): ?int;

    /** Hebt Sperre und Zähler auf. false, wenn der Fingerabdruck unbekannt ist. */
    public function gibFrei(string $fingerabdruck): bool;

    /** Hebt alle Sperren auf und liefert deren Anzahl. */
    public function gibAlleFrei(): int;
}
