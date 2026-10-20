<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ITHilbert\Alerts\Http\Controllers\FreigabeController;

Route::get('freigabe/{fingerabdruck}', [FreigabeController::class, 'bestaetigen'])
    ->where('fingerabdruck', '[a-z0-9._-]+')
    ->name('alerts.freigabe');

Route::post('freigabe/{fingerabdruck}', [FreigabeController::class, 'ausfuehren'])
    ->where('fingerabdruck', '[a-z0-9._-]+')
    ->name('alerts.freigabe.ausfuehren');
