<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use ITHilbert\Alerts\Application\GibAlertFrei;

final class FreigabeController
{
    public function bestaetigen(Request $request, string $fingerabdruck): View
    {
        return view('alerts::freigabe.bestaetigen', [
            'fingerabdruck' => $fingerabdruck,
            'aktion' => $request->fullUrl(),
        ]);
    }

    public function ausfuehren(string $fingerabdruck, GibAlertFrei $gibAlertFrei): View
    {
        return view('alerts::freigabe.erfolg', [
            'fingerabdruck' => $fingerabdruck,
            'warGesperrt' => $gibAlertFrei->ausfuehren($fingerabdruck),
        ]);
    }
}
