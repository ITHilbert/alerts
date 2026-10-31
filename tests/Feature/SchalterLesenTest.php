<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Application\AktuelleAnfrage;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Dashboard #355: Die Bool-Schalter werden beim Lesen gewertet, nicht erst in der Paket-Config. Der String steht hier
 * direkt in der Config (`config([...])`, wie aus einer veröffentlichten Alt-Config), nicht in der Umgebung.
 *
 * „Wirkung“ eines Schalters = was er freigibt: `enabled` alle vier Quellen und den Port (5), ein Erfassungs-Schalter
 * seine Quelle (1), ein Fehlertext-Schalter den Fehlertext im Kanal (1). 0 = aus.
 */
final class SchalterLesenTest extends TestCase
{
    private const FEHLERTEXT = 'GEHEIMER-FEHLERTEXT';

    /** Config-Schlüssel (ohne `alerts.`) → Vorgabe, wenn der Schlüssel fehlt. */
    private const VORGABEN = [
        'enabled' => true,
        'erfassung.exceptions' => true,
        'erfassung.log' => true,
        'erfassung.http_404' => true,
        'erfassung.http_403' => true,
        'mail.fehlertext' => true,
        'telegram.fehlertext' => false,
    ];

    private const QUELLE_JE_SCHALTER = [
        'erfassung.exceptions' => 'exception',
        'erfassung.log' => 'log',
        'erfassung.http_404' => '404',
        'erfassung.http_403' => '403',
    ];

    private object $anfrageZaehler;

    private int $lauf = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->anfrageZaehler = new class implements AktuelleAnfrage
        {
            public int $aufrufe = 0;

            public function url(): ?string
            {
                $this->aufrufe++;

                return null;
            }
        };
        $this->app->instance(AktuelleAnfrage::class, $this->anfrageZaehler);

        foreach (array_keys(self::VORGABEN) as $schluessel) {
            $this->setze($schluessel, true);
        }
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function setze(string $schluessel, mixed $wert): void
    {
        $this->app['config']->set('alerts.'.$schluessel, $wert);
    }

    /** Entfernt die Schlüssel ganz aus der Config (nicht auf null setzen). */
    private function entferne(string ...$schluessel): void
    {
        $alerts = $this->app['config']->get('alerts');
        Arr::forget($alerts, $schluessel);
        $this->app['config']->set('alerts', $alerts);

        foreach ($schluessel as $s) {
            $this->assertFalse(Arr::has($this->app['config']->get('alerts'), $s), "Aufbau: {$s} muss fehlen.");
        }
    }

    private function nutzer(): User
    {
        return (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);
    }

    private function erfassungen(): int
    {
        return $this->anfrageZaehler->aufrufe;
    }

    /** Löst die Quelle aus; gibt zurück, ob dafür eine Mail verschickt wurde. */
    private function quelleFeuert(string $quelle): bool
    {
        $n = ++$this->lauf;
        $this->app['cache']->flush();
        $vorher = count($this->mails());

        match ($quelle) {
            'log' => Log::critical("Import {$n} abgebrochen!x{$n}", ['alert_key' => "log.{$n}"]),
            'exception' => $this->meldeException($this->ausnahme()),
            '404' => $this->get("/gibt-es-nicht-{$n}", ['Referer' => 'http://localhost/start']),
            '403' => $this->actingAs($this->nutzer())->get('/verboten'),
            'port' => $this->app->make(AlertReporter::class)->melde(new Meldung(schluessel: "port.{$n}", titel: 'Testmeldung')),
        };

        return count($this->mails()) > $vorher;
    }

    /** Schickt eine Port-Meldung mit Fehlertext; gibt zurück, ob er im Kanal steht. */
    private function fehlertextSichtbar(string $kanal): bool
    {
        $n = ++$this->lauf;
        $this->app['cache']->flush();
        $this->app->make(AlertReporter::class)->melde(new Meldung(schluessel: "text.{$n}", titel: 'Testmeldung', fehlertext: self::FEHLERTEXT));

        $text = $kanal === 'mail'
            ? $this->mailText($this->mail(count($this->mails()) - 1))
            : $this->telegramText(count($this->telegramAnfragen()) - 1);

        return str_contains($text, self::FEHLERTEXT);
    }

    private function wirkung(string $schluessel): int
    {
        if ($schluessel === 'enabled') {
            $summe = 0;
            foreach (['log', 'exception', '404', '403', 'port'] as $quelle) {
                $summe += (int) $this->quelleFeuert($quelle);
            }

            return $summe;
        }

        if ($schluessel === 'mail.fehlertext') {
            return (int) $this->fehlertextSichtbar('mail');
        }

        if ($schluessel === 'telegram.fehlertext') {
            return (int) $this->fehlertextSichtbar('telegram');
        }

        return (int) $this->quelleFeuert(self::QUELLE_JE_SCHALTER[$schluessel]);
    }

    private function wirkungAn(string $schluessel): int
    {
        return $schluessel === 'enabled' ? 5 : 1;
    }

    // ── Datenquellen ─────────────────────────────────────────────────────────

    /** @return list<string> */
    private static function ausWerte(): array
    {
        return ['false', '0', 'off', 'no', '', 'FALSE', 'Off', 'NO'];
    }

    /** @return list<string> */
    private static function anWerte(): array
    {
        return ['true', '1', 'on', 'yes', 'TRUE', 'On', 'YES'];
    }

    /** @return array<string, array{string, string}> */
    private static function kreuz(array $werte): array
    {
        $daten = [];
        foreach (array_keys(self::VORGABEN) as $schluessel) {
            foreach ($werte as $wert) {
                $daten["{$schluessel}='{$wert}'"] = [$schluessel, $wert];
            }
        }

        return $daten;
    }

    /** @return array<string, array{string, string}> */
    public static function schalterMitAusWert(): array
    {
        return self::kreuz(self::ausWerte());
    }

    /** @return array<string, array{string, string}> */
    public static function schalterMitAnWert(): array
    {
        return self::kreuz(self::anWerte());
    }

    /** @return array<string, array{string}> */
    public static function schalter(): array
    {
        $daten = [];
        foreach (array_keys(self::VORGABEN) as $schluessel) {
            $daten[$schluessel] = [$schluessel];
        }

        return $daten;
    }

    /** @return array<string, array{string}> */
    public static function quellen(): array
    {
        return ['Log' => ['log'], 'Exception' => ['exception'], '404' => ['404'], '403' => ['403'], 'Port' => ['port']];
    }

    /** @return array<string, array{string}> */
    public static function erfassteQuellen(): array
    {
        return ['Log' => ['log'], 'Exception' => ['exception']];
    }

    /** @return array<string, array{string, string}> Schalter, deren Erfasser AktuelleAnfrage::url() aufrufen */
    public static function zaehlbar(): array
    {
        $daten = [];
        foreach (['enabled', 'erfassung.exceptions', 'erfassung.log'] as $schluessel) {
            foreach (self::ausWerte() as $wert) {
                $daten["{$schluessel}='{$wert}'"] = [$schluessel, $wert];
            }
        }

        return $daten;
    }

    // ── AK1: Wertung beim Lesen ──────────────────────────────────────────────

    // AK1 – false, 0, off, no, leer (Groß-/Kleinschreibung egal) sind aus: Erfassung und Versand
    #[DataProvider('schalterMitAusWert')]
    public function test_string_aus_wert_schaltet_aus(string $schluessel, string $wert): void
    {
        $this->setze($schluessel, 'on');
        $this->assertSame($this->wirkungAn($schluessel), $this->wirkung($schluessel), 'Gegenprobe: Schalter "on" ist an.');

        $this->setze($schluessel, $wert);

        $this->assertSame(0, $this->wirkung($schluessel), "{$schluessel}='{$wert}' muss aus sein.");
    }

    // AK1 – true, 1, on, yes sind an
    #[DataProvider('schalterMitAnWert')]
    public function test_string_an_wert_schaltet_an(string $schluessel, string $wert): void
    {
        $this->setze($schluessel, 'off');
        $this->assertSame(0, $this->wirkung($schluessel), 'Gegenprobe: Schalter "off" ist aus.');

        $this->setze($schluessel, $wert);

        $this->assertSame($this->wirkungAn($schluessel), $this->wirkung($schluessel), "{$schluessel}='{$wert}' muss an sein.");
    }

    // AK1 – „Erfassung“: bei aus wird die Quelle gar nicht erst erfasst (nicht erst der Versand gesperrt)
    #[DataProvider('zaehlbar')]
    public function test_string_aus_wert_loest_keine_erfassung_aus(string $schluessel, string $wert): void
    {
        $quellen = $schluessel === 'enabled' ? ['log', 'exception'] : [self::QUELLE_JE_SCHALTER[$schluessel]];

        $this->setze($schluessel, 'on');
        foreach ($quellen as $quelle) {
            $vorher = $this->erfassungen();
            $this->quelleFeuert($quelle);
            $this->assertGreaterThan($vorher, $this->erfassungen(), "Gegenprobe: {$quelle} wird bei 'on' erfasst.");
        }

        $this->setze($schluessel, $wert);
        foreach ($quellen as $quelle) {
            $vorher = $this->erfassungen();
            $this->quelleFeuert($quelle);
            $this->assertSame($vorher, $this->erfassungen(), "{$quelle} darf bei {$schluessel}='{$wert}' nicht erfasst werden.");
        }
    }

    // ── AK2: veröffentlichte Alt-Config ──────────────────────────────────────

    // AK2 – enabled = 'off': nichts erfasst, nichts versendet (Log, Exception, Status, Port)
    #[DataProvider('quellen')]
    public function test_alt_config_enabled_off_versendet_nichts(string $quelle): void
    {
        config(['alerts.enabled' => 'on']);
        $this->assertTrue($this->quelleFeuert($quelle), "Gegenprobe: {$quelle} verschickt bei enabled='on'.");
        $mails = count($this->mails());
        $telegram = count($this->telegramAnfragen());

        config(['alerts.enabled' => 'off']);
        $this->quelleFeuert($quelle);

        $this->assertCount($mails, $this->mails(), "{$quelle} darf bei enabled='off' keine Mail verschicken.");
        $this->assertCount($telegram, $this->telegramAnfragen(), "{$quelle} darf bei enabled='off' kein Telegram verschicken.");
    }

    // AK2 – enabled = 'off': nichts erfasst (Log, Exception; 404/403/Port rufen AktuelleAnfrage nicht auf, ihr Ergebnis prüft der Versand-Test)
    #[DataProvider('erfassteQuellen')]
    public function test_alt_config_enabled_off_erfasst_nichts(string $quelle): void
    {
        config(['alerts.enabled' => 'on']);
        $vorher = $this->erfassungen();
        $this->quelleFeuert($quelle);
        $this->assertGreaterThan($vorher, $this->erfassungen(), 'Gegenprobe: bei enabled=on erfasst.');

        config(['alerts.enabled' => 'off']);
        $vorher = $this->erfassungen();
        $this->quelleFeuert($quelle);

        $this->assertSame($vorher, $this->erfassungen(), "{$quelle} darf bei enabled='off' nicht erfasst werden.");
    }

    // ── AK3: eine Stelle ─────────────────────────────────────────────────────

    // AK3 – die Lesestellen enthalten kein eigenes filter_var
    #[DataProvider('lesestellen')]
    public function test_lesestelle_enthaelt_kein_filter_var(string $datei): void
    {
        $pfad = __DIR__.'/../../src/'.$datei;
        $this->assertFileExists($pfad, 'Aufbau: Lesestelle gefunden.');

        $this->assertSame(
            0,
            preg_match('/\bfilter_var\s*\(|FILTER_VALIDATE_BOOL/', (string) file_get_contents($pfad)),
            "{$datei} enthält ein eigenes filter_var/FILTER_VALIDATE_BOOL.",
        );
    }

    /** @return array<string, array{string}> */
    public static function lesestellen(): array
    {
        $dateien = [
            'Application/MeldeAlert.php',
            'Erfassung/ExceptionErfasser.php',
            'Erfassung/StatusErfasser.php',
            'Erfassung/LogErfasser.php',
            'Infrastructure/Notification/AlertNotification.php',
        ];

        return array_combine($dateien, array_map(static fn (string $d): array => [$d], $dateien));
    }

    // AK3 – das Casten steht in genau einer Klasse (ausgenommen die Paket-Config selbst)
    public function test_casten_steht_in_genau_einer_klasse(): void
    {
        $treffer = [];

        foreach ($this->phpDateien(__DIR__.'/../../src') as $datei) {
            if (str_contains($datei, DIRECTORY_SEPARATOR.'Config'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (preg_match('/\bfilter_var\s*\(|FILTER_VALIDATE_BOOL/', (string) file_get_contents($datei)) === 1) {
                $treffer[] = basename($datei);
            }
        }

        $this->assertCount(1, $treffer, 'Erwartet genau eine Klasse mit dem Cast, gefunden: '.implode(', ', $treffer));
    }

    /** @return list<string> */
    private function phpDateien(string $ordner): array
    {
        $dateien = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ordner, RecursiveDirectoryIterator::SKIP_DOTS)) as $datei) {
            if ($datei->getExtension() === 'php') {
                $dateien[] = $datei->getPathname();
            }
        }

        return $dateien;
    }

    // ── AK4: Vorgaben ────────────────────────────────────────────────────────

    // AK4 – fehlt ein Schlüssel, gilt seine bisherige Vorgabe
    #[DataProvider('schalter')]
    public function test_fehlender_schluessel_nutzt_vorgabe(string $schluessel): void
    {
        $this->setze($schluessel, 'off');
        $this->assertSame(0, $this->wirkung($schluessel), 'Gegenprobe: gesetzt "off" ist aus.');

        $this->entferne($schluessel);

        $erwartet = self::VORGABEN[$schluessel] ? $this->wirkungAn($schluessel) : 0;
        $this->assertSame($erwartet, $this->wirkung($schluessel), "Vorgabe von {$schluessel} fehlt.");
    }

    // AK4 – alle Schlüssel fehlen gleichzeitig
    public function test_fehlen_alle_schluessel_gelten_alle_vorgaben(): void
    {
        $this->entferne(...array_keys(self::VORGABEN));

        foreach (self::VORGABEN as $schluessel => $an) {
            $this->assertSame($an ? $this->wirkungAn($schluessel) : 0, $this->wirkung($schluessel), "Vorgabe von {$schluessel}.");
        }
    }

    // ── Randfälle ────────────────────────────────────────────────────────────

    // Randfall 1 – null (Schlüssel vorhanden, aber leer) ist aus, auch wo die Vorgabe „an“ ist
    #[DataProvider('schalter')]
    public function test_null_ist_aus(string $schluessel): void
    {
        $this->assertSame($this->wirkungAn($schluessel), $this->wirkung($schluessel), 'Gegenprobe: true ist an.');

        $this->setze($schluessel, null);

        $this->assertSame(0, $this->wirkung($schluessel), "{$schluessel}=null muss aus sein.");
    }

    // Randfall 2 – echte Bools bleiben unverändert (true = an)
    #[DataProvider('schalter')]
    public function test_echtes_bool_true_ist_an(string $schluessel): void
    {
        $this->setze($schluessel, false);
        $this->assertSame(0, $this->wirkung($schluessel), 'Gegenprobe: false ist aus.');

        $this->setze($schluessel, true);

        $this->assertSame($this->wirkungAn($schluessel), $this->wirkung($schluessel));
    }

    // Randfall 2 – echte Bools bleiben unverändert (false = aus)
    #[DataProvider('schalter')]
    public function test_echtes_bool_false_ist_aus(string $schluessel): void
    {
        $this->assertSame($this->wirkungAn($schluessel), $this->wirkung($schluessel), 'Gegenprobe: true ist an.');

        $this->setze($schluessel, false);

        $this->assertSame(0, $this->wirkung($schluessel));
    }
}
