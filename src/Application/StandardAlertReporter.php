<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\AlertQuelle;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Domain\Meldung;
use ITHilbert\Alerts\Domain\Vorfall;

final class StandardAlertReporter implements AlertReporter
{
    public function __construct(
        private readonly MeldeAlert $meldeAlert,
        private readonly AktuelleAnfrage $anfrage,
    ) {}

    public function melde(Meldung $meldung): void
    {
        $this->meldeAlert->ausfuehren(new Vorfall(
            fingerabdruck: new Fingerabdruck($meldung->schluessel),
            quelle: AlertQuelle::Meldung,
            titel: $meldung->titel,
            fehlertext: $meldung->fehlertext,
            url: $this->anfrage->url(),
            details: $meldung->details,
        ));
    }
}
