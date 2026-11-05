<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

/** Stand der Zählung einer Quelle im laufenden Zeitfenster. */
final readonly class Fensterstand
{
    /**
     * @param  int  $anzahl  Gezählte Ereignisse im Fenster, einschließlich des gerade gezählten.
     * @param  list<string>  $eintraege  Die ersten gemerkten Einträge in Reihenfolge des Eintreffens.
     */
    public function __construct(
        public int $anzahl,
        public array $eintraege,
    ) {}
}
