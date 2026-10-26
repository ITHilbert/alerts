<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Env;
use ITHilbert\Alerts\Tests\TestCase;

/** 404-Rauschen: Referer gleich angefragter URL (AK1, AK2), ignorierte Pfade (AK3, AK4), Konfiguration (AK5). */
final class Http404RauschenTest extends TestCase
{
    private const ENV = 'ALERTS_HTTP_404_IGNORIEREN';

    /**
     * Zeigt zuerst, dass die Anfrage mit gleichem Pfad im Referer nicht gemeldet wird,
     * dann (Gegenprobe) dass dieselbe Anfrage mit anderem Referer-Pfad gemeldet wird.
     */
    private function pruefeGleichheit(string $angefragt, string $referer): void
    {
        $this->get($angefragt, ['Referer' => $referer])->assertNotFound();
        $this->assertKeineMeldung();

        $this->get($angefragt, ['Referer' => 'http://localhost/ganz-andere-seite'])->assertNotFound();
        $this->assertEineMeldung();
    }

    /** @param list<string> $muster */
    private function pruefeIgnoriert(array $muster, string $angefragt, string $gegenprobePfad = '/nicht-passender-pfad'): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => $muster]);

        $this->get($angefragt, ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung();

        $this->get($gegenprobePfad, ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }

    /** @return array<mixed> */
    private function konfigMitEnv(?string $wert): array
    {
        $repo = Env::getRepository();
        $vorher = $repo->get(self::ENV);
        $wert === null ? $repo->clear(self::ENV) : $repo->set(self::ENV, $wert);

        try {
            return require __DIR__.'/../../src/Config/alerts.php';
        } finally {
            $vorher === null ? $repo->clear(self::ENV) : $repo->set(self::ENV, $vorher);
        }
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1
    public function test_referer_mit_identischem_pfad_wird_nicht_gemeldet(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht', 'http://localhost/gibt-es-nicht');
    }

    // AK1
    public function test_referer_pfad_nur_in_grossschreibung_anders_wird_nicht_gemeldet(): void
    {
        $this->pruefeGleichheit('/access/access-programmierer-brakel', 'http://localhost/access/Access-Programmierer-Brakel');
    }

    // AK1
    public function test_angefragter_pfad_nur_in_grossschreibung_anders_wird_nicht_gemeldet(): void
    {
        $this->pruefeGleichheit('/Access/Access-Programmierer-Brakel', 'http://localhost/access/access-programmierer-brakel');
    }

    // AK1
    public function test_prozent_kodierung_im_referer_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/strasse-ß', 'http://localhost/strasse-%C3%9F');
    }

    // AK1
    public function test_prozent_kodierung_im_angefragten_pfad_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/strasse-%C3%9F', 'http://localhost/strasse-ß');
    }

    // AK1
    public function test_prozent_kodierung_in_kleinen_hexziffern_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/strasse-%C3%9F', 'http://localhost/strasse-%c3%9f');
    }

    // AK1
    public function test_abschliessender_schraegstrich_im_referer_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht', 'http://localhost/gibt-es-nicht/');
    }

    // AK1
    public function test_abschliessender_schraegstrich_im_angefragten_pfad_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht/', 'http://localhost/gibt-es-nicht');
    }

    // AK1
    public function test_anderes_schema_im_referer_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht', 'https://localhost/gibt-es-nicht');
    }

    // AK1
    public function test_anderer_query_string_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht?a=1', 'http://localhost/gibt-es-nicht?b=2');
    }

    // AK1
    public function test_query_string_nur_im_referer_gilt_als_gleich(): void
    {
        $this->pruefeGleichheit('/gibt-es-nicht', 'http://localhost/gibt-es-nicht?q=suche');
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2
    public function test_anderer_referer_pfad_wird_wie_bisher_gemeldet(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/start'])->assertNotFound();

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertStringContainsString('Quelle: 404', $mail);
        $this->assertStringContainsString('/gibt-es-nicht', $mail);
        $this->assertStringContainsString('/start', $mail);
    }

    // AK2
    public function test_pfad_der_nur_als_praefix_gleicht_wird_gemeldet(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/gibt-es-nicht-doch'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // AK2
    public function test_referer_startseite_bei_tiefem_pfad_wird_gemeldet(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // ── AK3 ──────────────────────────────────────────────────────────────────

    // AK3
    public function test_pfad_mit_platzhalter_muster_wird_nicht_gemeldet(): void
    {
        $this->pruefeIgnoriert(['entwickler/*', 'manifest.json'], '/entwickler/alt/seite');
    }

    // AK3
    public function test_pfad_mit_festem_muster_wird_nicht_gemeldet(): void
    {
        $this->pruefeIgnoriert(['entwickler/*', 'manifest.json'], '/manifest.json');
    }

    // AK3
    public function test_muster_ignoriert_gross_und_kleinschreibung_im_pfad(): void
    {
        $this->pruefeIgnoriert(['entwickler/*'], '/Entwickler/Alt');
    }

    // AK3
    public function test_muster_ignoriert_gross_und_kleinschreibung_im_muster(): void
    {
        $this->pruefeIgnoriert(['ENTWICKLER/*'], '/entwickler/alt');
    }

    // AK3
    public function test_muster_ohne_fuehrenden_schraegstrich_passt_auch_unter_mehreren_eintraegen(): void
    {
        $this->pruefeIgnoriert(['andere/*', 'entwickler/*'], '/entwickler/x');
    }

    // Randfall: Muster `*` allein
    public function test_muster_stern_allein_ignoriert_alle_404_mit_internem_referer(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['*']]);

        $this->get('/ganz/woanders', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->get('/irgendwas', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung();

        config(['alerts.erfassung.http_404_ignorieren' => []]); // Gegenprobe: ohne Muster wird gemeldet
        $this->get('/irgendwas', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // Randfall: 404 ohne Referer, unabhängig von den Mustern
    public function test_404_ohne_referer_bleibt_ohne_meldung_trotz_muster(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*']]);

        $this->get('/andere/seite', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung(); // Gegenprobe: Aufbau meldet

        $this->get('/entwickler/neu')->assertNotFound();
        $this->get('/nochmal/andere/seite')->assertNotFound();
        $this->assertCount(1, $this->mails());
    }

    // Randfall: 404 mit fremdem Referer, unabhängig von den Mustern
    public function test_404_mit_fremdem_referer_bleibt_ohne_meldung_trotz_muster(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*']]);

        $this->get('/andere/seite', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung(); // Gegenprobe

        $this->get('/nicht/gelistet', ['Referer' => 'https://www.google.com/search'])->assertNotFound();
        $this->get('/entwickler/neu', ['Referer' => 'https://www.google.com/search'])->assertNotFound();
        $this->assertCount(1, $this->mails());
    }

    // ── AK4 ──────────────────────────────────────────────────────────────────

    // AK4
    public function test_pfad_ohne_passendes_muster_wird_wie_bisher_gemeldet(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*', 'manifest.json']]);

        $this->get('/andere/seite', ['Referer' => 'http://localhost/start'])->assertNotFound();

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertStringContainsString('Quelle: 404', $mail);
        $this->assertStringContainsString('/andere/seite', $mail);
    }

    // AK4
    public function test_festes_muster_passt_nicht_auf_pfad_darunter(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['manifest.json']]);

        $this->get('/manifest.json', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung(); // Gegenprobe: das Muster greift

        $this->get('/manifest.json/mehr', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // AK4
    public function test_platzhalter_muster_passt_nicht_auf_anderes_praefix(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*']]);

        $this->get('/entwickler/x', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung(); // Gegenprobe

        $this->get('/neu/entwickler/x', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // AK4 / AK5 (Standard leer = Verhalten unverändert)
    public function test_ohne_muster_wird_wie_bisher_gemeldet(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => []]);

        $this->get('/entwickler/alt', ['Referer' => 'http://localhost/start'])->assertNotFound();

        $this->assertEineMeldung();
    }

    // ── 403 unberührt ────────────────────────────────────────────────────────

    // Randfall: 403 bleibt unberührt (Referer = angefragter Pfad)
    public function test_403_wird_trotz_gleichem_referer_gemeldet(): void
    {
        $nutzer = (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);

        $this->actingAs($nutzer)->get('/verboten', ['Referer' => 'http://localhost/verboten'])->assertForbidden();

        $this->assertEineMeldung();
        $this->assertStringContainsString('Quelle: 403', $this->mailText($this->mail()));
    }

    // Randfall: 403 bleibt unberührt (Pfad passt auf ein Muster)
    public function test_403_wird_trotz_passendem_muster_gemeldet(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['*']]);
        $nutzer = (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);

        $this->actingAs($nutzer)->get('/verboten')->assertForbidden();

        $this->assertEineMeldung();
        $this->assertStringContainsString('Quelle: 403', $this->mailText($this->mail()));
    }

    // ── AK5 ──────────────────────────────────────────────────────────────────

    // AK5
    public function test_standard_ist_leere_liste(): void
    {
        $this->assertSame([], config('alerts.erfassung.http_404_ignorieren'));
    }

    // AK5 / Randfall 1: nicht gesetzt
    public function test_env_nicht_gesetzt_ergibt_leere_liste(): void
    {
        $config = $this->konfigMitEnv(null);

        $this->assertArrayHasKey('http_404_ignorieren', $config['erfassung']);
        $this->assertSame([], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5 / Randfall 1: leer gesetzt
    public function test_env_leer_gesetzt_ergibt_leere_liste(): void
    {
        $config = $this->konfigMitEnv('');

        $this->assertArrayHasKey('http_404_ignorieren', $config['erfassung']);
        $this->assertSame([], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5
    public function test_env_kommagetrennte_liste_wird_zum_array(): void
    {
        $config = $this->konfigMitEnv('entwickler/*,manifest.json');

        $this->assertSame(['entwickler/*', 'manifest.json'], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5
    public function test_env_entfernt_leerzeichen_um_die_eintraege(): void
    {
        $config = $this->konfigMitEnv('  entwickler/* ,   manifest.json  ');

        $this->assertSame(['entwickler/*', 'manifest.json'], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5
    public function test_env_ignoriert_leere_eintraege(): void
    {
        $config = $this->konfigMitEnv(',entwickler/*,, ,manifest.json,');

        $this->assertSame(['entwickler/*', 'manifest.json'], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5 / Randfall 1: nur Leerzeichen und Kommas
    public function test_env_nur_aus_leerzeichen_und_kommas_ergibt_leere_liste(): void
    {
        $config = $this->konfigMitEnv(' , ,');

        $this->assertSame([], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5
    public function test_env_einzelner_eintrag_ergibt_array_mit_einem_muster(): void
    {
        $config = $this->konfigMitEnv('entwickler/*');

        $this->assertSame(['entwickler/*'], $config['erfassung']['http_404_ignorieren']);
    }

    // AK5
    public function test_array_in_veroeffentlichter_config_wirkt(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*']]);

        $this->get('/entwickler/x', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung();

        config(['alerts.erfassung.http_404_ignorieren' => []]); // Gegenprobe: ohne Muster wird gemeldet
        $this->get('/entwickler/x', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }
}
