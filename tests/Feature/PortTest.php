<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;

/** AK1 (Port), AK15 (synchron), AK18 (abschaltbar). */
final class PortTest extends TestCase
{
    private function reporter(): AlertReporter
    {
        return $this->app->make(AlertReporter::class);
    }

    private function meldung(string $schluessel = 'consentlaw.kein-fallback'): Meldung
    {
        return new Meldung(
            schluessel: $schluessel,
            titel: 'Rechtstext nicht lieferbar',
            fehlertext: 'HTTP 503 von der API',
            details: ['Typ' => 'impressum'],
        );
    }

    public function test_meldung_ueber_den_port_geht_per_mail_und_telegram_raus(): void
    {
        $this->reporter()->melde($this->meldung());

        $this->assertEineMeldung();

        $mail = $this->mailText($this->mail());
        $this->assertSame('[Testprojekt] Rechtstext nicht lieferbar', $this->betreff($this->mail()));
        $this->assertSame('consentlaw.kein-fallback', $this->fingerabdruckAus($mail));
        $this->assertStringContainsString('Projekt: Testprojekt', $mail);
        $this->assertStringContainsString('Umgebung: testing', $mail);
        $this->assertStringContainsString('Quelle: Meldung', $mail);
        $this->assertStringContainsString('Typ: impressum', $mail);
        $this->assertStringContainsString('HTTP 503 von der API', $mail);
        $this->assertMatchesRegularExpression('/20\.10\.2026|2026-10-20/', $mail);
        $this->assertSame(['dev@example.com'], $this->empfaenger($this->mail()));

        $telegram = $this->telegramText();
        $this->assertStringContainsString('Testprojekt', $telegram);
        $this->assertStringContainsString('Rechtstext nicht lieferbar', $telegram);
        $this->assertSame('consentlaw.kein-fallback', $this->fingerabdruckAus($telegram));
        $this->assertSame(self::CHAT, (string) $this->telegramAnfragen()[0]->data()['chat_id']);
        $this->assertStringContainsString('/bot'.self::TOKEN.'/sendMessage', $this->telegramAnfragen()[0]->url());
    }

    public function test_ungueltiger_schluessel_wird_abgelehnt(): void
    {
        $this->expectException(\DomainException::class);

        new Meldung(schluessel: 'Mit Leerzeichen!', titel: 'x');
    }

    public function test_versand_laeuft_synchron_ohne_queue(): void
    {
        Queue::fake();

        $this->reporter()->melde($this->meldung());

        Queue::assertNothingPushed();
        $this->assertEineMeldung();
    }

    public function test_abgeschaltet_wird_nichts_verschickt(): void
    {
        config(['alerts.enabled' => false]);

        $this->reporter()->melde($this->meldung());

        $this->assertKeineMeldung();
    }
}
