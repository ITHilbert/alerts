<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Foundation\Application;
use ITHilbert\Alerts\Tests\TestCase;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * projekt-config: Teil-Config des Projekts (AK1), Listen ersetzen (AK2), config:cache, Projekt ohne eigene Config.
 *
 * Die Projekt-Config wird in `defineEnvironment`-Methoden gesetzt, also bevor der Provider registriert wird
 * (so wie Laravel die `config/alerts.php` des Projekts vor den Providern lädt).
 */
final class ProjektConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private function paket(): array
    {
        return require __DIR__.'/../../src/Config/alerts.php';
    }

    /** @param Application $app */
    protected function projektNur404Muster($app): void
    {
        $app['config']->set('alerts.erfassung', ['http_404_ignorieren' => ['entwickler/*']]);
    }

    /** @param Application $app */
    protected function projektMailNurMailer($app): void
    {
        $app['config']->set('alerts.mail', ['mailer' => 'zweit']);
    }

    /** @param Application $app */
    protected function projektListeEinElement($app): void
    {
        $app['config']->set('alerts.freigabe', ['middleware' => ['web']]);
    }

    /** @param Application $app */
    protected function projektLeereListe($app): void
    {
        $app['config']->set('alerts.freigabe', ['middleware' => []]);
    }

    /** @param Application $app */
    protected function projektSkalarImAbschnitt($app): void
    {
        $app['config']->set('alerts.telegram', ['timeout' => 9]);
    }

    // ── AK1 ──────────────────────────────────────────────────────────────────

    // AK1 – „diese Werte gelten“
    #[DefineEnvironment('projektNur404Muster')]
    public function test_gesetzter_wert_der_projekt_config_gilt(): void
    {
        $this->assertSame(['entwickler/*'], config('alerts.erfassung.http_404_ignorieren'));
    }

    // AK1 – „alle übrigen Schlüssel … auch die im selben Abschnitt (z. B. erfassung.exceptions)“
    #[DefineEnvironment('projektNur404Muster')]
    public function test_uebrige_schluessel_im_selben_abschnitt_behalten_den_paket_standard(): void
    {
        foreach (['exceptions', 'log', 'http_404', 'http_403'] as $schluessel) {
            $this->assertSame($this->paket()['erfassung'][$schluessel], config("alerts.erfassung.{$schluessel}"), "erfassung.{$schluessel}");
        }
    }

    // AK1 – „alle übrigen Schlüssel behalten den Paket-Standard“ (andere Abschnitte)
    #[DefineEnvironment('projektNur404Muster')]
    public function test_uebrige_abschnitte_behalten_den_paket_standard(): void
    {
        $paket = $this->paket();

        $this->assertSame($paket['drosselung'], config('alerts.drosselung'));
        $this->assertSame($paket['freigabe'], config('alerts.freigabe'));
        $this->assertSame($paket['channels'], config('alerts.channels'));
        $this->assertSame('https://api.telegram.org', config('alerts.telegram.api_url'));
    }

    // AK1 – das Verhalten folgt: Muster greift, 404-Erfassung bleibt aktiv
    #[DefineEnvironment('projektNur404Muster')]
    public function test_teil_config_wirkt_und_laesst_404_erfassung_aktiv(): void
    {
        $this->get('/entwickler/x', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertKeineMeldung();

        $this->get('/anderswo', ['Referer' => 'http://localhost/start'])->assertNotFound();
        $this->assertEineMeldung();
    }

    // AK1 – erfassung.exceptions bleibt aktiv
    #[DefineEnvironment('projektNur404Muster')]
    public function test_exception_erfassung_bleibt_mit_teil_config_aktiv(): void
    {
        $this->meldeException($this->ausnahme());

        $this->assertEineMeldung();
    }

    // AK1 – Skalar im Abschnitt gilt, übrige Schlüssel des Abschnitts bleiben
    #[DefineEnvironment('projektSkalarImAbschnitt')]
    public function test_einzelner_wert_in_einem_abschnitt_laesst_die_anderen_des_abschnitts_stehen(): void
    {
        $this->assertSame(9, config('alerts.telegram.timeout'));
        $this->assertSame('https://api.telegram.org', config('alerts.telegram.api_url'));
        $this->assertSame($this->paket()['telegram']['connect_timeout'], config('alerts.telegram.connect_timeout'));
        $this->assertSame($this->paket()['telegram']['fehlertext'], config('alerts.telegram.fehlertext'));
    }

    // AK1 – Abschnitt `mail` mit nur `mailer` verliert `fehlertext` nicht
    #[DefineEnvironment('projektMailNurMailer')]
    public function test_mail_abschnitt_mit_nur_mailer_behaelt_die_uebrigen_mail_schluessel(): void
    {
        $this->assertSame('zweit', config('alerts.mail.mailer'), 'Voraussetzung: Projektwert gilt.');
        $this->assertSame($this->paket()['mail']['fehlertext'], config('alerts.mail.fehlertext'));
    }

    // ── AK2 ──────────────────────────────────────────────────────────────────

    // AK2 – Liste des Projekts ersetzt die des Pakets vollständig (Paket: ['web', 'throttle:6,1'])
    #[DefineEnvironment('projektListeEinElement')]
    public function test_projekt_liste_ersetzt_paket_liste_vollstaendig(): void
    {
        $this->assertSame(['web', 'throttle:6,1'], $this->paket()['freigabe']['middleware'], 'Voraussetzung: Paket-Liste hat zwei Einträge.');
        $this->assertSame(['web'], config('alerts.freigabe.middleware'));
        $this->assertSame($this->paket()['freigabe']['prefix'], config('alerts.freigabe.prefix'));
    }

    // AK2 – auch eine leere Projekt-Liste ersetzt die Liste des Pakets
    #[DefineEnvironment('projektLeereListe')]
    public function test_leere_projekt_liste_ersetzt_paket_liste(): void
    {
        $this->assertSame([], config('alerts.freigabe.middleware'));
    }

    // AK2 – Liste wird nicht nach Position zusammengeführt (zweiter Eintrag des Pakets darf nicht überleben)
    #[DefineEnvironment('projektNur404Muster')]
    public function test_404_muster_liste_wird_nicht_mit_anderen_eintraegen_vermischt(): void
    {
        $this->assertCount(1, config('alerts.erfassung.http_404_ignorieren'));
    }

    // ── Randfall config:cache ───────────────────────────────────────────────

    // Randfall config:cache – Der Cache enthält nur Werte (keine Closures), sodass `config:cache` die Config schreiben kann
    #[DefineEnvironment('projektNur404Muster')]
    public function test_zusammengefuehrte_config_ist_cachebar(): void
    {
        $alerts = config('alerts');

        $this->assertSame(['entwickler/*'], $alerts['erfassung']['http_404_ignorieren'], 'Voraussetzung: Projektwert gilt.');
        $this->assertTrue($alerts['erfassung']['exceptions'] ?? null, 'Standard im selben Abschnitt bleibt erhalten.');
        $this->assertSame($alerts, eval('return '.var_export($alerts, true).';'));
        $this->assertStringNotContainsString('Closure', var_export($alerts, true));
    }

    // Randfall config:cache – „gecacht und gilt unverändert“: eine App, die die gecachte Config lädt, hat dieselben Werte
    #[DefineEnvironment('projektNur404Muster')]
    public function test_gecachte_config_gilt_unveraendert(): void
    {
        $pfad = $this->app->getCachedConfigPath();
        $this->assertFileDoesNotExist($pfad, 'Voraussetzung: noch kein Config-Cache.');
        $erwartet = config('alerts');

        file_put_contents($pfad, '<?php return '.var_export(['alerts' => $erwartet], true).';');

        try {
            $app = $this->createApplication();

            $this->assertTrue($app->configurationIsCached(), 'Aufbau: die App lädt den Cache.');
            $this->assertSame(['entwickler/*'], $app['config']->get('alerts.erfassung.http_404_ignorieren'));
            $this->assertSame($this->paket()['erfassung']['exceptions'], $app['config']->get('alerts.erfassung.exceptions'));
            $this->assertSame($this->paket()['telegram']['api_url'], $app['config']->get('alerts.telegram.api_url'));
        } finally {
            @unlink($pfad);
        }
    }

    // ── Randfall: Projekt ohne eigene config/alerts.php ─────────────────────

    // Randfall Projekt ohne eigene config/alerts.php (Verhalten unverändert, vor der Umsetzung schon grün)
    public function test_ohne_projekt_config_gelten_die_paket_standards(): void
    {
        $paket = $this->paket();

        $this->assertSame($paket['enabled'], config('alerts.enabled'));
        $this->assertSame($paket['channels'], config('alerts.channels'));
        $this->assertSame($paket['erfassung'], config('alerts.erfassung'));
        $this->assertSame($paket['drosselung'], config('alerts.drosselung'));
        $this->assertSame($paket['freigabe'], config('alerts.freigabe'));
    }

    // Randfall Projekt ohne eigene config/alerts.php: Versand wie bisher über alle aktiven Kanäle
    public function test_ohne_projekt_config_geht_ein_alert_ueber_alle_aktiven_kanaele(): void
    {
        $this->meldeException($this->ausnahme());

        $this->assertEineMeldung();
    }
}
