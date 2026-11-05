<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Erfassung;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ITHilbert\Alerts\Application\Drosselung;
use ITHilbert\Alerts\Application\MeldeAlert;
use ITHilbert\Alerts\Application\Schalter;
use ITHilbert\Alerts\Domain\AlertQuelle;
use ITHilbert\Alerts\Domain\Fensterstand;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Domain\Vorfall;
use Psr\Log\LoggerInterface;

final class StatusErfasser
{
    private const SAMMELN_AB = 5;

    private const SAMMELN_FENSTER_SEKUNDEN = 60;

    /** Höchstzahl der Pfade in einer Sammelmeldung. */
    private const SAMMELN_PFADE = 10;

    public function __construct(
        private readonly MeldeAlert $meldeAlert,
        private readonly Drosselung $drosselung,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function behandle(RequestHandled $ereignis): void
    {
        if (! Schalter::an($this->config, 'alerts.enabled', true)) {
            return;
        }

        $status = $ereignis->response->getStatusCode();

        if ($status === 404 && Schalter::an($this->config, 'alerts.erfassung.http_404', true)) {
            $this->nichtGefunden($ereignis->request);
        } elseif ($status === 403 && Schalter::an($this->config, 'alerts.erfassung.http_403', true) && $this->angemeldet($ereignis->request)) {
            $this->melde($ereignis->request, AlertQuelle::Verboten, 'Zugriff verweigert (403)', $this->herkunft($ereignis->request)->details());
        }
    }

    private function nichtGefunden(Request $request): void
    {
        $referer = $this->bereinigteUrl((string) $request->headers->get('referer'));
        $appHost = parse_url((string) $this->config->get('app.url'), PHP_URL_HOST);

        if ($referer === null || ! is_string($appHost) || strcasecmp((string) parse_url($referer, PHP_URL_HOST), $appHost) !== 0) {
            return;
        }

        if ($this->normalisierterPfad((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH)) === $this->normalisierterPfad($request->getPathInfo())) {
            return; // Eine Seite, die selbst 404 liefert, kann nicht auf sich verlinkt haben.
        }

        if ($this->ignoriert($request, 'alerts.erfassung.http_404_ignorieren') || $this->ignoriert($request, 'alerts.erfassung.http_404_scanner')) {
            return;
        }

        $herkunft = $this->herkunft($request);
        $sammeln = $this->sammelEinstellung();

        if ($sammeln !== null && $herkunft->ip !== null) {
            $stand = $this->drosselung->zaehleImFenster($this->quellenKennung($herkunft->ip), $request->getPathInfo(), $sammeln['fenster'], self::SAMMELN_PFADE);

            if ($stand !== null && $stand->anzahl > $sammeln['ab']) {
                if ($stand->anzahl === $sammeln['ab'] + 1) {
                    $this->meldeHaeufung($herkunft, $stand, $sammeln['fenster']);
                }

                return; // Jeder weitere 404 der Quelle im Fenster ist in der Sammelmeldung enthalten.
            }
        }

        $this->melde($request, AlertQuelle::NichtGefunden, 'Seite nicht gefunden (404)', ['Referer' => $referer, ...$herkunft->details()]);
    }

    private function ignoriert(Request $request, string $schluessel): bool
    {
        $muster = array_values(array_filter(
            (array) $this->config->get($schluessel, []),
            static fn (mixed $eintrag): bool => is_string($eintrag) && $eintrag !== '',
        ));

        return $muster !== [] && Str::is($muster, $request->decodedPath(), true);
    }

    /**
     * Sammeln ist aus, sobald `ab` oder `fenster_sekunden` ≤ 0 oder nicht numerisch ist.
     *
     * @return array{ab: int, fenster: int}|null
     */
    private function sammelEinstellung(): ?array
    {
        $einstellung = $this->config->get('alerts.erfassung.http_404_sammeln', []);
        $einstellung = is_array($einstellung) ? $einstellung : [];

        $ab = $this->positiveZahl(array_key_exists('ab', $einstellung) ? $einstellung['ab'] : self::SAMMELN_AB);
        $fenster = $this->positiveZahl(array_key_exists('fenster_sekunden', $einstellung) ? $einstellung['fenster_sekunden'] : self::SAMMELN_FENSTER_SEKUNDEN);

        return $ab === null || $fenster === null ? null : ['ab' => $ab, 'fenster' => $fenster];
    }

    private function positiveZahl(mixed $wert): ?int
    {
        if (! is_numeric($wert) || (float) $wert <= 0) {
            return null;
        }

        return max(1, (int) round((float) $wert));
    }

    /** Pseudonym der Quelle: Fingerabdruck und Zählung je voller IP, ohne die IP im Klartext. */
    private function quellenKennung(string $ip): string
    {
        return hash_hmac('sha256', '404-quelle|'.$ip, (string) $this->config->get('app.key', ''));
    }

    private function meldeHaeufung(Herkunft $herkunft, Fensterstand $stand, int $fenster): void
    {
        $kennung = $this->quellenKennung((string) $herkunft->ip);

        $this->meldeAlert->ausfuehren(new Vorfall(
            fingerabdruck: Fingerabdruck::abgeleitet(AlertQuelle::NichtGefunden->value, 'haeufung', 'haeufung|'.$kennung),
            quelle: AlertQuelle::NichtGefunden,
            titel: 'Häufung von 404 (eine Quelle)',
            details: [
                'Anzahl' => $stand->anzahl.' × 404 innerhalb von '.$fenster.' s',
                'Pfade' => implode(', ', $stand->eintraege),
                ...$herkunft->details(),
            ],
        ));
    }

    private function herkunft(Request $request): Herkunft
    {
        return Herkunft::aus($request, Schalter::an($this->config, 'alerts.erfassung.ip_kuerzen', false));
    }

    private function normalisierterPfad(string $pfad): string
    {
        return rtrim(mb_strtolower(rawurldecode($pfad)), '/');
    }

    /** @param array<string, string> $details */
    private function melde(Request $request, AlertQuelle $quelle, string $titel, array $details = []): void
    {
        $pfad = $request->getPathInfo();

        $this->meldeAlert->ausfuehren(new Vorfall(
            fingerabdruck: Fingerabdruck::abgeleitet($quelle->value, $pfad, $pfad),
            quelle: $quelle,
            titel: $titel,
            url: $request->url(),
            details: $details,
        ));
    }

    private function angemeldet(Request $request): bool
    {
        try {
            return $request->user() !== null;
        } catch (\Throwable $e) {
            $this->logger->warning('Alerts: Anmeldestatus nicht prüfbar ('.$e::class.').');

            return false;
        }
    }

    private function bereinigteUrl(string $url): ?string
    {
        $teile = parse_url($url);

        if (! is_array($teile) || ! in_array($teile['scheme'] ?? '', ['http', 'https'], true) || ! isset($teile['host'])) {
            return null;
        }

        return mb_strimwidth(
            $teile['scheme'].'://'.$teile['host'].(isset($teile['port']) ? ':'.$teile['port'] : '').($teile['path'] ?? ''),
            0,
            200,
            '…',
        );
    }
}
