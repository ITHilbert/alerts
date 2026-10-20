<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Erfassung;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Log\Events\MessageLogged;
use ITHilbert\Alerts\Application\AktuelleAnfrage;
use ITHilbert\Alerts\Application\MeldeAlert;
use ITHilbert\Alerts\Domain\AlertQuelle;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Domain\Vorfall;
use Psr\Log\LoggerInterface;

final class LogErfasser
{
    private const LEVEL = ['critical', 'alert', 'emergency'];

    public function __construct(
        private readonly MeldeAlert $meldeAlert,
        private readonly AktuelleAnfrage $anfrage,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function behandle(MessageLogged $eintrag): void
    {
        if (! in_array($eintrag->level, self::LEVEL, true)
            || ! $this->config->get('alerts.enabled')
            || ! $this->config->get('alerts.erfassung.log')) {
            return;
        }

        $nachricht = (string) $eintrag->message;

        $this->meldeAlert->ausfuehren(new Vorfall(
            fingerabdruck: $this->fingerabdruck($eintrag, $nachricht),
            quelle: AlertQuelle::Log,
            titel: mb_strimwidth($nachricht, 0, 200, '…'),
            url: $this->anfrage->url(),
        ));
    }

    private function fingerabdruck(MessageLogged $eintrag, string $nachricht): Fingerabdruck
    {
        $schluessel = $eintrag->context['alert_key'] ?? null;

        if (is_string($schluessel) && Fingerabdruck::istGueltig($schluessel)) {
            return new Fingerabdruck($schluessel);
        }

        if ($schluessel !== null) {
            $this->logger->warning('Alerts: Ungültiger alert_key im Log-Kontext, Fingerabdruck wird abgeleitet.');
        }

        $exception = $eintrag->context['exception'] ?? null;

        if ($exception instanceof \Throwable) {
            return Fingerabdruck::ausException($exception);
        }

        $normalisiert = (string) preg_replace('/\d+/', '#', $nachricht);

        return Fingerabdruck::abgeleitet('log', $normalisiert, $normalisiert);
    }
}
