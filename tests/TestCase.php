<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests;

use Carbon\CarbonImmutable;
use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use ITHilbert\Alerts\AlertsServiceProvider;
use ITHilbert\Alerts\Tests\Fakes\KaputterTransport;
use ITHilbert\Alerts\Tests\Fakes\PlaudernderTransport;
use Orchestra\Testbench\TestCase as Testbench;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

/**
 * Basis aller Tests des Alert-Packages (Testbench = echte Laravel-App).
 *
 * VERTRAG für die Implementierung (Namen sind verbindlich):
 *
 *  Öffentlicher Port
 *   - ITHilbert\Alerts\Contracts\AlertReporter::melde(Meldung $meldung): void — per DI auflösbar.
 *   - ITHilbert\Alerts\Domain\Meldung (final readonly), benannte Parameter:
 *       schluessel: string, titel: string, fehlertext: ?string = null, details: array<string, scalar|null> = []
 *     Schlüssel muss /^[a-z0-9][a-z0-9._-]{0,99}$/ erfüllen, sonst
 *     ITHilbert\Alerts\Exceptions\UngueltigerSchluessel (extends \DomainException).
 *   - melde() wirft nie wegen eines Versandfehlers.
 *
 *  Fingerabdruck
 *   - Port: der Schlüssel selbst. Log: Kontext `alert_key`, sonst aus der Meldung abgeleitet.
 *   - Exception: aus Klasse + Datei + Zeile (NICHT aus der Meldung).
 *   - 404/403: aus Status + URL-Pfad.
 *   - Jeder Fingerabdruck erfüllt /^[a-z0-9._-]+$/.
 *
 *  Nachricht (Mail und Telegram), jeweils als eigene Zeile bzw. eigener Absatz:
 *   - „Projekt: <app.name>“, „Umgebung: <app.env>“, „Quelle: <Meldung|Exception|Log|404|403>“,
 *     „Fingerabdruck: <fp>“, Zeitpunkt (d.m.Y oder Y-m-d), URL ohne Query-String (falls Request).
 *   - Wiederholungen: „<n>× seit letzter Meldung“ (n = unterdrückte Auftreten seit der letzten Meldung),
 *     nur wenn n > 0.
 *   - Titel: Port = titel; Exception = Klassenname ohne Namespace (NIE die Exception-Meldung);
 *     Log = Log-Meldung; 404/403 frei.
 *   - Fehlertext (Exception-Meldung bzw. Meldung::fehlertext) nur, wenn für den Kanal
 *     `alerts.<kanal>.fehlertext` true ist. Ort „Datei:Zeile“ bei Exceptions immer.
 *   - Details der Meldung als „<Schlüssel>: <Wert>“.
 *   - Signierter Freigabe-Link (siehe Routen). Keine Nutzerdaten, kein Bot-Token.
 *   - Mail: Betreff „[<app.name>] <Titel>“. Views `alerts::mail.alert`, Telegram-Text aus `alerts::telegram.alert`.
 *   - Telegram: POST {api_url}/bot{token}/sendMessage über den Laravel-HTTP-Client, Felder chat_id, text;
 *     Link im Text oder als Inline-Button.
 *   - Versand synchron (nichts auf die Queue).
 *
 *  Freigabe
 *   - Command `alerts:reset {fingerabdruck?} {--all}`; unbekannter Fingerabdruck oder keine Angabe → Exit 1.
 *   - Routen gleicher URI „/{alerts.freigabe.prefix}/freigabe/{fingerabdruck}“, Middleware `signed`
 *     + `alerts.freigabe.middleware`: GET Name `alerts.freigabe` (Bestätigungsseite, gibt NICHT frei),
 *     POST Name `alerts.freigabe.ausfuehren` (gibt frei, Antwort enthält „freigegeben“).
 *     Der Link ist ein temporarySignedRoute auf `alerts.freigabe`; das Formular postet an dieselbe URL.
 *   - Bestätigungsseite: View `alerts::freigabe.bestaetigen`. Views publishbar über Tag `alerts-views`.
 *
 *  Config wird zur LAUFZEIT gelesen (Tests ändern sie nach dem Boot über config()).
 */
abstract class TestCase extends Testbench
{
    protected const TOKEN = '123456:GEHEIMER-BOT-TOKEN';

    protected const CHAT = '4711';

    /** @var Closure(Request): mixed */
    protected Closure $telegram;

    /** @var list<MessageLogged> */
    protected array $logs = [];

    protected function getPackageProviders($app): array
    {
        return [AlertsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Testprojekt');
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('mail.default', 'array');
        $app['config']->set('mail.from', ['address' => 'app@example.com', 'name' => 'App']);
        $app['config']->set('mail.mailers.array', ['transport' => 'array']);
        $app['config']->set('mail.mailers.zweit', ['transport' => 'array']);
        $app['config']->set('mail.mailers.kaputt', ['transport' => 'kaputt']);
        $app['config']->set('mail.mailers.plaudernd', ['transport' => 'plaudernd']);

        $app['config']->set('cache.default', 'array');

        $app['config']->set('alerts.mail.to', 'dev@example.com');
        $app['config']->set('alerts.telegram.bot_token', self::TOKEN);
        $app['config']->set('alerts.telegram.chat_id', self::CHAT);
    }

    /** @param Router $router */
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function (Router $router): void {
            $router->get('/seite', static fn (): string => 'ok');
            $router->get('/kaputt', static fn () => throw new RuntimeException('Boom aus der Route'));
            $router->get('/verboten', static fn () => abort(403));
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00:00'));

        $manager = $this->app['mail.manager'];
        $manager->extend('kaputt', static fn (): ArrayTransport => new KaputterTransport);
        $manager->extend('plaudernd', static fn (): ArrayTransport => new PlaudernderTransport);

        $this->telegram = static fn (): mixed => Http::response(['ok' => true, 'result' => []], 200);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): mixed => ($this->telegram)($request));

        $this->logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $eintrag): void {
            $this->logs[] = $eintrag;
        });
    }

    // ── Auslösen ─────────────────────────────────────────────────────────────

    protected function meldeException(\Throwable $e): void
    {
        $this->app->make(ExceptionHandler::class)->report($e);
    }

    /** Erzeugt die Exception immer an derselben Stelle (gleicher Ort = gleicher Fingerabdruck). */
    protected function ausnahme(string $meldung = 'Datenbank nicht erreichbar'): RuntimeException
    {
        return new RuntimeException($meldung);
    }

    /** Wie {@see ausnahme()}, aber an anderer Stelle. */
    protected function andereAusnahme(string $meldung = 'Datenbank nicht erreichbar'): RuntimeException
    {
        return new RuntimeException($meldung);
    }

    // ── Telegram ─────────────────────────────────────────────────────────────

    /** @return list<Request> */
    protected function telegramAnfragen(): array
    {
        return array_values(array_map(
            static fn (array $paar): Request => $paar[0],
            Http::recorded(static fn (Request $r): bool => str_contains($r->url(), 'api.telegram.org'))->all(),
        ));
    }

    protected function telegramText(int $index = 0): string
    {
        $anfrage = $this->telegramAnfragen()[$index] ?? null;
        $this->assertNotNull($anfrage, "Telegram-Nachricht #{$index} fehlt.");

        return (string) ($anfrage->data()['text'] ?? '');
    }

    /** Komplette Nutzlast einer Telegram-Anfrage als Text (für Link-Suche). */
    protected function telegramNutzlast(int $index = 0): string
    {
        $anfrage = $this->telegramAnfragen()[$index] ?? null;
        $this->assertNotNull($anfrage, "Telegram-Nachricht #{$index} fehlt.");

        return (string) json_encode($anfrage->data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function telegramStoert(int $status = 500): void
    {
        $this->telegram = static fn (): mixed => Http::response(['ok' => false, 'description' => 'Bad Gateway'], $status);
    }

    protected function telegramNichtErreichbar(): void
    {
        $this->telegram = static function (Request $request): never {
            throw new ConnectException(
                'cURL error 28: Operation timed out for '.$request->url(),
                new PsrRequest('POST', $request->url()),
            );
        };
    }

    // ── Mail ─────────────────────────────────────────────────────────────────

    /** @return list<SentMessage> */
    protected function mails(string $mailer = 'array'): array
    {
        $transport = $this->app['mail.manager']->mailer($mailer)->getSymfonyTransport();

        return $transport instanceof ArrayTransport ? array_values($transport->messages()->all()) : [];
    }

    protected function mail(int $index = 0, string $mailer = 'array'): SentMessage
    {
        $mail = $this->mails($mailer)[$index] ?? null;
        $this->assertNotNull($mail, "Mail #{$index} über Mailer {$mailer} fehlt.");

        return $mail;
    }

    /** Betreff + HTML + Text, HTML entfernt, Leerraum vereinheitlicht. */
    protected function mailText(SentMessage $mail): string
    {
        return $this->lesbar($this->mailRoh($mail));
    }

    protected function mailRoh(SentMessage $mail): string
    {
        $original = $mail->getOriginalMessage();

        if (! $original instanceof Email) {
            return $mail->toString();
        }

        return $original->getSubject()."\n".$original->getHtmlBody()."\n".$original->getTextBody();
    }

    protected function betreff(SentMessage $mail): string
    {
        $original = $mail->getOriginalMessage();

        return $original instanceof Email ? (string) $original->getSubject() : '';
    }

    /** @return list<string> */
    protected function empfaenger(SentMessage $mail): array
    {
        return array_map(static fn ($a): string => $a->getAddress(), $mail->getEnvelope()->getRecipients());
    }

    // ── Auswertung ───────────────────────────────────────────────────────────

    protected function lesbar(string $roh): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</tr>', '</li>'], "\n", $roh)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    }

    protected function fingerabdruckAus(string $text): string
    {
        $this->assertMatchesRegularExpression('/Fingerabdruck:\s*([a-z0-9._-]+)/', $text, 'Fingerabdruck fehlt in der Nachricht.');
        preg_match('/Fingerabdruck:\s*([a-z0-9._-]+)/', $text, $treffer);

        return $treffer[1];
    }

    protected function freigabeLinkAus(string $roh): string
    {
        $roh = html_entity_decode($roh, ENT_QUOTES | ENT_HTML5);
        $this->assertMatchesRegularExpression('#https?://[^\s"\'<>]+/alerts/freigabe/[^\s"\'<>]+#', $roh, 'Freigabe-Link fehlt.');
        preg_match('#https?://[^\s"\'<>]+/alerts/freigabe/[^\s"\'<>]+#', $roh, $treffer);

        return $treffer[0];
    }

    protected function logText(): string
    {
        return implode("\n", array_map(
            static fn (MessageLogged $e): string => $e->level.' '.$e->message.' '.json_encode($e->context, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            $this->logs,
        ));
    }

    /** @return list<MessageLogged> */
    protected function logsMitLevel(string $level): array
    {
        return array_values(array_filter($this->logs, static fn (MessageLogged $e): bool => $e->level === $level));
    }

    protected function assertKeineMeldung(): void
    {
        $this->assertCount(0, $this->mails(), 'Es wurde unerwartet eine Mail verschickt.');
        $this->assertCount(0, $this->telegramAnfragen(), 'Es wurde unerwartet eine Telegram-Nachricht verschickt.');
    }

    protected function assertEineMeldung(): void
    {
        $this->assertCount(1, $this->mails(), 'Erwartet: genau eine Mail.');
        $this->assertCount(1, $this->telegramAnfragen(), 'Erwartet: genau eine Telegram-Nachricht.');
    }
}
