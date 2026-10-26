<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Erfassung;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ITHilbert\Alerts\Application\MeldeAlert;
use ITHilbert\Alerts\Domain\AlertQuelle;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Domain\Vorfall;
use Psr\Log\LoggerInterface;

final class StatusErfasser
{
    public function __construct(
        private readonly MeldeAlert $meldeAlert,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function behandle(RequestHandled $ereignis): void
    {
        if (! $this->config->get('alerts.enabled')) {
            return;
        }

        $status = $ereignis->response->getStatusCode();

        if ($status === 404 && $this->config->get('alerts.erfassung.http_404')) {
            $this->nichtGefunden($ereignis->request);
        } elseif ($status === 403 && $this->config->get('alerts.erfassung.http_403') && $this->angemeldet($ereignis->request)) {
            $this->melde($ereignis->request, AlertQuelle::Verboten, 'Zugriff verweigert (403)');
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

        $muster = array_values(array_filter(
            (array) $this->config->get('alerts.erfassung.http_404_ignorieren', []),
            static fn (mixed $eintrag): bool => is_string($eintrag) && $eintrag !== '',
        ));

        if ($muster !== [] && Str::is($muster, $request->decodedPath(), true)) {
            return;
        }

        $this->melde($request, AlertQuelle::NichtGefunden, 'Seite nicht gefunden (404)', ['Referer' => $referer]);
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
