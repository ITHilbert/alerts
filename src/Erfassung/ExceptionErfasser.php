<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Erfassung;

use Illuminate\Contracts\Config\Repository;
use ITHilbert\Alerts\Application\AktuelleAnfrage;
use ITHilbert\Alerts\Application\MeldeAlert;
use ITHilbert\Alerts\Domain\AlertQuelle;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use ITHilbert\Alerts\Domain\Vorfall;

final class ExceptionErfasser
{
    public function __construct(
        private readonly MeldeAlert $meldeAlert,
        private readonly AktuelleAnfrage $anfrage,
        private readonly Repository $config,
    ) {}

    public function erfasse(\Throwable $e): void
    {
        if (! $this->config->get('alerts.enabled') || ! $this->config->get('alerts.erfassung.exceptions')) {
            return;
        }

        $name = class_basename($e);
        $meldung = trim($e->getMessage());

        $this->meldeAlert->ausfuehren(new Vorfall(
            fingerabdruck: Fingerabdruck::ausException($e),
            quelle: AlertQuelle::Exception,
            titel: $name,
            fehlertext: $meldung !== '' ? $meldung : null,
            ort: str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()).':'.$e->getLine(),
            url: $this->anfrage->url(),
        ));
    }
}
