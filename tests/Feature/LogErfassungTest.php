<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Support\Facades\Log;
use ITHilbert\Alerts\Tests\TestCase;

/** AK4 (Log-Kanal ab critical). */
final class LogErfassungTest extends TestCase
{
    public function test_critical_mit_alert_key_nutzt_den_schluessel_als_fingerabdruck(): void
    {
        Log::critical('Zahlungsdienst antwortet nicht', ['alert_key' => 'zahlung.weg']);

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertSame('zahlung.weg', $this->fingerabdruckAus($mail));
        $this->assertStringContainsString('Quelle: Log', $mail);
        $this->assertStringContainsString('Zahlungsdienst antwortet nicht', $this->betreff($this->mail()));
    }

    public function test_alert_und_emergency_werden_gemeldet(): void
    {
        Log::alert('Platte voll');
        Log::emergency('Server brennt');

        $this->assertCount(2, $this->mails());
    }

    public function test_error_und_darunter_werden_nicht_gemeldet(): void
    {
        Log::error('Nur ein Fehler');
        Log::warning('Nur eine Warnung');
        Log::info('Nur eine Info');

        $this->assertKeineMeldung();
    }

    public function test_gleiche_meldung_ohne_alert_key_wird_gedrosselt(): void
    {
        Log::critical('Import abgebrochen');
        Log::critical('Import abgebrochen');

        $this->assertEineMeldung();
        $this->fingerabdruckAus($this->mailText($this->mail()));
    }

    public function test_zahlen_in_der_meldung_ergeben_denselben_fingerabdruck(): void
    {
        Log::critical('Import 17 abgebrochen nach 3 Versuchen');
        Log::critical('Import 18 abgebrochen nach 4 Versuchen');

        $this->assertEineMeldung();
    }

    public function test_unterschiedlicher_text_ergibt_eigene_meldung(): void
    {
        Log::critical('Import abgebrochen');
        Log::critical('Export abgebrochen');

        $this->assertCount(2, $this->mails());
    }

    public function test_exception_im_kontext_nutzt_den_exception_fingerabdruck(): void
    {
        // Spec-Randfall: dieselbe Exception gemeldet UND zusätzlich auf critical geloggt → eine Meldung.
        $e = $this->ausnahme();

        $this->meldeException($e);
        Log::critical('Bestellung fehlgeschlagen', ['exception' => $e]);

        $this->assertEineMeldung();
    }

    public function test_exception_im_kontext_zuerst_geloggt_dann_gemeldet(): void
    {
        $e = $this->ausnahme();

        Log::critical('Bestellung fehlgeschlagen', ['exception' => $e]);
        $this->meldeException($e);

        $this->assertEineMeldung();
    }

    public function test_ungueltiger_alert_key_meldet_trotzdem_und_warnt(): void
    {
        Log::critical('Zahlungsdienst antwortet nicht', ['alert_key' => 'Ungültig mit Leerzeichen']);

        $this->assertEineMeldung();
        $this->assertStringContainsString('alert_key', $this->logText(), 'Ungültiger alert_key wird als Warnung geloggt.');
        $this->assertNotEmpty($this->logsMitLevel('warning'));
    }

    public function test_erfassung_abgeschaltet(): void
    {
        config(['alerts.erfassung.log' => false]);

        Log::critical('Zahlungsdienst antwortet nicht');

        $this->assertKeineMeldung();
    }
}
