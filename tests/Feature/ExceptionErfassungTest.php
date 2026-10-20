<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Validation\ValidationException;
use ITHilbert\Alerts\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** AK2 (unbehandelte Exceptions), AK3 (nicht meldepflichtige), Inhalt AK14. */
final class ExceptionErfassungTest extends TestCase
{
    public function test_gemeldete_exception_loest_meldung_aus(): void
    {
        $this->meldeException($this->ausnahme('Datenbank nicht erreichbar'));

        $this->assertEineMeldung();

        $mail = $this->mailText($this->mail());
        $this->assertSame('[Testprojekt] RuntimeException', $this->betreff($this->mail()));
        $this->assertStringContainsString('Quelle: Exception', $mail);
        $this->assertStringContainsString('TestCase.php:', $mail, 'Ort (Datei:Zeile) fehlt.');
        $this->assertStringContainsString('Datenbank nicht erreichbar', $mail, 'Mail enthält standardmäßig den Fehlertext.');
        $this->fingerabdruckAus($mail);
    }

    public function test_titel_enthaelt_nie_die_exception_meldung(): void
    {
        $this->meldeException($this->ausnahme('kunde@example.com nicht gefunden'));

        $this->assertStringNotContainsString('kunde@example.com', $this->betreff($this->mail()));
    }

    public function test_gleicher_ort_mit_anderer_meldung_ergibt_denselben_fingerabdruck(): void
    {
        $this->meldeException($this->ausnahme('ID 17 fehlt'));
        $this->meldeException($this->ausnahme('ID 18 fehlt'));

        $this->assertEineMeldung();
    }

    public function test_anderer_ort_ergibt_eigene_meldung(): void
    {
        $this->meldeException($this->ausnahme());
        $this->meldeException($this->andereAusnahme());

        $this->assertCount(2, $this->mails());
        $this->assertNotSame(
            $this->fingerabdruckAus($this->mailText($this->mail(0))),
            $this->fingerabdruckAus($this->mailText($this->mail(1))),
        );
    }

    public function test_nicht_meldepflichtige_exceptions_werden_ignoriert(): void
    {
        $this->meldeException(ValidationException::withMessages(['feld' => 'falsch']));
        $this->meldeException(new NotFoundHttpException);
        $this->meldeException(new HttpException(500, 'abort(500)'));

        $this->assertKeineMeldung();
    }

    public function test_exception_aus_einer_anfrage_nennt_url_ohne_query_string(): void
    {
        $this->get('/kaputt?token=streng-geheim')->assertStatus(500);

        $this->assertEineMeldung();
        $mail = $this->mailText($this->mail());
        $this->assertStringContainsString('http://localhost/kaputt', $mail);
        $this->assertStringNotContainsString('streng-geheim', $mail);
        $this->assertStringNotContainsString('streng-geheim', $this->telegramNutzlast());
    }

    public function test_exception_ausserhalb_einer_anfrage_nennt_keine_url(): void
    {
        // Wie Queue-Worker oder Cron: Laravel bindet dann einen Platzhalter-Request aus app.url.
        $this->meldeException($this->ausnahme());

        $this->assertDoesNotMatchRegularExpression('/URL:/', $this->mailText($this->mail()));
        $this->assertDoesNotMatchRegularExpression('/URL:/', $this->telegramText());
    }

    public function test_erfassung_abgeschaltet(): void
    {
        config(['alerts.erfassung.exceptions' => false]);

        $this->meldeException($this->ausnahme());

        $this->assertKeineMeldung();
    }

    public function test_package_abgeschaltet(): void
    {
        config(['alerts.enabled' => false]);

        $this->meldeException($this->ausnahme());

        $this->assertKeineMeldung();
    }
}
