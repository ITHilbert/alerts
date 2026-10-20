<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;

/** AK13 (Kanäle), AK14 (Inhalt/Fehlertext je Kanal), AK16 (Ausfallsicherheit), AK17 (keine Schleife). */
final class VersandTest extends TestCase
{
    private function melde(): void
    {
        $this->app->make(AlertReporter::class)->melde(new Meldung(
            schluessel: 'test.versand',
            titel: 'Testmeldung',
            fehlertext: 'Fehler bei kunde@example.com',
        ));
    }

    // ── Kanäle ───────────────────────────────────────────────────────────────

    public function test_mehrere_empfaenger_kommagetrennt(): void
    {
        config(['alerts.mail.to' => 'a@example.com, b@example.com']);

        $this->melde();

        $this->assertEqualsCanonicalizing(['a@example.com', 'b@example.com'], $this->empfaenger($this->mail()));
    }

    public function test_eigener_mailer(): void
    {
        config(['alerts.mail.mailer' => 'zweit']);

        $this->melde();

        $this->assertCount(0, $this->mails('array'));
        $this->assertCount(1, $this->mails('zweit'));
    }

    public function test_nur_mail_kanal_aktiv(): void
    {
        config(['alerts.channels' => 'mail']);

        $this->melde();

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());
    }

    public function test_nur_telegram_kanal_aktiv(): void
    {
        config(['alerts.channels' => 'telegram']);

        $this->melde();

        $this->assertCount(0, $this->mails());
        $this->assertCount(1, $this->telegramAnfragen());
    }

    public function test_kanal_ohne_konfiguration_wird_uebersprungen(): void
    {
        config(['alerts.mail.to' => null, 'alerts.telegram.bot_token' => null]);

        $this->melde();

        $this->assertKeineMeldung();
    }

    public function test_telegram_ohne_chat_id_wird_uebersprungen_mail_geht_raus(): void
    {
        config(['alerts.telegram.chat_id' => null]);

        $this->melde();

        $this->assertCount(1, $this->mails());
        $this->assertCount(0, $this->telegramAnfragen());
    }

    // ── Fehlertext je Kanal ──────────────────────────────────────────────────

    public function test_standard_mail_mit_telegram_ohne_fehlertext(): void
    {
        $this->melde();

        $this->assertStringContainsString('Fehler bei kunde@example.com', $this->mailText($this->mail()));
        $this->assertStringNotContainsString('kunde@example.com', $this->telegramNutzlast());
    }

    public function test_fehlertext_je_kanal_umschaltbar(): void
    {
        config(['alerts.mail.fehlertext' => false, 'alerts.telegram.fehlertext' => true]);

        $this->melde();

        $this->assertStringNotContainsString('kunde@example.com', $this->mailText($this->mail()));
        $this->assertStringContainsString('Fehler bei kunde@example.com', $this->telegramText());
    }

    public function test_ort_der_exception_steht_auch_ohne_fehlertext_in_telegram(): void
    {
        $this->meldeException($this->ausnahme('geheime Details'));

        $telegram = $this->telegramText();
        $this->assertStringContainsString('TestCase.php:', $telegram);
        $this->assertStringContainsString('RuntimeException', $telegram);
        $this->assertStringNotContainsString('geheime Details', $this->telegramNutzlast());
    }

    public function test_bot_token_steht_nie_in_der_mail(): void
    {
        $this->melde();

        $this->assertStringNotContainsString(self::TOKEN, $this->mailRoh($this->mail()));
    }

    // ── Ausfallsicherheit ────────────────────────────────────────────────────

    public function test_mail_ausfall_bricht_nicht_ab_und_telegram_geht_trotzdem(): void
    {
        config(['alerts.mail.mailer' => 'kaputt']);

        $this->melde();

        $this->assertCount(1, $this->telegramAnfragen());
        $this->assertNotEmpty($this->logsMitLevel('warning'), 'Fehlgeschlagener Versand wird als Warnung geloggt.');
    }

    public function test_telegram_fehlerstatus_bricht_nicht_ab(): void
    {
        $this->telegramStoert(502);

        $this->melde();

        $this->assertCount(1, $this->mails());
        $this->assertNotEmpty($this->logsMitLevel('warning'));
        $this->assertStringNotContainsString(self::TOKEN, $this->logText(), 'Bot-Token darf nie im Log stehen.');
    }

    public function test_telegram_nicht_erreichbar_bricht_nicht_ab_und_loggt_keinen_token(): void
    {
        $this->telegramNichtErreichbar();

        $this->melde();

        $this->assertCount(1, $this->mails());
        $this->assertNotEmpty($this->logsMitLevel('warning'));
        $this->assertStringNotContainsString(self::TOKEN, $this->logText(), 'Bot-Token darf nie im Log stehen.');
    }

    public function test_sperre_gilt_auch_nach_gescheitertem_versand(): void
    {
        config(['alerts.mail.mailer' => 'kaputt']);

        $this->melde();
        $this->melde();

        $this->assertCount(1, $this->telegramAnfragen());
    }

    public function test_versandfehler_loesen_keine_neue_meldung_aus(): void
    {
        config(['alerts.mail.mailer' => 'kaputt']);
        $this->telegramNichtErreichbar();

        $this->melde();

        $this->assertEmpty($this->logsMitLevel('critical'), 'Versandfehler nie auf critical loggen (Endlosschleife).');
        $this->assertEmpty($this->logsMitLevel('error'));
    }

    public function test_critical_log_waehrend_des_versands_loest_keine_zweite_meldung_aus(): void
    {
        config(['alerts.mail.mailer' => 'plaudernd']);

        $this->melde();

        $this->assertCount(1, $this->mails('plaudernd'));
        $this->assertCount(1, $this->telegramAnfragen());
    }
}
