<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

use ITHilbert\Alerts\Exceptions\UngueltigerSchluessel;

final readonly class Fingerabdruck
{
    public const MUSTER = '/^[a-z0-9][a-z0-9._-]{0,99}$/';

    public string $wert;

    public function __construct(string $wert)
    {
        if (! self::istGueltig($wert)) {
            throw UngueltigerSchluessel::fuer($wert);
        }

        $this->wert = $wert;
    }

    public static function istGueltig(string $wert): bool
    {
        return preg_match(self::MUSTER, $wert) === 1;
    }

    /** Identität einer Exception: Klasse + Datei + Zeile, nie die Meldung. */
    public static function ausException(\Throwable $e): self
    {
        return self::abgeleitet('exception', (new \ReflectionClass($e))->getShortName(), $e::class.'|'.$e->getFile().'|'.$e->getLine());
    }

    /** Stabiler Fingerabdruck aus Präfix, lesbarem Label und dem Material, das die Identität bestimmt. */
    public static function abgeleitet(string $praefix, string $label, string $material): self
    {
        $slug = trim(substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-'), 0, 40), '-');
        $hash = substr(hash('sha256', $material), 0, 12);

        return new self($praefix.'.'.($slug !== '' ? $slug.'.' : '').$hash);
    }
}
