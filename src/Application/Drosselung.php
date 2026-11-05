<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Domain\Fensterstand;
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

    /**
     * Zählt ein Ereignis der Quelle im Zeitfenster. Das Fenster beginnt mit dem ersten gezählten Ereignis
     * und läuft nach $fensterSekunden ab; danach zählt die Quelle von vorn.
     *
     * @param  string  $quelle  Kennung der Quelle, bereits pseudonymisiert (nie eine IP im Klartext).
     * @param  string  $eintrag  Wird gemerkt, solange weniger als $merken Einträge vorliegen.
     * @return Fensterstand|null null, wenn nicht gezählt werden konnte (z. B. Cache-Ausfall).
     */
    public function zaehleImFenster(string $quelle, string $eintrag, int $fensterSekunden, int $merken): ?Fensterstand;

    /** Hebt Sperre und Zähler auf. false, wenn der Fingerabdruck unbekannt ist. */
    public function gibFrei(string $fingerabdruck): bool;

    /** Hebt alle Sperren auf und liefert deren Anzahl. */
    public function gibAlleFrei(): int;
}
