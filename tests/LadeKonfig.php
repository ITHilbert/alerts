<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests;

use Illuminate\Support\Env;

/** Lädt die Paket-Config mit gesetzten Umgebungsvariablen (nur für die Dauer des Ladens). */
trait LadeKonfig
{
    /** @param array<string, string> $env @return array<mixed> */
    private function konfig(array $env = []): array
    {
        $repo = Env::getRepository();
        $vorher = [];
        foreach ($env as $name => $wert) {
            $vorher[$name] = $repo->get($name);
            $repo->set($name, $wert);
        }

        try {
            return require __DIR__.'/../src/Config/alerts.php';
        } finally {
            foreach ($vorher as $name => $wert) {
                $wert === null ? $repo->clear($name) : $repo->set($name, $wert);
            }
        }
    }
}
