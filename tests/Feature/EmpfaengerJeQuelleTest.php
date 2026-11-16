<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\LadeKonfig;
use ITHilbert\Alerts\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;

/** empfaenger-je-quelle: Mail-Empfänger je Quelle, Kanäle je Quelle per .env, Sammel-Standard für langsame Scanner. */
final class EmpfaengerJeQuelleTest extends TestCase
{
    use LadeKonfig;

    private const QUELLEN = ['exception', 'log', 'meldung', '404', '403'];

    private int $zaehler = 0;

    /** @return array<string, array{string}> */
    public static function quellen(): array
    {
        return array_combine(self::QUELLEN, array_map(static fn (string $q): array => [$q], self::QUELLEN));
    }

    /** Löst einen Alert der Quelle über den echten Erfassungsweg aus; jeder Aufruf hat einen neuen Fingerabdruck. */
    private function loese(string $quelle): void
    {
        $this->zaehler++;
        $n = $this->zaehler;

        match ($quelle) {
            'meldung' => $this->app->make(AlertReporter::class)->melde(new Meldung(schluessel: "test.meldung-{$n}", titel: "Meldung {$n}")),
            'exception' => $this->meldeException(match ($n % 3) {
                1 => $this->ausnahme("Fehler {$n}"),
                2 => $this->andereAusnahme("Fehler {$n}"),
                default => new \LogicException("Fehler {$n}"),
            }),
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

    /** @return list<string> Empfänger aller Mails, sortiert. */
    private function alleEmpfaenger(): array
    {
        $liste = array_merge(...array_map(fn (SentMessage $m): array => $this->empfaenger($m), $this->mails()));
        sort($liste);

        return $liste;
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1
    public function test_404_geht_an_die_quell_adresse_und_exception_weiter_an_alerts_mail_to(): void
    {
        config(['alerts.mail.to' => 'admin@example.com', 'alerts.mail.to_je_quelle.404' => '404@it-hilbert.com']);

        $this->loese('404');
        $this->assertCount(1, $this->mails());
        $this->assertSame(['404@it-hilbert.com'], $this->empfaenger($this->mail(0)));

        $this->loese('exception');
        $this->assertCount(2, $this->mails());
        $this->assertSame(['admin@example.com'], $this->empfaenger($this->mail(1)));
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2
    #[DataProvider('quellen')]
    public function test_jede_quelle_nimmt_mehrere_kommagetrennte_adressen_und_die_uebrigen_bleiben_beim_standard(string $quelle): void
    {
        config(['alerts.mail.to' => 'admin@example.com', "alerts.mail.to_je_quelle.{$quelle}" => 'a@example.com, b@example.com']);

        $this->loese($quelle);
        $this->assertSame(['a@example.com', 'b@example.com'], $this->alleEmpfaenger());

        $this->loese($this->andereQuelle($quelle));
        $this->assertSame(['admin@example.com'], $this->empfaenger($this->mail(1)), 'Gegenprobe: eine andere Quelle geht an alerts.mail.to.');
    }

    // AK2
    public function test_env_schluessel_je_quelle_landen_in_der_config(): void
    {
        $env = [];
        foreach (self::QUELLEN as $quelle) {
            $env['ALERTS_MAIL_TO_'.strtoupper($quelle)] = "{$quelle}@example.com, zweite-{$quelle}@example.com";
        }

        $je = $this->konfig($env)['mail']['to_je_quelle'] ?? null;

        $this->assertIsArray($je);
        foreach (self::QUELLEN as $quelle) {
            $this->assertSame("{$quelle}@example.com, zweite-{$quelle}@example.com", $je[$quelle] ?? null, "ALERTS_MAIL_TO_{$quelle}");
        }
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    /** @return array<string, array{mixed}> */
    public static function leereEmpfaenger(): array
    {
        return ['null' => [null], 'leer' => [''], 'leerzeichen' => ['   '], 'kommas' => [',,'], 'kommas und leerzeichen' => [' , , ']];
    }

    // AK3
    #[DataProvider('leereEmpfaenger')]
    public function test_nicht_gesetzter_oder_leerer_schluessel_nimmt_alerts_mail_to(mixed $wert): void
    {
        config(['alerts.mail.to' => 'admin@example.com', 'alerts.mail.to_je_quelle.404' => $wert]);

        $this->loese('404');

        $this->assertSame(['admin@example.com'], $this->alleEmpfaenger());
    }

    // AK3
    public function test_ohne_neue_schluessel_ist_der_env_wert_je_quelle_leer_und_alerts_mail_to_gilt(): void
    {
        $je = $this->konfig()['mail']['to_je_quelle'] ?? [];

        foreach (self::QUELLEN as $quelle) {
            $this->assertSame('', trim(str_replace(',', '', (string) ($je[$quelle] ?? ''))), "Quelle {$quelle} hat ohne Env keinen Empfänger.");
        }

        $this->loese('404');
        $this->assertSame(['dev@example.com'], $this->alleEmpfaenger(), 'Gegenprobe: der Versand geht an alerts.mail.to.');
    }

    // ── AK4 ──────────────────────────────────────────────────────────────────

    // AK4
    public function test_alerts_kanaele_je_quelle_aus_der_env_kommagetrennt_und_ohne_eintrag_wenn_nicht_gesetzt_oder_leer(): void
    {
        $env = [];
        foreach (self::QUELLEN as $quelle) {
            $env['ALERTS_KANAELE_'.strtoupper($quelle)] = 'mail';
        }
        $kanaele = $this->konfig($env)['kanaele'] ?? null;
        $this->assertIsArray($kanaele);
        foreach (self::QUELLEN as $quelle) {
            $this->assertSame(['mail'], $kanaele[$quelle] ?? null, "ALERTS_KANAELE_{$quelle}");
        }

        $this->assertSame(['mail', 'telegram'], $this->konfig(['ALERTS_KANAELE_404' => 'mail, telegram'])['kanaele'][404] ?? null);

        $this->assertArrayNotHasKey(404, $this->konfig()['kanaele'] ?? [], 'nicht gesetzt = kein Eintrag');
        $this->assertArrayNotHasKey(404, $this->konfig(['ALERTS_KANAELE_404' => ''])['kanaele'] ?? [], 'leer gesetzt = kein Eintrag');
    }

    // AK4
    public function test_404_mit_alerts_kanaele_404_mail_geht_nicht_an_telegram_die_exception_schon(): void
    {
        config(['alerts.kanaele' => $this->konfig(['ALERTS_KANAELE_404' => 'mail'])['kanaele']]);

        $this->loese('exception');
        $this->assertCount(1, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen(), 'Gegenprobe: eine Quelle ohne Eintrag geht an alle Kanäle.');

        $this->loese('404');
        $this->assertCount(2, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen(), '404 geht nicht an Telegram.');
    }

    // ── AK5 ──────────────────────────────────────────────────────────────────

    // AK5
    public function test_elf_404_im_abstand_von_35_sekunden_ergeben_hoechstens_eine_einzel_und_eine_sammelmeldung(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.57', 'HTTP_USER_AGENT' => 'LangsamerScanner/1.0'])
                ->get("/langsam-{$i}", ['Referer' => 'http://localhost/start'])->assertNotFound();
            $this->travel(35)->seconds();
        }

        $haeufungen = array_filter($this->mails(), fn (SentMessage $m): bool => str_contains($this->betreff($m), 'Häufung von 404 (eine Quelle)'));
        $einzeln = array_filter($this->mails(), fn (SentMessage $m): bool => str_contains($this->betreff($m), 'Seite nicht gefunden (404)'));

        $this->assertGreaterThanOrEqual(1, count($this->mails()), 'Aufbau: die 404 sind meldepflichtig.');
        $this->assertLessThanOrEqual(1, count($einzeln));
        $this->assertLessThanOrEqual(1, count($haeufungen));
        $this->assertLessThanOrEqual(2, count($this->telegramAnfragen()));
    }

    // ── Randfälle ────────────────────────────────────────────────────────────

    // Randfall 2
    public function test_quell_adresse_ohne_erlaubten_mail_kanal_versendet_keine_mail(): void
    {
        config(['alerts.mail.to_je_quelle.404' => '404@it-hilbert.com', 'alerts.kanaele.404' => ['telegram']]);

        $this->loese('404');

        $this->assertCount(1, $this->telegramAnfragen(), 'Aufbau: die Meldung wurde ausgelöst und geht an Telegram.');
        $this->assertCount(0, $this->mails());
    }

    // Randfall 3
    public function test_alerts_mail_to_leer_und_quell_adresse_gesetzt_mailt_nur_die_404(): void
    {
        config(['alerts.mail.to' => '', 'alerts.mail.to_je_quelle.404' => '404@it-hilbert.com']);

        $this->loese('404');
        $this->assertSame(['404@it-hilbert.com'], $this->alleEmpfaenger());

        $this->loese('exception');
        $this->loese('meldung');
        $this->assertCount(1, $this->mails(), 'Andere Quellen versenden keine Mail.');
        $this->assertCount(3, $this->telegramAnfragen(), 'Gegenprobe: die Meldungen wurden ausgelöst.');
    }
}
