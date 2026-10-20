<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\UrlGenerator;
use ITHilbert\Alerts\Application\FreigabeLinks;
use ITHilbert\Alerts\Domain\Fingerabdruck;

final class SignierteFreigabeLinks implements FreigabeLinks
{
    public function __construct(
        private readonly UrlGenerator $url,
        private readonly Repository $config,
    ) {}

    public function erzeuge(Fingerabdruck $fingerabdruck): string
    {
        $tage = max(1, (int) $this->config->get('alerts.freigabe.link_gueltig_tage', 7));

        return $this->url->temporarySignedRoute(
            'alerts.freigabe',
            CarbonImmutable::now()->addDays($tage),
            ['fingerabdruck' => $fingerabdruck->wert],
        );
    }
}
