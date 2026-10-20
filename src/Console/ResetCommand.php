<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Console;

use Illuminate\Console\Command;
use ITHilbert\Alerts\Application\GibAlertFrei;

final class ResetCommand extends Command
{
    protected $signature = 'alerts:reset {fingerabdruck? : Fingerabdruck der Meldung} {--all : Alle Sperren aufheben}';

    protected $description = 'Hebt die Sperre (und den Zähler) einer Alert-Meldung auf';

    public function handle(GibAlertFrei $gibAlertFrei): int
    {
        if ($this->option('all')) {
            $this->info($gibAlertFrei->alle().' Sperre(n) aufgehoben.');

            return self::SUCCESS;
        }

        $fingerabdruck = $this->argument('fingerabdruck');

        if (! is_string($fingerabdruck) || $fingerabdruck === '') {
            $this->error('Bitte einen Fingerabdruck angeben oder --all verwenden.');

            return self::FAILURE;
        }

        if (! $gibAlertFrei->ausfuehren($fingerabdruck)) {
            $this->error('Unbekannter Fingerabdruck: '.$fingerabdruck);

            return self::FAILURE;
        }

        $this->info('Sperre für '.$fingerabdruck.' aufgehoben.');

        return self::SUCCESS;
    }
}
