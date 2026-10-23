<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use DOMDocument;
use DOMXPath;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Tests\TestCase;

/** mail-links: AK1 (URL), AK2 (Referer), AK3 (Freigabe-Adresse), AK4 (nur http(s)) und Randfälle. */
final class MailLinksTest extends TestCase
{
    /** @param array<string, scalar|null> $details */
    private function meldeMitDetails(array $details): void
    {
        $this->app->make(AlertReporter::class)->melde(new Meldung(
            schluessel: 'test.maillinks',
            titel: 'Linktest',
            details: $details,
        ));
    }

    private function html(): string
    {
        $html = $this->mail()->getOriginalMessage()->getHtmlBody();

        return (string) $html;
    }

    /** @return list<array{href: string, text: string}> */
    private function links(): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$this->html());
        $ergebnis = [];

        foreach ((new DOMXPath($dom))->query('//a') as $a) {
            $ergebnis[] = ['href' => $a->getAttribute('href'), 'text' => trim($a->textContent)];
        }

        return $ergebnis;
    }

    /** @return list<array{href: string, text: string}> */
    private function linksMitHref(string $href): array
    {
        return array_values(array_filter($this->links(), static fn (array $l): bool => $l['href'] === $href));
    }

    private function assertLink(string $href, string $meldung = ''): void
    {
        $this->assertContains(
            ['href' => $href, 'text' => $href],
            $this->links(),
            $meldung ?: "Erwartet: <a href=\"{$href}\"> mit dem Linktext {$href}.",
        );
    }

    // AK1
    public function test_url_der_meldung_ist_ein_link_mit_der_url_als_text(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/start'])->assertNotFound();

        $this->assertLink('http://localhost/gibt-es-nicht');
    }

    // AK2
    public function test_referer_mit_https_ist_ein_link_auf_genau_diesen_wert(): void
    {
        $this->meldeMitDetails(['Referer' => 'https://example.com/seite?a=1']);

        $this->assertLink('https://example.com/seite?a=1');
    }

    // AK2
    public function test_referer_mit_http_ist_ein_link_auf_genau_diesen_wert(): void
    {
        $this->meldeMitDetails(['Referer' => 'http://example.com/start']);

        $this->assertLink('http://example.com/start');
    }

    // AK2
    public function test_interner_referer_eines_404_ist_ein_link(): void
    {
        $this->get('/gibt-es-nicht', ['Referer' => 'http://localhost/start'])->assertNotFound();

        $this->assertLink('http://localhost/start');
    }

    // AK3
    public function test_freigabe_adresse_ist_ein_link_auf_dieselbe_adresse_wie_meldung_freigeben(): void
    {
        $this->meldeMitDetails([]);

        $adresse = $this->freigabeLinkAus($this->html());
        $gleich = $this->linksMitHref($adresse);

        $this->assertCount(2, $gleich, 'Zwei Links auf die Freigabe-Adresse erwartet.');
        $this->assertContains('Meldung freigeben', array_column($gleich, 'text'));
        $this->assertContains($adresse, array_column($gleich, 'text'), 'Die ausgeschriebene Adresse muss selbst Link sein.');
    }

    // AK3
    public function test_meldung_freigeben_bleibt_ein_link(): void
    {
        $this->meldeMitDetails([]);

        $this->assertContains('Meldung freigeben', array_column($this->links(), 'text'));
    }

    // AK4
    public function test_referer_mit_javascript_ist_kein_link_aber_text(): void
    {
        $this->meldeMitDetails(['Referer' => 'javascript:alert(1)']);

        $this->assertSame([], $this->linksMitHref('javascript:alert(1)'));
        $this->assertStringContainsString('javascript:alert(1)', $this->mailText($this->mail()));
    }

    // AK4
    public function test_mail_enthaelt_nie_einen_link_ohne_http_oder_https(): void
    {
        $this->meldeMitDetails(['Referer' => 'javascript:alert(1)']);

        $this->assertNotEmpty($this->links(), 'Vorbedingung: die Mail enthält Links.');
        foreach ($this->links() as $link) {
            $this->assertMatchesRegularExpression('#^https?://#', $link['href']);
        }
    }

    // AK4
    public function test_referer_mit_data_schema_wird_nicht_verlinkt(): void
    {
        $this->meldeMitDetails(['Referer' => 'data:text/html,<b>x</b>']);

        $this->assertSame([], array_values(array_filter(
            $this->links(),
            static fn (array $l): bool => str_starts_with($l['href'], 'data:'),
        )));
    }

    // AK2, Randfall Sonderzeichen
    public function test_referer_mit_kaufmanns_und_und_anfuehrungszeichen_bleibt_exakt_im_href(): void
    {
        $referer = 'https://example.com/p?a=1&b="x"&c=2';
        $this->meldeMitDetails(['Referer' => $referer]);

        $this->assertLink($referer);
        $this->assertStringNotContainsString('b="x"', $this->html(), 'Rohes Anführungszeichen würde das Attribut brechen.');
    }

    // AK2, Randfall Sonderzeichen
    public function test_referer_mit_spitzer_klammer_wird_im_link_escaped(): void
    {
        $referer = 'https://example.com/p?x=<b>';
        $this->meldeMitDetails(['Referer' => $referer]);

        $this->assertLink($referer);
        $this->assertStringNotContainsString('<b>', $this->html());
    }

    // Randfall: andere Details bleiben Text
    public function test_anderes_detail_mit_adresse_als_wert_bleibt_text(): void
    {
        $this->meldeMitDetails(['Herkunft' => 'https://example.com/herkunft']);

        $this->assertSame([], $this->linksMitHref('https://example.com/herkunft'));
        $this->assertStringContainsString('Herkunft: https://example.com/herkunft', $this->mailText($this->mail()));
    }
}
