<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use ITHilbert\Alerts\Domain\Alert;
use ITHilbert\Alerts\Domain\Vorfall;
use Psr\Log\LoggerInterface;

final class MeldeAlert
{
    private const MAX_FEHLERTEXT = 2000;

    private bool $laeuft = false;

    public function __construct(
        private readonly Repository $config,
        private readonly Drosselung $drosselung,
        private readonly AlertVersand $versand,
        private readonly FreigabeLinks $links,
        private readonly LoggerInterface $logger,
    ) {}

    public function ausfuehren(Vorfall $vorfall): void
    {
        if (! filter_var($this->config->get('alerts.enabled', true), FILTER_VALIDATE_BOOL) || $this->laeuft) {
            return;
        }

        $this->laeuft = true;

        try {
            $wiederholungen = $this->drosselung->betrete($vorfall->fingerabdruck);

            if ($wiederholungen !== null) {
                $this->versand->sende($this->baue($vorfall, $wiederholungen));
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Alerts: Meldung konnte nicht verarbeitet werden ('.$e::class.').');
        } finally {
            $this->laeuft = false;
        }
    }

    private function baue(Vorfall $vorfall, int $wiederholungen): Alert
    {
        if ($vorfall->fehlertext !== null && mb_strlen($vorfall->fehlertext) > self::MAX_FEHLERTEXT) {
            $vorfall = new Vorfall(
                $vorfall->fingerabdruck,
                $vorfall->quelle,
                $vorfall->titel,
                mb_substr($vorfall->fehlertext, 0, self::MAX_FEHLERTEXT).'…',
                $vorfall->ort,
                $vorfall->url,
                $vorfall->details,
            );
        }

        return new Alert(
            vorfall: $vorfall,
            projekt: (string) $this->config->get('app.name'),
            umgebung: (string) $this->config->get('app.env'),
            wiederholungen: $wiederholungen,
            zeitpunkt: CarbonImmutable::now(),
            freigabeLink: $this->links->erzeuge($vorfall->fingerabdruck),
        );
    }
}
