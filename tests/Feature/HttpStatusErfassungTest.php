<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use ITHilbert\Alerts\Tests\TestCase;

/** AK5–AK7 (404 mit internem Referer, 403 nur bei angemeldetem Nutzer). */
final class HttpStatusErfassungTest extends TestCase
{
    private function nutzer(): User
    {
        return (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);
    }

    // ── 404 ──────────────────────────────────────────────────────────────────

    public function test_404_mit_internem_referer_wird_gemeldet(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/start?q=suche'])->assertNotFound();

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertStringContainsString('Quelle: 404', $mail);
        $this->assertStringContainsString('/gibt-es-nicht', $mail);
        $this->assertStringContainsString('/start', $mail);
        $this->assertStringNotContainsString('q=suche', $mail, 'Query-String des Referers gehört nicht in die Meldung.');
        $this->fingerabdruckAus($mail);
    }

    public function test_404_ohne_referer_wird_ignoriert(): void
    {
        $this->get('/wp-admin')->assertNotFound();

        $this->assertKeineMeldung();
    }

    public function test_404_mit_fremdem_referer_wird_ignoriert(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'https://www.google.com/search'])->assertNotFound();
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost.boese.example/'])->assertNotFound();

        $this->assertKeineMeldung();
    }

    public function test_gleiche_tote_url_von_verschiedenen_seiten_ergibt_eine_meldung(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/a']);
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/b']);

        $this->assertEineMeldung();
    }

    public function test_404_erfassung_abgeschaltet(): void
    {
        config(['alerts.erfassung.http_404' => false]);

        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/start']);

        $this->assertKeineMeldung();
    }

    public function test_erfolgreiche_seite_loest_nichts_aus(): void
    {
        $this->get('/seite', ['Referer' => 'http://localhost/start'])->assertOk();

        $this->assertKeineMeldung();
    }

    // ── 403 ──────────────────────────────────────────────────────────────────

    public function test_403_bei_angemeldetem_nutzer_wird_ohne_nutzerdaten_gemeldet(): void
    {
        $this->actingAs($this->nutzer())->get('/verboten')->assertForbidden();

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertStringContainsString('Quelle: 403', $mail);
        $this->assertStringContainsString('/verboten', $mail);

        foreach (['erika@example.com', 'Erika Muster'] as $persoenlich) {
            $this->assertStringNotContainsString($persoenlich, $mail);
            $this->assertStringNotContainsString($persoenlich, $this->telegramNutzlast());
        }
    }

    public function test_403_ohne_anmeldung_wird_ignoriert(): void
    {
        $this->get('/verboten')->assertForbidden();

        $this->assertKeineMeldung();
    }

    public function test_403_erfassung_abgeschaltet(): void
    {
        config(['alerts.erfassung.http_403' => false]);

        $this->actingAs($this->nutzer())->get('/verboten');

        $this->assertKeineMeldung();
    }
}
