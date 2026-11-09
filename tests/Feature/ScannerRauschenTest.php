<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Cache\Repository;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Tests\Fakes\KaputterCacheStore;
use ITHilbert\Alerts\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

/** Scanner-404 nicht einzeln melden (#626): Scanner-Liste, Sammelmeldung je IP, IP und User-Agent. */
final class ScannerRauschenTest extends TestCase
{
    private const IP = '203.0.113.57';

    private const SCANNER_PFADE = [
        '/.env', '/config.env', '/aws.env', '/.env.local', '/backend/.env', '/.git/config', '/wp-json',
        '/wp-json/wp/v2/users', '/wp-login.php', '/wp-admin/setup-config.php', '/blog/wp-login.php',
        '/xmlrpc.php', '/phpmyadmin/index.php', '/graphql', '/api/graphql', '/v1/graphql',
        '/mailto:%6B%6F%6E%74%61%6B%74%40example.com', '/tel:+495251123456', '/backup.sql', '/index.php.bak',
    ];

    /** AK18 (#946): Sitemap-Pfade, die Scanner abklopfen. */
    private const SITEMAP_SCANNER_PFADE = [
        '/sitemap_index.xml', '/wp-sitemap.xml', '/sitemap.xml.gz', '/sitemap_index.xml.gz',
        '/sitemap-index.xml', '/sitemap-index.xml.gz', '/sitemaps.xml',
    ];

    // ── Helfer ───────────────────────────────────────────────────────────────

    private function nutzer(): User
    {
        return (new User)->forceFill(['id' => 42, 'name' => 'Erika Muster', 'email' => 'erika@example.com']);
    }

    /** 404-Anfrage mit internem Referer von einer IP; danach vergeht eine Sekunde. */
    private function anfrage(string $pfad, string $ip = self::IP, string $ua = 'TestScanner/1.0', ?string $referer = 'http://localhost/start', bool $weiter = true): void
    {
        $headers = $referer === null ? [] : ['Referer' => $referer];
        $this->withServerVariables(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua])->get(rtrim((string) config('app.url'), '/').$pfad, $headers)->assertNotFound(); // absolut: `mailto:`/`tel:` hält Laravel sonst für eine fertige URL

        if ($weiter) {
            $this->travel(1)->seconds();
        }
    }

    /** $anzahl verschiedene meldepflichtige Pfade `/<praefix>-<n>` von einer IP. */
    private function serie(int $anzahl, string $praefix, string $ip = self::IP, string $ua = 'TestScanner/1.0'): void
    {
        for ($i = 1; $i <= $anzahl; $i++) {
            $this->anfrage("/{$praefix}-{$i}", $ip, $ua);
        }
    }

    /** @return list<SentMessage> */
    private function haeufungen(): array
    {
        return array_values(array_filter(
            $this->mails(),
            fn (SentMessage $mail): bool => str_contains($this->betreff($mail), 'Häufung von 404 (eine Quelle)'),
        ));
    }

    private function haeufungMail(int $index = 0): string
    {
        $mail = $this->haeufungen()[$index] ?? null;
        $this->assertNotNull($mail, "Sammelmeldung #{$index} fehlt.");

        return $this->mailText($mail);
    }

    /** @return list<string> Telegram-Texte aller Sammelmeldungen. */
    private function haeufungenTelegram(): array
    {
        $texte = [];
        foreach (array_keys($this->telegramAnfragen()) as $i) {
            if (str_contains($this->telegramText($i), 'Häufung von 404 (eine Quelle)')) {
                $texte[] = $this->telegramText($i);
            }
        }

        return $texte;
    }

    private function mailHtml(int $index = 0): string
    {
        $original = $this->mail($index)->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $original);

        return (string) $original->getHtmlBody();
    }

    /** @param array<string, string> $env @return array<mixed> */
    private function konfig(array $env = []): array
    {
        $repo = Env::getRepository();
        $vorher = [];
        foreach ($env as $name => $wert) {
            $vorher[$name] = $repo->get($name);
            $repo->set($name, $wert);
        }

        try {
            return require __DIR__.'/../../src/Config/alerts.php';
        } finally {
            foreach ($vorher as $name => $wert) {
                $wert === null ? $repo->clear($name) : $repo->set($name, $wert);
            }
        }
    }

    // ── A. Standard-Ignorierliste ────────────────────────────────────────────

    // AK1
    public function test_scanner_pfade_aus_dem_paket_standard_werden_nicht_gemeldet(): void
    {
        foreach (self::SCANNER_PFADE as $pfad) {
            $this->anfrage($pfad, weiter: false);
            $this->assertKeineMeldung();
        }

        $this->anfrage('/leistungen/alt');
        $this->assertEineMeldung();
    }

    // AK2
    public function test_scanner_liste_steht_als_array_in_der_paket_config(): void
    {
        $liste = $this->konfig()['erfassung']['http_404_scanner'] ?? null;

        $this->assertIsArray($liste);
        $this->assertNotSame([], $liste);
        foreach ($liste as $muster) {
            $this->assertIsString($muster);
            $this->assertNotSame('', $muster);
        }
    }

    // AK2
    public function test_scanner_liste_hat_keine_umgebungsvariable(): void
    {
        $standard = $this->konfig()['erfassung']['http_404_scanner'] ?? null;
        $this->assertIsArray($standard);
        $this->assertNotSame([], $standard);

        foreach (['ALERTS_HTTP_404_SCANNER', 'ALERTS_HTTP_404_SCANNER_LISTE'] as $name) {
            $this->assertSame($standard, $this->konfig([$name => 'geheim/*'])['erfassung']['http_404_scanner'], "{$name} darf die Liste nicht ändern.");
        }
    }

    // AK2
    public function test_muster_der_scanner_liste_ohne_fuehrenden_schraegstrich_mit_platzhalter_und_ohne_gross_kleinschreibung(): void
    {
        config(['alerts.erfassung.http_404_scanner' => ['Geheim/*', 'FALLE.php']]);

        $this->anfrage('/geheim/unterseite/x', weiter: false);
        $this->anfrage('/GEHEIM/X', weiter: false);
        $this->anfrage('/falle.PHP', weiter: false);
        $this->assertKeineMeldung();

        $this->anfrage('/oeffentlich/x');
        $this->assertEineMeldung();
    }

    // AK2
    public function test_muster_der_scanner_liste_gelten_fuer_den_dekodierten_pfad(): void
    {
        config(['alerts.erfassung.http_404_scanner' => ['geheim seite/*']]);

        $this->anfrage('/geheim%20seite/x', weiter: false);
        $this->assertKeineMeldung();

        $this->anfrage('/geheim-seite/x');
        $this->assertEineMeldung();
    }

    // AK3
    public function test_projekt_liste_und_scanner_standard_gelten_zusammen(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['entwickler/*', 'manifest.json']]);

        $this->anfrage('/entwickler/alt', weiter: false);
        $this->anfrage('/manifest.json', weiter: false);
        $this->anfrage('/config.env', weiter: false);
        $this->assertKeineMeldung();

        $this->anfrage('/leistungen/alt');
        $this->assertEineMeldung();
    }

    // AK4
    public function test_leere_scanner_liste_schaltet_die_scanner_muster_ab(): void
    {
        $this->anfrage('/config.env', weiter: false);
        $this->assertKeineMeldung();

        config(['alerts.erfassung.http_404_scanner' => []]);
        $this->anfrage('/config.env');

        $this->assertEineMeldung();
        $this->assertStringContainsString('/config.env', $this->mailText($this->mail()));
    }

    // AK4
    public function test_eigene_scanner_liste_ersetzt_den_standard(): void
    {
        config(['alerts.erfassung.http_404_scanner' => ['geheim/*']]);

        $this->anfrage('/geheim/x', weiter: false);
        $this->assertKeineMeldung();

        $this->anfrage('/config.env');
        $this->assertEineMeldung();
        $this->assertStringContainsString('/config.env', $this->mailText($this->mail()));
    }

    // ── B. Sammelmeldung ─────────────────────────────────────────────────────

    // AK5
    public function test_die_ersten_fuenf_404_einer_ip_werden_einzeln_gemeldet(): void
    {
        $this->serie(5, 'seite');

        $this->assertCount(5, $this->mails());
        $this->assertCount(5, $this->telegramAnfragen());
        $this->assertCount(0, $this->haeufungen());
        foreach ($this->mails() as $mail) {
            $this->assertStringContainsString('Seite nicht gefunden (404)', $this->betreff($mail));
        }
    }

    // AK5
    public function test_der_sechste_404_loest_genau_eine_sammelmeldung_aus(): void
    {
        $this->serie(6, 'seite');

        $this->assertCount(6, $this->mails());
        $this->assertCount(6, $this->telegramAnfragen());
        $this->assertCount(1, $this->haeufungen());
        $this->assertCount(1, $this->haeufungenTelegram());
    }

    // AK5
    public function test_weitere_404_der_ip_im_fenster_erzeugen_keine_meldung(): void
    {
        $this->serie(6, 'seite');
        $this->assertCount(6, $this->mails(), 'Aufbau: bis zur Sammelmeldung sind es 6 Meldungen.');

        $this->anfrage('/weiter-1');
        $this->anfrage('/weiter-2');
        $this->anfrage('/weiter-3');

        $this->assertCount(6, $this->mails());
        $this->assertCount(6, $this->telegramAnfragen());
    }

    // AK5
    public function test_fuenfundzwanzig_verschiedene_pfade_in_fuenfundzwanzig_sekunden_ergeben_sechs_meldungen(): void
    {
        $this->serie(25, 'seite');

        $this->assertCount(6, $this->mails());
        $this->assertCount(6, $this->telegramAnfragen());
        $this->assertCount(1, $this->haeufungen());
    }

    // AK6
    public function test_fuenfundzwanzig_env_pfade_mit_internem_referer_ergeben_hoechstens_eine_meldung(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->anfrage("/scan-{$i}.env");
        }

        $this->assertLessThanOrEqual(1, count($this->mails()));
        $this->assertLessThanOrEqual(1, count($this->telegramAnfragen()));
    }

    // AK6
    public function test_ohne_scanner_liste_greift_bei_env_pfaden_die_sammelmeldung(): void
    {
        config(['alerts.erfassung.http_404_scanner' => []]);

        for ($i = 1; $i <= 25; $i++) {
            $this->anfrage("/scan-{$i}.env");
        }

        $this->assertCount(6, $this->mails());
        $this->assertCount(1, $this->haeufungen());
    }

    // AK7
    public function test_sammelmeldung_hat_titel_und_quelle_404(): void
    {
        $this->serie(6, 'seite');

        $text = $this->haeufungMail();
        $this->assertStringContainsString('Quelle: 404', $text);
        $this->assertStringContainsString('[Testprojekt] Häufung von 404 (eine Quelle)', $this->betreff($this->haeufungen()[0]));
        $this->assertStringContainsString('Häufung von 404 (eine Quelle)', $this->haeufungenTelegram()[0]);
        $this->assertStringContainsString('Quelle: 404', $this->haeufungenTelegram()[0]);
    }

    // AK7
    public function test_sammelmeldung_nennt_anzahl_und_fenster(): void
    {
        $this->serie(6, 'seite');

        $this->assertStringContainsString('Anzahl: 6 × 404 innerhalb von 60 s', $this->haeufungMail());
        $this->assertStringContainsString('Anzahl: 6 × 404 innerhalb von 60 s', $this->haeufungenTelegram()[0]);
    }

    // AK7
    public function test_sammelmeldung_nennt_die_pfade_in_reihenfolge_des_eintreffens(): void
    {
        $this->serie(6, 'seite');

        foreach ([$this->haeufungMail(), $this->haeufungenTelegram()[0]] as $text) {
            $this->assertSame(1, preg_match('/^\s*Pfade: (.*)$/mu', $text, $treffer), 'Zeile „Pfade:“ fehlt.');
            $position = -1;
            for ($i = 1; $i <= 6; $i++) {
                $gefunden = strpos($treffer[1], "/seite-{$i}");
                $this->assertNotFalse($gefunden, "/seite-{$i} fehlt in den Pfaden.");
                $this->assertGreaterThan($position, $gefunden, 'Pfade nicht in Reihenfolge des Eintreffens.');
                $position = $gefunden;
            }
        }
    }

    // AK7
    public function test_sammelmeldung_nennt_hoechstens_zehn_pfade(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 12, 'fenster_sekunden' => 60]]);

        $this->serie(13, 'seite');

        $this->assertCount(1, $this->haeufungen());
        $this->assertStringContainsString('Anzahl: 13 × 404 innerhalb von 60 s', $this->haeufungenTelegram()[0]);
        $this->assertSame(1, preg_match('/^\s*Pfade: (.*)$/mu', $this->haeufungenTelegram()[0], $treffer));
        $this->assertSame(10, preg_match_all('#/seite-\d+#', $treffer[1]));
    }

    // AK7
    public function test_sammelmeldung_zeigt_ip_und_user_agent_des_ausloesenden_aufrufs(): void
    {
        $this->serie(5, 'seite', ua: 'Erster/1.0');
        $this->anfrage('/seite-6', ua: 'Letzter/2.0');

        foreach ([$this->haeufungMail(), $this->haeufungenTelegram()[0]] as $text) {
            $this->assertStringContainsString('IP: '.self::IP, $text);
            $this->assertStringContainsString('User-Agent: Letzter/2.0', $text);
            $this->assertStringNotContainsString('Erster/1.0', $text);
        }
    }

    // AK7
    public function test_fingerabdruck_der_sammelmeldung_ist_je_ip_gleich_und_fuer_andere_ips_verschieden(): void
    {
        config(['alerts.drosselung.sperre_stunden' => 1]);

        $this->serie(6, 'a', self::IP);
        $this->serie(6, 'b', '203.0.113.99');
        $this->assertCount(2, $this->haeufungen(), 'Aufbau: zwei IPs, zwei Sammelmeldungen.');
        $erste = $this->fingerabdruckAus($this->haeufungMail(0));
        $andere = $this->fingerabdruckAus($this->haeufungMail(1));

        $this->travel(2)->hours();
        $this->serie(6, 'c', self::IP);
        $this->assertCount(3, $this->haeufungen(), 'Aufbau: nach der Sperre kommt die nächste Sammelmeldung.');

        $this->assertSame($erste, $this->fingerabdruckAus($this->haeufungMail(2)));
        $this->assertNotSame($erste, $andere);
    }

    // AK7
    public function test_fingerabdruck_der_sammelmeldung_erfuellt_das_format_und_enthaelt_die_ip_nicht(): void
    {
        $this->serie(6, 'seite');

        $fingerabdruck = $this->fingerabdruckAus($this->haeufungMail());
        $this->assertMatchesRegularExpression(Fingerabdruck::MUSTER, $fingerabdruck);
        foreach ([self::IP, '203-0-113-57', '20301135'.'7'] as $klartext) {
            $this->assertStringNotContainsString($klartext, $fingerabdruck);
        }
        $this->assertStringNotContainsString('seite-', $fingerabdruck, 'Der Pfad gehört nicht in den Fingerabdruck der Sammelmeldung.');
    }

    // AK8
    public function test_zweite_haeufung_innerhalb_der_sperre_wird_nicht_verschickt_sondern_gezaehlt(): void
    {
        $this->serie(6, 'a');
        $this->assertCount(1, $this->haeufungen(), 'Aufbau: die erste Häufung wird verschickt.');

        $this->travel(61)->seconds();
        $this->serie(6, 'b');
        $this->assertCount(1, $this->haeufungen(), 'Zweite Häufung innerhalb der Sperre darf nicht raus.');
        $this->assertCount(11, $this->mails(), '5 Einzelmeldungen der zweiten Runde kommen weiter raus.');

        $this->travel(25)->hours();
        $this->serie(6, 'c');
        $this->assertCount(2, $this->haeufungen(), 'Nach Ablauf der Sperre kommt die nächste Sammelmeldung.');
        $this->assertStringContainsString('1× seit letzter Meldung', $this->haeufungMail(1));
        $this->assertStringContainsString('1× seit letzter Meldung', $this->haeufungenTelegram()[1]);
        $this->assertStringNotContainsString('seit letzter Meldung', $this->haeufungMail(0));
    }

    // AK8
    public function test_nach_alerts_reset_kommt_die_naechste_sammelmeldung(): void
    {
        $this->serie(6, 'a');
        $fingerabdruck = $this->fingerabdruckAus($this->haeufungMail());

        $this->travel(61)->seconds();
        $this->serie(6, 'b');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: ohne Reset bleibt die zweite Häufung aus.');

        $this->artisan('alerts:reset', ['fingerabdruck' => $fingerabdruck])->assertExitCode(0);

        $this->travel(61)->seconds();
        $this->serie(6, 'c');
        $this->assertCount(2, $this->haeufungen());
    }

    // AK8
    public function test_sperre_stunden_gilt_auch_fuer_die_sammelmeldung(): void
    {
        config(['alerts.drosselung.sperre_stunden' => 1]);

        $this->serie(6, 'a');
        $this->travel(2)->hours();
        $this->serie(6, 'b');

        $this->assertCount(2, $this->haeufungen());
    }

    // AK9
    public function test_nach_ablauf_des_fensters_zaehlt_die_ip_von_vorn(): void
    {
        $this->serie(5, 'a');
        $this->assertCount(5, $this->mails());

        $this->travel(56)->seconds(); // 61 s nach dem ersten gezählten 404
        $this->anfrage('/b-1');

        $this->assertCount(6, $this->mails());
        $this->assertCount(0, $this->haeufungen());
        $this->assertStringContainsString('Seite nicht gefunden (404)', $this->betreff($this->mail(5)));
    }

    // AK9
    public function test_nach_ablauf_des_fensters_braucht_es_wieder_mehr_als_fuenf_404(): void
    {
        $this->serie(5, 'a');
        $this->travel(56)->seconds();

        $this->serie(5, 'b');
        $this->assertCount(10, $this->mails());
        $this->assertCount(0, $this->haeufungen());

        $this->anfrage('/b-6');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: im neuen Fenster löst der 6. aus.');
    }

    // AK10
    public function test_zwei_ips_mit_je_fuenf_404_im_selben_fenster_werden_alle_einzeln_gemeldet(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->anfrage("/a-{$i}", '203.0.113.10', weiter: false);
            $this->anfrage("/b-{$i}", '203.0.113.20');
        }

        $this->assertCount(10, $this->mails());
        $this->assertCount(10, $this->telegramAnfragen());
        $this->assertCount(0, $this->haeufungen());

        $this->anfrage('/a-6', '203.0.113.10');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: die 6. Anfrage einer IP löst aus.');
    }

    // AK10
    public function test_nicht_meldepflichtige_404_zaehlen_nicht_mit(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['ignoriert/*']]);

        $this->serie(3, 'a');
        $this->anfrage('/fremd-1', referer: 'http://fremd.example/start');
        $this->anfrage('/fremd-2', referer: 'http://fremd.example/start');
        $this->anfrage('/ohne-referer-1', referer: null);
        $this->anfrage('/ohne-referer-2', referer: null);
        $this->anfrage('/selbst-1', referer: 'http://localhost/selbst-1');
        $this->anfrage('/selbst-2', referer: 'http://localhost/selbst-2');
        $this->anfrage('/ignoriert/1');
        $this->anfrage('/ignoriert/2');
        $this->anfrage('/config.env');
        $this->anfrage('/aws.env');
        $this->assertCount(3, $this->mails(), 'Aufbau: nicht meldepflichtige 404 erzeugen keine Meldung.');

        $this->serie(2, 'b');
        $this->assertCount(5, $this->mails());
        $this->assertCount(0, $this->haeufungen(), 'Nicht meldepflichtige 404 dürfen nicht mitzählen.');

        $this->anfrage('/b-3');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: der 6. meldepflichtige 404 löst aus.');
    }

    // AK11
    public function test_einstellungen_haben_die_standardwerte_5_und_60(): void
    {
        $sammeln = $this->konfig()['erfassung']['http_404_sammeln'] ?? null;

        $this->assertIsArray($sammeln);
        $this->assertEquals(5, $sammeln['ab'] ?? null);
        $this->assertEquals(60, $sammeln['fenster_sekunden'] ?? null);
    }

    // AK11
    public function test_einstellungen_lesen_die_umgebungsvariablen(): void
    {
        $sammeln = $this->konfig(['ALERTS_HTTP_404_SAMMELN_AB' => '7', 'ALERTS_HTTP_404_SAMMELN_FENSTER' => '90'])['erfassung']['http_404_sammeln'] ?? null;

        $this->assertIsArray($sammeln);
        $this->assertEquals(7, $sammeln['ab'] ?? null);
        $this->assertEquals(90, $sammeln['fenster_sekunden'] ?? null);
    }

    // AK11
    public function test_ab_ist_einstellbar(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 3, 'fenster_sekunden' => 60]]);

        $this->serie(3, 'a');
        $this->assertCount(0, $this->haeufungen());

        $this->anfrage('/a-4');
        $this->assertCount(1, $this->haeufungen());
        $this->assertCount(4, $this->mails());
    }

    // AK11
    public function test_fenster_sekunden_ist_einstellbar(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 5, 'fenster_sekunden' => 10]]);

        $this->serie(5, 'a');
        $this->travel(7)->seconds(); // 12 s nach dem ersten gezählten 404
        $this->anfrage('/a-6');

        $this->assertCount(6, $this->mails());
        $this->assertCount(0, $this->haeufungen());
        $this->assertStringContainsString('Seite nicht gefunden (404)', $this->betreff($this->mail(5)));
    }

    // AK11
    public function test_fenster_sekunden_erscheint_in_der_sammelmeldung(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 5, 'fenster_sekunden' => 10]]);

        $this->serie(6, 'a');

        $this->assertStringContainsString('Anzahl: 6 × 404 innerhalb von 10 s', $this->haeufungMail());
    }

    /** @return array<string, array{mixed}> */
    public static function ungueltigeWerte(): array
    {
        return ['null' => [0], 'negativ' => [-1], 'text' => ['abc'], 'leer' => [''], 'null als text' => ['0']];
    }

    /** @dataProvider ungueltigeWerte */
    // AK11
    #[DataProvider('ungueltigeWerte')]
    public function test_ungueltiges_ab_schaltet_das_sammeln_ab(mixed $wert): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => $wert, 'fenster_sekunden' => 60]]);

        $this->serie(12, 'a');

        $this->assertCount(12, $this->mails());
        $this->assertCount(0, $this->haeufungen());
    }

    /** @dataProvider ungueltigeWerte */
    // AK11
    #[DataProvider('ungueltigeWerte')]
    public function test_ungueltige_fenster_sekunden_schalten_das_sammeln_ab(mixed $wert): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 5, 'fenster_sekunden' => $wert]]);

        $this->serie(12, 'a');

        $this->assertCount(12, $this->mails());
        $this->assertCount(0, $this->haeufungen());
    }

    // AK11
    public function test_gegenprobe_gueltige_werte_sammeln_im_selben_aufbau(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 5, 'fenster_sekunden' => 60]]);

        $this->serie(12, 'a');

        $this->assertCount(6, $this->mails());
    }

    // AK11
    public function test_nur_ab_gesetzt_behaelt_den_standard_fuer_das_fenster(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['ab' => 3]]);

        $this->serie(3, 'a');
        $this->travel(30)->seconds(); // innerhalb des Standard-Fensters von 60 s
        $this->anfrage('/a-4');

        $this->assertCount(1, $this->haeufungen());
        $this->assertStringContainsString('innerhalb von 60 s', $this->haeufungMail());
    }

    // AK11
    public function test_nur_fenster_gesetzt_behaelt_den_standard_fuer_ab(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => ['fenster_sekunden' => 20]]);

        $this->serie(5, 'a');
        $this->assertCount(0, $this->haeufungen(), 'Standard ab = 5: die ersten fünf einzeln.');

        $this->anfrage('/a-6');
        $this->assertCount(1, $this->haeufungen());
        $this->assertStringContainsString('innerhalb von 20 s', $this->haeufungMail());
    }

    // AK11
    public function test_ohne_jede_einstellung_gelten_die_standardwerte(): void
    {
        config(['alerts.erfassung.http_404_sammeln' => []]);

        $this->serie(5, 'a');
        $this->assertCount(0, $this->haeufungen());

        $this->anfrage('/a-6');
        $this->assertCount(1, $this->haeufungen());
        $this->assertStringContainsString('innerhalb von 60 s', $this->haeufungMail());
    }

    // ── C. IP und User-Agent ─────────────────────────────────────────────────

    // AK12
    public function test_einzelne_404_meldung_enthaelt_ip_und_user_agent_in_mail_und_telegram(): void
    {
        $this->anfrage('/leistungen/alt', self::IP, 'Mozilla/5.0 Testbrowser');

        $this->assertEineMeldung();
        foreach ([$this->mailText($this->mail()), $this->telegramText()] as $text) {
            $this->assertStringContainsString('IP: '.self::IP, $text);
            $this->assertStringContainsString('User-Agent: Mozilla/5.0 Testbrowser', $text);
        }
    }

    // AK12
    public function test_sammelmeldung_enthaelt_ip_und_user_agent_in_mail_und_telegram(): void
    {
        $this->serie(6, 'a', self::IP, 'Mozilla/5.0 Testbrowser');

        foreach ([$this->haeufungMail(), $this->haeufungenTelegram()[0]] as $text) {
            $this->assertStringContainsString('IP: '.self::IP, $text);
            $this->assertStringContainsString('User-Agent: Mozilla/5.0 Testbrowser', $text);
        }
    }

    // AK12
    public function test_403_meldung_enthaelt_ip_und_user_agent_in_mail_und_telegram(): void
    {
        $this->actingAs($this->nutzer())
            ->withServerVariables(['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => 'Mozilla/5.0 Testbrowser'])
            ->get('/verboten')->assertForbidden();

        $this->assertEineMeldung();
        foreach ([$this->mailText($this->mail()), $this->telegramText()] as $text) {
            $this->assertStringContainsString('Quelle: 403', $text);
            $this->assertStringContainsString('IP: '.self::IP, $text);
            $this->assertStringContainsString('User-Agent: Mozilla/5.0 Testbrowser', $text);
        }
    }

    // AK12
    public function test_user_agent_ueber_200_zeichen_wird_auf_200_zeichen_gekuerzt(): void
    {
        $this->anfrage('/lang-1', ua: str_repeat('a', 200));
        $this->anfrage('/lang-2', ua: str_repeat('b', 300));

        $this->assertCount(2, $this->mails());
        foreach ([$this->mailText($this->mail(0)), $this->telegramText(0)] as $text) {
            $this->assertStringContainsString('User-Agent: '.str_repeat('a', 200), $text, 'Genau 200 Zeichen bleiben unverändert.');
            $this->assertStringNotContainsString('…', $text);
        }
        foreach ([$this->mailText($this->mail(1)), $this->telegramText(1)] as $text) {
            $this->assertSame(1, preg_match('/User-Agent: (b+…)/u', $text, $treffer), 'Gekürzter User-Agent mit „…“ fehlt.');
            $this->assertSame(200, mb_strlen($treffer[1]));
        }
    }

    // AK12
    public function test_fehlender_user_agent_steht_als_unbekannt_da(): void
    {
        $this->anfrage('/ohne-ua-1', ua: 'Bekannt/1.0');
        $this->anfrage('/ohne-ua-2', ua: '');

        $this->assertCount(2, $this->mails());
        $this->assertStringContainsString('User-Agent: Bekannt/1.0', $this->mailText($this->mail(0)));
        foreach ([$this->mailText($this->mail(1)), $this->telegramText(1)] as $text) {
            $this->assertStringContainsString('User-Agent: unbekannt', $text);
        }
    }

    // AK12
    public function test_fehlende_ip_steht_als_unbekannt_da(): void
    {
        $this->anfrage('/ohne-ip-1', ip: self::IP);
        $this->anfrage('/ohne-ip-2', ip: '');

        $this->assertCount(2, $this->mails());
        $this->assertStringContainsString('IP: '.self::IP, $this->mailText($this->mail(0)));
        foreach ([$this->mailText($this->mail(1)), $this->telegramText(1)] as $text) {
            $this->assertStringContainsString('IP: unbekannt', $text);
        }
    }

    // AK12
    public function test_ip_und_user_agent_werden_in_der_mail_escaped(): void
    {
        $this->anfrage('/leistungen/alt', ua: '<script>alert(1)</script>');

        $this->assertEineMeldung();
        $html = $this->mailHtml();
        $this->assertStringContainsString('User-Agent: &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('User-Agent: <script>alert(1)</script>', $this->mailText($this->mail()), 'Als Text sichtbar.');
    }

    // AK12
    public function test_ip_wird_in_der_mail_escaped(): void
    {
        $this->anfrage('/leistungen/alt', ip: '<b>x</b>');

        $this->assertEineMeldung();
        $html = $this->mailHtml();
        $this->assertStringContainsString('IP: &lt;b&gt;x&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
    }

    // AK13
    public function test_ip_kuerzen_ist_standardmaessig_aus(): void
    {
        $this->assertFalse($this->konfig()['erfassung']['ip_kuerzen'] ?? null);
        $this->assertTrue($this->konfig(['ALERTS_IP_KUERZEN' => 'true'])['erfassung']['ip_kuerzen'] ?? null);
    }

    /** @return array<string, array{mixed, bool}> */
    public static function kuerzenSchalter(): array
    {
        return [
            'true' => [true, true], 'string true' => ['true', true], 'eins' => ['1', true], 'on' => ['on', true], 'yes' => ['yes', true],
            'false' => [false, false], 'string false' => ['false', false], 'null' => ['0', false], 'off' => ['off', false], 'no' => ['no', false], 'leer' => ['', false],
        ];
    }

    #[DataProvider('kuerzenSchalter')]
    // AK13
    public function test_ip_kuerzen_wird_wie_die_uebrigen_bool_schalter_gewertet(mixed $wert, bool $gekuerzt): void
    {
        config(['alerts.erfassung.ip_kuerzen' => $wert]);

        $this->anfrage('/leistungen/alt');

        $this->assertEineMeldung();
        $text = $this->mailText($this->mail());
        $this->assertStringContainsString($gekuerzt ? 'IP: 203.0.113.0' : 'IP: '.self::IP, $text);
        $this->assertSame($gekuerzt, ! str_contains($text, self::IP));
    }

    // AK13
    public function test_ipv4_wird_in_allen_meldungen_auf_den_letzten_block_null_gekuerzt(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->anfrage('/leistungen/alt');

        foreach ([$this->mailText($this->mail()), $this->telegramText()] as $text) {
            $this->assertStringContainsString('IP: 203.0.113.0', $text);
            $this->assertStringNotContainsString(self::IP, $text);
        }
    }

    // AK13
    public function test_ipv6_wird_auf_die_ersten_64_bit_gekuerzt(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->anfrage('/leistungen/alt', '2001:db8:1234:5678:9abc::1');

        foreach ([$this->mailText($this->mail()), $this->telegramText()] as $text) {
            $this->assertStringContainsString('IP: 2001:db8:1234:5678::', $text);
            $this->assertStringNotContainsString('9abc', $text);
        }
    }

    // AK13
    public function test_gekuerzte_ip_steht_auch_in_der_sammelmeldung(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->serie(6, 'a');

        foreach ([$this->haeufungMail(), $this->haeufungenTelegram()[0]] as $text) {
            $this->assertStringContainsString('IP: 203.0.113.0', $text);
            $this->assertStringNotContainsString(self::IP, $text);
        }
    }

    // AK13
    public function test_gekuerzte_ip_steht_auch_in_der_403_meldung(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->actingAs($this->nutzer())
            ->withServerVariables(['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => 'Test/1.0'])
            ->get('/verboten')->assertForbidden();

        foreach ([$this->mailText($this->mail()), $this->telegramText()] as $text) {
            $this->assertStringContainsString('IP: 203.0.113.0', $text);
            $this->assertStringNotContainsString(self::IP, $text);
        }
    }

    // AK13
    public function test_zaehlung_und_fingerabdruck_bleiben_je_voller_ip_auch_beim_kuerzen(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->serie(5, 'a', '203.0.113.57');
        $this->serie(5, 'b', '203.0.113.58');
        $this->assertCount(10, $this->mails(), 'Zwei IPs im selben /24 zählen getrennt.');
        $this->assertCount(0, $this->haeufungen());

        $this->anfrage('/a-6', '203.0.113.57');
        $this->anfrage('/b-6', '203.0.113.58');

        $this->assertCount(2, $this->haeufungen());
        $this->assertNotSame($this->fingerabdruckAus($this->haeufungMail(0)), $this->fingerabdruckAus($this->haeufungMail(1)));
    }

    // AK14
    public function test_ip_steht_in_keinem_cache_schluessel_und_keinem_cache_wert_im_klartext(): void
    {
        $this->serie(6, 'seite');
        $this->assertCount(1, $this->haeufungen(), 'Aufbau: die Zählung lief bis zur Sammelmeldung.');

        $store = $this->app['cache']->store('array')->getStore();
        $this->assertNotSame([], $store->all(false));
        foreach ($store->all(false) as $schluessel => $eintrag) {
            foreach ([self::IP, '203-0-113-57', '203_0_113_57'] as $klartext) {
                $this->assertStringNotContainsString($klartext, (string) $schluessel);
                $this->assertStringNotContainsString($klartext, serialize($eintrag['value']));
            }
        }
    }

    // AK14
    public function test_eintraege_der_fensterzaehlung_laufen_spaetestens_mit_dem_fenster_ab(): void
    {
        $this->serie(3, 'seite');

        $store = $this->app['cache']->store('array')->getStore();
        $zaehlung = array_filter(
            $store->all(false),
            static fn (string $schluessel): bool => preg_match('/^alerts\.(sperre|zaehler|index)/', $schluessel) !== 1,
            ARRAY_FILTER_USE_KEY,
        );

        $this->assertNotSame([], $zaehlung, 'Aufbau: die Fensterzählung legt Einträge an.');
        foreach ($zaehlung as $schluessel => $eintrag) {
            $this->assertLessThanOrEqual(
                time() + 60,
                (int) $eintrag['expiresAt'],
                "Eintrag {$schluessel} läuft nach dem Fenster ab.",
            );
        }
    }

    // AK14
    public function test_keine_datenbank_und_keine_datei(): void
    {
        $abfragen = 0;
        DB::listen(function () use (&$abfragen): void {
            $abfragen++;
        });
        $vorher = $this->dateien();

        $this->serie(6, 'seite');

        $this->assertCount(1, $this->haeufungen(), 'Aufbau: die Zählung lief bis zur Sammelmeldung.');
        $this->assertSame(0, $abfragen);
        $this->assertSame($vorher, $this->dateien());
    }

    /** @return list<string> */
    private function dateien(): array
    {
        $dateien = [];
        foreach ([storage_path('framework'), storage_path('app'), sys_get_temp_dir().'/alerts-nicht-vorhanden'] as $ordner) {
            if (! is_dir($ordner)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($ordner, \FilesystemIterator::SKIP_DOTS)) as $datei) {
                $dateien[] = $datei->getPathname().'|'.$datei->getSize();
            }
        }
        sort($dateien);

        return $dateien;
    }

    // ── Übergreifend ─────────────────────────────────────────────────────────

    // AK15
    public function test_einzelner_kaputter_link_wird_genau_einmal_mit_unveraendertem_fingerabdruck_gemeldet(): void
    {
        $this->anfrage('/leistungen/alt');

        $this->assertEineMeldung();
        $erwartet = '404.leistungen-alt.'.substr(hash('sha256', '/leistungen/alt'), 0, 12);
        $this->assertSame($erwartet, $this->fingerabdruckAus($this->mailText($this->mail())));
        $this->assertSame($erwartet, $this->fingerabdruckAus($this->telegramText()));
        $this->assertCount(0, $this->haeufungen());
    }

    // AK15
    public function test_einzelner_kaputter_link_enthaelt_ip_und_user_agent(): void
    {
        $this->anfrage('/leistungen/alt', self::IP, 'Mozilla/5.0 Testbrowser');

        $this->assertEineMeldung();
        $this->assertStringContainsString('IP: '.self::IP, $this->mailText($this->mail()));
        $this->assertStringContainsString('User-Agent: Mozilla/5.0 Testbrowser', $this->mailText($this->mail()));
    }

    private function kaputtenCacheEinrichten(): void
    {
        $this->app['cache']->extend('kaputt', static fn (): Repository => new Repository(new KaputterCacheStore));
        config(['cache.stores.kaputt' => ['driver' => 'kaputt'], 'alerts.drosselung.store' => 'kaputt']);
    }

    // AK16
    public function test_bei_cache_ausfall_wird_jeder_404_einzeln_gemeldet(): void
    {
        $this->kaputtenCacheEinrichten();

        $this->serie(10, 'seite');

        $this->assertCount(10, $this->mails());
        $this->assertCount(10, $this->telegramAnfragen());
        $this->assertCount(0, $this->haeufungen());
        $this->assertStringContainsString('IP: '.self::IP, $this->mailText($this->mail(9)), 'Die Einzelmeldung trägt auch ohne Cache IP und User-Agent.');
    }

    // AK16
    public function test_bei_cache_ausfall_geht_eine_warnung_ins_log(): void
    {
        $this->kaputtenCacheEinrichten();

        $this->serie(6, 'seite');

        $this->assertNotSame([], $this->logsMitLevel('warning'));
        $this->assertStringContainsString('RuntimeException', $this->logText());
    }

    // AK16
    public function test_bei_cache_ausfall_erreicht_keine_exception_die_anfrage(): void
    {
        $this->kaputtenCacheEinrichten();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => 'Test/1.0']);
        for ($i = 1; $i <= 7; $i++) {
            $this->get("/seite-{$i}", ['Referer' => 'http://localhost/start'])->assertNotFound();
        }

        $this->assertCount(7, $this->mails());
        $this->assertStringContainsString('IP: '.self::IP, $this->mailText($this->mail(6)));
    }

    // ── Randfälle ────────────────────────────────────────────────────────────

    // Randfall 1
    public function test_prozentkodierter_punkt_gilt_als_env_pfad(): void
    {
        $this->anfrage('/%2Eenv', weiter: false);
        $this->anfrage('/%2eenv', weiter: false);
        $this->assertKeineMeldung();

        config(['alerts.erfassung.http_404_scanner' => []]);
        $this->anfrage('/%2Eenv');
        $this->assertEineMeldung();
    }

    // Randfall 2
    public function test_403_hat_kein_sammeln_und_keine_ignorierlisten(): void
    {
        config(['alerts.erfassung.http_404_ignorieren' => ['verboten/*']]);
        foreach (range(1, 8) as $i) {
            Route::middleware('web')->get("/verboten/{$i}", static fn () => abort(403));
        }
        Route::middleware('web')->get('/config.env', static fn () => abort(403));

        $this->actingAs($this->nutzer())->withServerVariables(['REMOTE_ADDR' => self::IP, 'HTTP_USER_AGENT' => 'Test/1.0']);
        foreach (range(1, 8) as $i) {
            $this->get("/verboten/{$i}")->assertForbidden();
            $this->travel(1)->seconds();
        }
        $this->get('/config.env')->assertForbidden();

        $this->assertCount(9, $this->mails());
        $this->assertCount(9, $this->telegramAnfragen());
        $this->assertCount(0, $this->haeufungen());
        $this->assertStringContainsString('IP: '.self::IP, $this->mailText($this->mail(8)));
    }

    // Randfall 3
    public function test_derselbe_pfad_mehrfach_von_einer_ip_zaehlt_jedes_mal(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->anfrage('/immer-derselbe');
        }

        $this->assertCount(2, $this->mails(), 'Einzelmeldung einmal (gedrosselt), dann die Sammelmeldung.');
        $this->assertCount(1, $this->haeufungen());
        $this->assertStringContainsString('Anzahl: 6 × 404 innerhalb von 60 s', $this->haeufungMail());
    }

    // Randfall 4
    public function test_bei_alerts_enabled_aus_wird_nicht_gezaehlt(): void
    {
        config(['alerts.enabled' => false]);
        $this->serie(10, 'aus');
        $this->assertKeineMeldung();

        config(['alerts.enabled' => true]);
        $this->serie(5, 'an');
        $this->assertCount(5, $this->mails());
        $this->assertCount(0, $this->haeufungen(), 'Während „aus“ darf nichts gezählt worden sein.');

        $this->anfrage('/an-6');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: eingeschaltet wird gezählt.');
    }

    // Randfall 4
    public function test_bei_http_404_aus_wird_nicht_gezaehlt(): void
    {
        config(['alerts.erfassung.http_404' => false]);
        $this->serie(10, 'aus');
        $this->assertKeineMeldung();

        config(['alerts.erfassung.http_404' => true]);
        $this->serie(5, 'an');
        $this->assertCount(5, $this->mails());
        $this->assertCount(0, $this->haeufungen(), 'Während „aus“ darf nichts gezählt worden sein.');

        $this->anfrage('/an-6');
        $this->assertCount(1, $this->haeufungen(), 'Gegenprobe: eingeschaltet wird gezählt.');
    }

    // Randfall 5
    public function test_nicht_kuerzbare_ip_steht_beim_kuerzen_als_unbekannt_da(): void
    {
        config(['alerts.erfassung.ip_kuerzen' => true]);

        $this->anfrage('/ungueltig-1', '198.51.100.9');
        $this->anfrage('/ungueltig-2', 'keine-ip');

        $this->assertCount(2, $this->mails());
        $this->assertStringContainsString('IP: 198.51.100.0', $this->mailText($this->mail(0)), 'Gegenprobe: gültige IP wird gekürzt.');
        foreach ([$this->mailText($this->mail(1)), $this->telegramText(1)] as $text) {
            $this->assertStringContainsString('IP: unbekannt', $text);
            $this->assertStringNotContainsString('IP: keine-ip', $text);
        }
    }

    // Randfall 6
    public function test_gewoehnliche_seitenpfade_werden_weiter_gemeldet(): void
    {
        foreach (['/wp-tipps', '/kontakt', '/umwelt-graphik'] as $pfad) {
            $this->anfrage($pfad);
        }

        $this->assertCount(3, $this->mails());
        $this->assertCount(3, $this->telegramAnfragen());
        foreach (['/wp-tipps', '/kontakt', '/umwelt-graphik'] as $i => $pfad) {
            $this->assertStringContainsString($pfad, $this->mailText($this->mail($i)));
        }
    }

    // ── Sitemap-Scanner (#946) ───────────────────────────────────────────────

    // AK18
    public function test_sitemap_scanner_pfade_aus_dem_paket_standard_werden_nicht_gemeldet(): void
    {
        foreach (self::SITEMAP_SCANNER_PFADE as $pfad) {
            $this->anfrage($pfad, weiter: false);
            $this->assertKeineMeldung();
        }

        // Gegenprobe: die echte Sitemap und ein normaler Pfad werden gemeldet.
        $this->anfrage('/sitemap.xml');
        $this->anfrage('/sitemap-seite');

        $this->assertCount(2, $this->mails());
        $this->assertCount(2, $this->telegramAnfragen());
        $this->assertStringContainsString('/sitemap.xml', $this->mailText($this->mail(0)));
        $this->assertStringContainsString('/sitemap-seite', $this->mailText($this->mail(1)));
    }

    // AK18
    public function test_sitemap_scanner_muster_stehen_in_der_scanner_liste(): void
    {
        $this->anfrage('/wp-sitemap.xml');
        $this->assertKeineMeldung();

        // Quelle ändern: ohne Scanner-Liste wird derselbe Pfad gemeldet.
        config(['alerts.erfassung.http_404_scanner' => []]);
        $this->anfrage('/wp-sitemap.xml');
        $this->assertEineMeldung();
    }
}
