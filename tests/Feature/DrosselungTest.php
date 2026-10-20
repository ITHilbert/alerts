<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use ITHilbert\Alerts\Tests\TestCase;

/** AK8 (Sperre), AK9 (Sammelmeldung), AK10/AK11 (alerts:reset). */
final class DrosselungTest extends TestCase
{
    public function test_wiederholungen_innerhalb_der_sperre_werden_nicht_verschickt(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());

        $this->assertEineMeldung();
        $this->assertStringNotContainsString('seit letzter Meldung', $this->mailText($this->mail()));
    }

    public function test_nach_ablauf_der_sperre_kommt_eine_sammelmeldung(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());

        $this->travel(25)->hours();
        $this->meldeException($this->ausnahme());

        $this->assertCount(2, $this->mails());
        $this->assertStringContainsString('2× seit letzter Meldung', $this->mailText($this->mail(1)));
        $this->assertStringContainsString('2× seit letzter Meldung', $this->telegramText(1));
    }

    public function test_sperrdauer_ist_einstellbar(): void
    {
        config(['alerts.drosselung.sperre_stunden' => 1]);

        $this->meldeException($this->ausnahme());
        $this->travel(2)->hours();
        $this->meldeException($this->ausnahme());

        $this->assertCount(2, $this->mails());
    }

    public function test_reset_gibt_einen_fingerabdruck_frei(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->ausnahme());
        $fingerabdruck = $this->fingerabdruckAus($this->mailText($this->mail()));

        $this->artisan('alerts:reset', ['fingerabdruck' => $fingerabdruck])->assertExitCode(0);
        $this->meldeException($this->ausnahme());

        $this->assertCount(2, $this->mails());
        $this->assertStringNotContainsString('seit letzter Meldung', $this->mailText($this->mail(1)), 'Reset setzt auch den Zähler zurück.');
    }

    public function test_reset_laesst_andere_sperren_bestehen(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->andereAusnahme());
        $erster = $this->fingerabdruckAus($this->mailText($this->mail(0)));

        $this->artisan('alerts:reset', ['fingerabdruck' => $erster])->assertExitCode(0);
        $this->meldeException($this->andereAusnahme());

        $this->assertCount(2, $this->mails());
    }

    public function test_reset_mit_unbekanntem_fingerabdruck_scheitert(): void
    {
        $this->artisan('alerts:reset', ['fingerabdruck' => 'gibt.es.nicht'])->assertExitCode(1);
    }

    public function test_reset_ohne_angabe_scheitert(): void
    {
        $this->artisan('alerts:reset')->assertExitCode(1);
    }

    public function test_reset_all_gibt_alle_frei(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->andereAusnahme());

        $this->artisan('alerts:reset', ['--all' => true])->assertExitCode(0);
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->andereAusnahme());

        $this->assertCount(4, $this->mails());
    }
}
