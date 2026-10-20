<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use ITHilbert\Alerts\Tests\TestCase;

/** AK12 (signierter Freigabe-Link mit Bestätigungsseite). */
final class FreigabeLinkTest extends TestCase
{
    private function link(): string
    {
        $this->meldeException($this->ausnahme());

        return $this->freigabeLinkAus($this->mailRoh($this->mail()));
    }

    public function test_link_steht_in_mail_und_telegram(): void
    {
        $link = $this->link();

        $this->assertStringContainsString('signature=', $link);
        $this->assertStringContainsString('expires=', $link, 'Link muss zeitlich begrenzt sein.');
        $this->freigabeLinkAus($this->telegramNutzlast());
    }

    public function test_aufruf_zeigt_bestaetigungsseite_und_gibt_nicht_frei(): void
    {
        $link = $this->link();

        $antwort = $this->get($link)->assertOk();
        $this->assertMatchesRegularExpression('/<form[^>]+method="post"/i', $antwort->getContent());
        $this->assertStringContainsString('_token', $antwort->getContent(), 'Formular braucht CSRF-Token.');

        $this->meldeException($this->ausnahme());
        $this->assertCount(1, $this->mails(), 'GET darf nicht freigeben (Mail-Scanner).');
    }

    public function test_bestaetigung_per_post_gibt_frei(): void
    {
        $link = $this->link();

        $this->post($link)->assertOk()->assertSee('freigegeben');

        $this->meldeException($this->ausnahme());
        $this->assertCount(2, $this->mails());
    }

    public function test_manipulierte_signatur_wird_abgelehnt(): void
    {
        $link = preg_replace('/signature=[a-f0-9]+/', 'signature='.str_repeat('0', 64), $this->link());

        $this->get($link)->assertForbidden();
        $this->post($link)->assertForbidden();
    }

    public function test_abgelaufener_link_wird_abgelehnt(): void
    {
        $link = $this->link();

        $this->travel(8)->days();

        $this->post($link)->assertForbidden();
    }

    public function test_freigabe_route_ist_gedrosselt(): void
    {
        $link = $this->link();

        for ($i = 0; $i < 6; $i++) {
            $this->get($link)->assertOk();
        }

        $this->get($link)->assertStatus(429);
    }

    public function test_bestaetigungsseite_laedt_keine_externen_assets(): void
    {
        $inhalt = $this->get($this->link())->getContent();

        $this->assertDoesNotMatchRegularExpression('/<(script|link|img)[^>]+(src|href)="(https?:)?\/\//i', $inhalt);
    }
}
