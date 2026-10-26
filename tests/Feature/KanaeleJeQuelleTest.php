<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** projekt-config: Kanäle je Quelle (AK3–AK6) und die zugehörigen Randfälle. */
final class KanaeleJeQuelleTest extends TestCase
{
    private int $zaehler = 0;

    /** @return array<string, array{string}> */
    public static function quellen(): array
    {
        return [
            'meldung' => ['meldung'],
            'exception' => ['exception'],
            'log' => ['log'],
            '404' => ['404'],
            '403' => ['403'],
        ];
    }

    /** Löst einen Alert der Quelle über den echten Erfassungsweg aus; jeder Aufruf hat einen neuen Fingerabdruck. */
    private function loese(string $quelle): void
    {
        $this->zaehler++;
        $n = $this->zaehler;

        match ($quelle) {
            'meldung' => $this->app->make(AlertReporter::class)->melde(new Meldung(schluessel: "test.meldung-{$n}", titel: "Meldung {$n}")),
            'exception' => $this->meldeException(new \RuntimeException("Fehler {$n}")),
            'log' => Log::critical("Kritisch Nummer {$n} Bereich".chr(96 + $n)),
            '404' => $this->get("/gibt-es-nicht-{$n}", ['Referer' => 'http://localhost/start'])->assertNotFound(),
            '403' => $this->actingAs((new User)->forceFill(['id' => 42, 'name' => 'Erika', 'email' => 'e@example.com']))
                ->get('/verboten')->assertForbidden(),
            default => throw new \InvalidArgumentException($quelle),
        };
    }

    private function andereQuelle(string $quelle): string
    {
        return $quelle === 'meldung' ? 'log' : 'meldung';
    }

    /** @param list<mixed> $liste */
    private function kanaele(string $quelle, array $liste): void
    {
        config(["alerts.kanaele.{$quelle}" => $liste]);
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    // AK3
    #[DataProvider('quellen')]
    public function test_nur_mail_in_der_liste_versendet_je_quelle_nur_per_mail(string $quelle): void
    {
        $this->kanaele($quelle, ['mail']);

        $this->loese($quelle);

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());

        // Gegenprobe: eine Quelle ohne Eintrag geht weiterhin über beide Kanäle (Aufbau greift, Einstellung gilt nur für die Quelle).
        $this->loese($this->andereQuelle($quelle));
        $this->assertCount(2, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // AK3
    #[DataProvider('quellen')]
    public function test_nur_telegram_in_der_liste_versendet_je_quelle_nur_per_telegram(string $quelle): void
    {
        $this->kanaele($quelle, ['telegram']);

        $this->loese($quelle);

        $this->assertCount(0, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());

        $this->loese($this->andereQuelle($quelle));
        $this->assertCount(1, $this->mails());
        $this->assertCount(2, $this->telegramAnfragen());
    }

    // AK3
    public function test_liste_mit_beiden_kanaelen_versendet_ueber_beide(): void
    {
        $this->kanaele('exception', ['mail', 'telegram']);

        $this->loese('exception');

        $this->assertEineMeldung();
    }

    // AK3
    public function test_kanal_der_liste_wird_nur_genutzt_wenn_er_in_channels_aktiv_ist(): void
    {
        config(['alerts.channels' => 'mail']);
        $this->kanaele('exception', ['mail', 'telegram']);

        $this->loese('exception');

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());

        // Gegenprobe: ist Telegram global aktiv, geht dieselbe Liste auch dorthin.
        config(['alerts.channels' => 'mail,telegram']);
        $this->loese('exception');

        $this->assertCount(2, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // AK3
    public function test_kanal_der_liste_wird_nur_genutzt_wenn_er_eingerichtet_ist(): void
    {
        config(['alerts.telegram.bot_token' => '']);
        $this->kanaele('exception', ['mail', 'telegram']);

        $this->loese('exception');

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());

        // Gegenprobe: mit Token geht dieselbe Liste auch per Telegram.
        config(['alerts.telegram.bot_token' => self::TOKEN]);
        $this->loese('exception');

        $this->assertCount(2, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // AK3 – Randfall „global nicht aktiv oder nicht eingerichtet“: keine Warnung
    public function test_global_nicht_aktiver_kanal_in_der_liste_erzeugt_keine_warnung(): void
    {
        config(['alerts.channels' => 'mail']);
        $this->kanaele('log', ['telegram']);

        $this->loese('log');

        $this->assertKeineMeldung();
        $this->assertCount(0, $this->logsMitLevel('warning'), 'Kein Versand über den Kanal und keine Warnung: '.$this->logText());

        // Gegenprobe: Telegram aktiv -> Alert wäre über Telegram gegangen.
        config(['alerts.channels' => 'mail,telegram']);
        $this->loese('log');
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // AK3 – Randfall „global nicht aktiv oder nicht eingerichtet“: keine Warnung
    public function test_nicht_eingerichteter_kanal_in_der_liste_erzeugt_keine_warnung(): void
    {
        config(['alerts.telegram.bot_token' => '']);
        $this->kanaele('log', ['telegram', 'mail']);

        $this->loese('log');

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());
        $this->assertCount(0, $this->logsMitLevel('warning'), 'Keine Warnung für nicht eingerichteten Kanal: '.$this->logText());
    }

    // ── AK4 ──────────────────────────────────────────────────────────────────

    // AK4 (Verhalten wie bisher, vor der Umsetzung schon grün)
    #[DataProvider('quellen')]
    public function test_ohne_eintrag_geht_der_alert_ueber_alle_aktiven_kanaele(string $quelle): void
    {
        $this->loese($quelle);

        $this->assertEineMeldung();
    }

    // AK4 (vor der Umsetzung schon grün)
    #[DataProvider('quellen')]
    public function test_ohne_eintrag_gilt_nur_channels(string $quelle): void
    {
        config(['alerts.channels' => 'telegram']);

        $this->loese($quelle);

        $this->assertCount(0, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // ── AK5 ──────────────────────────────────────────────────────────────────

    // AK5
    #[DataProvider('quellen')]
    public function test_leere_liste_versendet_fuer_die_quelle_ueber_keinen_kanal(string $quelle): void
    {
        $this->kanaele($quelle, []);

        $this->loese($quelle);

        $this->assertKeineMeldung();

        // Gegenprobe: andere Quelle ohne Eintrag wird versendet.
        $this->loese($this->andereQuelle($quelle));
        $this->assertEineMeldung();
    }

    // ── AK6 ──────────────────────────────────────────────────────────────────

    // AK6 – „die übrigen Kanäle der Liste gelten“
    public function test_unbekannter_kanal_wird_ignoriert_und_uebrige_kanaele_gelten(): void
    {
        $this->kanaele('exception', ['sms', 'mail']);

        $this->loese('exception');

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());

        // Gegenprobe: mit Telegram statt Mail neben dem unbekannten Namen geht es über Telegram.
        $this->kanaele('exception', ['sms', 'telegram']);
        $this->loese('exception');
        $this->assertCount(1, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // AK6 – „Log-Warnung (Level warning) mit dem Namen“
    public function test_unbekannter_kanal_erzeugt_log_warnung_mit_dem_namen(): void
    {
        $this->kanaele('exception', ['sms', 'mail']);

        $this->loese('exception');

        $this->assertCount(1, $this->mails(), 'Aufbau: Alert wurde verschickt.');
        $warnungen = $this->logsMitLevel('warning');
        $this->assertNotEmpty($warnungen, 'Warnung fehlt: '.$this->logText());
        $this->assertNotEmpty(
            array_filter($warnungen, static fn ($e): bool => str_contains($e->message.json_encode($e->context), 'sms')),
            'Warnung nennt den Namen „sms“ nicht: '.$this->logText(),
        );
    }

    // AK6 – nur unbekannter Name: kein Versand für die Quelle
    public function test_liste_nur_mit_unbekanntem_kanal_versendet_nichts(): void
    {
        $this->kanaele('exception', ['sms']);

        $this->loese('exception');

        $this->assertKeineMeldung();
        $this->assertNotEmpty($this->logsMitLevel('warning'), 'Warnung fehlt: '.$this->logText());

        // Gegenprobe: andere Quelle ohne Eintrag wird versendet.
        $this->loese('meldung');
        $this->assertEineMeldung();
    }

    // AK6 – bekannte Kanäle erzeugen keine Warnung (Gegenprobe zur Warnung)
    public function test_bekannte_kanaele_erzeugen_keine_warnung(): void
    {
        $this->kanaele('exception', ['mail', 'telegram']);

        $this->loese('exception');

        $this->assertEineMeldung();
        $this->assertCount(0, $this->logsMitLevel('warning'), $this->logText());
    }

    // ── Randfall: Schreibweise ──────────────────────────────────────────────

    // Randfall Groß-/Kleinschreibung und Leerzeichen
    public function test_gross_kleinschreibung_und_leerzeichen_in_kanalnamen_zaehlen_nicht(): void
    {
        $this->kanaele('exception', [' Mail ', 'TELEGRAM']);

        $this->loese('exception');

        $this->assertEineMeldung();
        $this->assertCount(0, $this->logsMitLevel('warning'), 'Keine Warnung wegen Schreibweise: '.$this->logText());
    }

    // Randfall Groß-/Kleinschreibung und Leerzeichen
    public function test_mail_mit_leerzeichen_und_grossbuchstaben_ist_nur_mail(): void
    {
        $this->kanaele('exception', [' MAIL ']);

        $this->loese('exception');

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());
        $this->assertCount(0, $this->logsMitLevel('warning'), $this->logText());

        // Gegenprobe: Telegram wäre ohne Einschränkung angesprochen worden.
        $this->loese('meldung');
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // ── Randfall: Drosselung und Freigabe ───────────────────────────────────

    // Randfall Drosselung hängt nur am Fingerabdruck
    public function test_drosselung_gilt_unabhaengig_vom_kanal(): void
    {
        $this->kanaele('exception', ['mail']);
        $this->meldeException($this->ausnahme());
        $this->assertCount(1, $this->mails());

        // Kanalliste wechselt, derselbe Fingerabdruck bleibt gedrosselt.
        $this->kanaele('exception', ['telegram']);
        $this->meldeException($this->ausnahme());
        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen(), 'Gleicher Fingerabdruck darf über anderen Kanal nicht erneut gemeldet werden.');

        // Gegenprobe: ein anderer Fingerabdruck geht jetzt per Telegram raus.
        $this->meldeException($this->andereAusnahme());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    // Randfall Drosselung hängt nur am Fingerabdruck
    public function test_wiederholung_ueber_denselben_kanal_bleibt_gedrosselt(): void
    {
        $this->kanaele('exception', ['mail']);

        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());
    }

    // Randfall Freigabe hängt nur am Fingerabdruck
    public function test_freigabe_gilt_fuer_den_fingerabdruck_und_nicht_fuer_den_kanal(): void
    {
        $this->kanaele('exception', ['mail']);
        $this->meldeException($this->ausnahme());
        $this->assertCount(1, $this->mails());
        $fingerabdruck = $this->fingerabdruckAus($this->mailText($this->mail()));

        $this->assertSame(0, Artisan::call('alerts:reset', ['fingerabdruck' => $fingerabdruck]));

        $this->kanaele('exception', ['telegram']);
        $this->meldeException($this->ausnahme());

        $this->assertCount(1, $this->mails(), 'Nur die erste Mail.');
        $this->assertCount(1, $this->telegramAnfragen(), 'Nach der Freigabe geht derselbe Fingerabdruck wieder raus.');
    }
}
