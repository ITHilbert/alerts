<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Application\AktuelleAnfrage;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AK18 (Bool-Schalter der Config, Dashboard #91): Die Werte kommen über die Umgebung (`ALERTS_*`) in die Config,
 * wie in einer echten App; gewertet wird wie `filter_var(…, FILTER_VALIDATE_BOOL)`.
 *
 * „Erfassung“ = der Erfasser ruft nach seiner Schalterprüfung `AktuelleAnfrage::url()` auf (Zähler-Fake).
 * „Versand“ = Mail bzw. Telegram-Anfrage.
 */
final class BoolSchalterTest extends TestCase
{
    /** Schalter → Config-Schlüssel. */
    private const SCHALTER = [
        'ALERTS_ENABLED' => 'enabled',
        'ALERTS_EXCEPTIONS' => 'erfassung.exceptions',
        'ALERTS_LOG' => 'erfassung.log',
        'ALERTS_HTTP_404' => 'erfassung.http_404',
        'ALERTS_HTTP_403' => 'erfassung.http_403',
        'ALERTS_MAIL_FEHLERTEXT' => 'mail.fehlertext',
        'ALERTS_TELEGRAM_FEHLERTEXT' => 'telegram.fehlertext',
    ];

    /** @var list<string> */
    private array $gesetzteEnv = [];

    private object $anfrageZaehler;

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
    }

    protected function tearDown(): void
    {
        foreach ($this->gesetzteEnv as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        parent::tearDown();
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    /** Setzt die Umgebungsvariable (null = nicht gesetzt) und lädt die Bool-Schalter aus der Paket-Config neu. */
    private function umgebung(string $name, ?string $wert): void
    {
        $this->gesetzteEnv[] = $name;

        if ($wert === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            putenv("{$name}={$wert}");
            $_ENV[$name] = $_SERVER[$name] = $wert;
        }

        $paket = require __DIR__.'/../../src/Config/alerts.php';

        foreach (self::SCHALTER as $schluessel) {
            $this->app['config']->set('alerts.'.$schluessel, data_get($paket, $schluessel));
        }
    }

    private function erfassungen(): int
    {
        return $this->anfrageZaehler->aufrufe;
    }

    private function nutzer(): User
    {
        return (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);
    }

    private function port(string $schluessel, ?string $fehlertext = null): void
    {
        $this->app->make(AlertReporter::class)->melde(new Meldung(schluessel: $schluessel, titel: 'Testmeldung', fehlertext: $fehlertext));
    }

    /** Löst die Quelle aus; $n macht Fingerabdruck bzw. Pfad eindeutig. */
    private function loeseAus(string $quelle, int $n): void
    {
        $this->app['cache']->flush();

        match ($quelle) {
            'log' => Log::critical("Import {$n} abgebrochen!x{$n}", ['alert_key' => "log.{$n}"]),
            'exception' => $this->meldeException($n === 1 ? $this->ausnahme() : $this->andereAusnahme()),
            '404' => $this->get("/gibt-es-nicht-{$n}", ['Referer' => 'http://localhost/start']),
            '403' => $this->actingAs($this->nutzer())->get('/verboten'),
        };
    }

    /** @return array<string, array{string}> */
    public static function quellen(): array
    {
        return ['Log' => ['log'], 'Exception' => ['exception'], '404' => ['404'], '403' => ['403']];
    }

    /** @return array<string, array{string}> */
    public static function ausWerte(): array
    {
        return ['false' => ['false'], '0' => ['0'], 'off' => ['off'], 'no' => ['no'], 'leer' => ['']];
    }

    /** @return array<string, array{?string}> */
    public static function anWerte(): array
    {
        return ['true' => ['true'], '1' => ['1'], 'on' => ['on'], 'yes' => ['yes'], 'nicht gesetzt' => [null]];
    }

    /** @return array<string, array{string}> */
    public static function gesetzteAnWerte(): array
    {
        return ['true' => ['true'], '1' => ['1'], 'on' => ['on'], 'yes' => ['yes']];
    }

    /** @return array<string, array{string, string}> Erfassungs-Schalter × Quelle */
    public static function erfassungsSchalter(): array
    {
        return [
            'ALERTS_EXCEPTIONS' => ['ALERTS_EXCEPTIONS', 'exception'],
            'ALERTS_LOG' => ['ALERTS_LOG', 'log'],
            'ALERTS_HTTP_404' => ['ALERTS_HTTP_404', '404'],
            'ALERTS_HTTP_403' => ['ALERTS_HTTP_403', '403'],
        ];
    }

    /** @return array<string, array{string, string}> Quellen, deren Erfasser `AktuelleAnfrage::url()` aufrufen */
    public static function zaehlbareSchalter(): array
    {
        return [
            'ALERTS_EXCEPTIONS' => ['ALERTS_EXCEPTIONS', 'exception'],
            'ALERTS_LOG' => ['ALERTS_LOG', 'log'],
        ];
    }

    // ── ALERTS_ENABLED: aus ──────────────────────────────────────────────────

    // AK18 – „keine Erfassung“
    #[DataProvider('ausWerte')]
    public function test_enabled_aus_wert_erfasst_nichts(string $wert): void
    {
        // 404/403 rufen AktuelleAnfrage nicht auf; ihr Ergebnis prüfen die Versand-Tests.
        foreach (['log', 'exception'] as $quelle) {
            $this->umgebung('ALERTS_ENABLED', 'on');
            $vorher = $this->erfassungen();
            $this->loeseAus($quelle, 1);
            $this->assertGreaterThan($vorher, $this->erfassungen(), "Gegenprobe: {$quelle} wird bei ALERTS_ENABLED=on erfasst.");

            $this->umgebung('ALERTS_ENABLED', $wert);
            $vorher = $this->erfassungen();
            $this->loeseAus($quelle, 2);
            $this->assertSame($vorher, $this->erfassungen(), "{$quelle} darf bei ALERTS_ENABLED='{$wert}' nicht erfasst werden.");
        }
    }

    // AK18 – „kein Versand“ (Erfassung und Versand gleich)
    #[DataProvider('ausWerte')]
    public function test_enabled_aus_wert_verschickt_nichts(string $wert): void
    {
        foreach (['log', 'exception', '404', '403'] as $quelle) {
            $this->umgebung('ALERTS_ENABLED', 'on');
            $mails = count($this->mails());
            $this->loeseAus($quelle, 1);
            $this->assertCount($mails + 1, $this->mails(), "Gegenprobe: {$quelle} verschickt bei ALERTS_ENABLED=on.");

            $this->umgebung('ALERTS_ENABLED', $wert);
            $this->loeseAus($quelle, 2);
            $this->assertCount($mails + 1, $this->mails(), "{$quelle} darf bei ALERTS_ENABLED='{$wert}' nichts verschicken.");
        }
    }

    // AK18 – „kein Versand“ über den öffentlichen Port, Mail und Telegram
    #[DataProvider('ausWerte')]
    public function test_enabled_aus_wert_sperrt_den_port(string $wert): void
    {
        $this->umgebung('ALERTS_ENABLED', 'on');
        $this->port('bool.an');
        $this->assertEineMeldung();

        $this->umgebung('ALERTS_ENABLED', $wert);
        $this->port('bool.aus');

        $this->assertCount(1, $this->mails(), "Port darf bei ALERTS_ENABLED='{$wert}' keine Mail verschicken.");
        $this->assertCount(1, $this->telegramAnfragen(), "Port darf bei ALERTS_ENABLED='{$wert}' kein Telegram verschicken.");
    }

    // ── ALERTS_ENABLED: an ───────────────────────────────────────────────────

    // AK18 – „aktiv“: Erfassung
    #[DataProvider('anWerte')]
    public function test_enabled_an_wert_erfasst_und_verschickt(?string $wert): void
    {
        $this->umgebung('ALERTS_ENABLED', $wert);

        foreach (['log', 'exception', '404', '403'] as $quelle) {
            $mails = count($this->mails());
            $this->loeseAus($quelle, 1);

            $this->assertCount($mails + 1, $this->mails(), "{$quelle} muss eine Mail auslösen.");
        }
    }

    // AK18 – „aktiv“: Versand über den Port
    #[DataProvider('anWerte')]
    public function test_enabled_an_wert_verschickt_ueber_den_port(?string $wert): void
    {
        $this->umgebung('ALERTS_ENABLED', $wert);

        $this->port('bool.an');

        $this->assertEineMeldung();
    }

    // ── Übrige Erfassungs-Schalter ───────────────────────────────────────────

    // AK18 – „Dieselbe Wertung gilt für alle Bool-Schalter“ (aus)
    #[DataProvider('erfassungsSchalter')]
    public function test_erfassungsschalter_off_erfasst_diese_quelle_nicht(string $schalter, string $quelle): void
    {
        $this->umgebung($schalter, 'on');
        $mails = count($this->mails());
        $this->loeseAus($quelle, 1);
        $this->assertCount($mails + 1, $this->mails(), "Gegenprobe: {$quelle} verschickt bei {$schalter}=on.");

        $this->umgebung($schalter, 'off');
        $this->loeseAus($quelle, 2);

        $this->assertCount($mails + 1, $this->mails(), "{$quelle} darf bei {$schalter}=off nichts verschicken.");
    }

    // AK18 – Erfassung bei Schalter „off“ ist wirklich abgeschaltet (nicht erst der Versand)
    #[DataProvider('zaehlbareSchalter')]
    public function test_erfassungsschalter_off_loest_keine_erfassung_aus(string $schalter, string $quelle): void
    {
        $this->umgebung($schalter, 'on');
        $vorher = $this->erfassungen();
        $this->loeseAus($quelle, 1);
        $this->assertGreaterThan($vorher, $this->erfassungen(), "Gegenprobe: {$quelle} wird bei {$schalter}=on erfasst.");

        $this->umgebung($schalter, 'off');
        $vorher = $this->erfassungen();
        $this->loeseAus($quelle, 2);

        $this->assertSame($vorher, $this->erfassungen(), "{$quelle} darf bei {$schalter}=off nicht erfasst werden.");
    }

    // AK18 – leer gesetzt wird wie filter_var gewertet (= aus)
    #[DataProvider('erfassungsSchalter')]
    public function test_erfassungsschalter_leer_gesetzt_ist_aus(string $schalter, string $quelle): void
    {
        $this->umgebung($schalter, 'on');
        $mails = count($this->mails());
        $this->loeseAus($quelle, 1);
        $this->assertCount($mails + 1, $this->mails(), 'Gegenprobe: Aufbau greift.');

        $this->umgebung($schalter, '');
        $this->loeseAus($quelle, 2);

        $this->assertCount($mails + 1, $this->mails(), "{$quelle} darf bei {$schalter}='' nichts verschicken.");
    }

    // AK18 – „oder nicht gesetzt → aktiv“
    #[DataProvider('erfassungsSchalter')]
    public function test_erfassungsschalter_nicht_gesetzt_ist_an(string $schalter, string $quelle): void
    {
        $this->umgebung($schalter, null);

        $this->loeseAus($quelle, 1);

        $this->assertCount(1, $this->mails(), "{$quelle} muss bei nicht gesetztem {$schalter} gemeldet werden.");
    }

    // ── Fehlertext-Schalter ──────────────────────────────────────────────────

    // AK18 – ALERTS_MAIL_FEHLERTEXT=off
    public function test_mail_fehlertext_off_laesst_fehlertext_weg(): void
    {
        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', 'on');
        $this->port('bool.mail.an', 'GEHEIMER-FEHLERTEXT');
        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail(0)), 'Gegenprobe: Fehlertext bei on.');

        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', 'off');
        $this->port('bool.mail.aus', 'GEHEIMER-FEHLERTEXT');

        $this->assertStringNotContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail(1)));
    }

    // AK18 – ALERTS_TELEGRAM_FEHLERTEXT=off
    public function test_telegram_fehlertext_off_laesst_fehlertext_weg(): void
    {
        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', 'on');
        $this->port('bool.tg.an', 'GEHEIMER-FEHLERTEXT');
        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText(0), 'Gegenprobe: Fehlertext bei on.');

        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', 'off');
        $this->port('bool.tg.aus', 'GEHEIMER-FEHLERTEXT');

        $this->assertStringNotContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText(1));
    }

    // AK18 – Fehlertext-Schalter leer gesetzt
    public function test_fehlertext_schalter_leer_gesetzt_ist_aus(): void
    {
        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', 'on');
        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', 'on');
        $this->port('bool.leer.an', 'GEHEIMER-FEHLERTEXT');
        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail(0)), 'Gegenprobe Mail.');
        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText(0), 'Gegenprobe Telegram.');

        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', '');
        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', '');
        $this->port('bool.leer.aus', 'GEHEIMER-FEHLERTEXT');

        $this->assertStringNotContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail(1)));
        $this->assertStringNotContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText(1));
    }

    // AK18 – „true, 1, on, yes → aktiv“ für die Fehlertext-Schalter
    #[DataProvider('gesetzteAnWerte')]
    public function test_fehlertext_an_wert_zeigt_fehlertext(string $wert): void
    {
        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', $wert);
        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', $wert);
        $this->port('bool.fehlertext', 'GEHEIMER-FEHLERTEXT');

        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail()));
        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText());
    }

    // AK18 – nicht gesetzt: Standardwerte der Config (Mail an, Telegram aus)
    public function test_fehlertext_schalter_nicht_gesetzt_nutzt_standard(): void
    {
        $this->umgebung('ALERTS_MAIL_FEHLERTEXT', null);
        $this->umgebung('ALERTS_TELEGRAM_FEHLERTEXT', null);
        $this->port('bool.standard', 'GEHEIMER-FEHLERTEXT');

        $this->assertStringContainsString('GEHEIMER-FEHLERTEXT', $this->mailText($this->mail()));
        $this->assertStringNotContainsString('GEHEIMER-FEHLERTEXT', $this->telegramText());
    }
}
