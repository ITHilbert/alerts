<?php

declare(strict_types=1);

namespace ITHilbert\Alerts;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Events\Routing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ITHilbert\Alerts\Application\AktuelleAnfrage;
use ITHilbert\Alerts\Application\AlertVersand;
use ITHilbert\Alerts\Application\Drosselung;
use ITHilbert\Alerts\Application\FreigabeLinks;
use ITHilbert\Alerts\Application\MeldeAlert;
use ITHilbert\Alerts\Application\StandardAlertReporter;
use ITHilbert\Alerts\Console\ResetCommand;
use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Erfassung\ExceptionErfasser;
use ITHilbert\Alerts\Erfassung\LogErfasser;
use ITHilbert\Alerts\Erfassung\StatusErfasser;
use ITHilbert\Alerts\Infrastructure\AnfrageHalter;
use ITHilbert\Alerts\Infrastructure\CacheDrosselung;
use ITHilbert\Alerts\Infrastructure\LaravelAktuelleAnfrage;
use ITHilbert\Alerts\Infrastructure\NotificationVersand;
use ITHilbert\Alerts\Infrastructure\SignierteFreigabeLinks;

final class AlertsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/alerts.php', 'alerts');

        $this->app->singleton(Drosselung::class, CacheDrosselung::class);
        $this->app->singleton(AlertVersand::class, NotificationVersand::class);
        $this->app->singleton(FreigabeLinks::class, SignierteFreigabeLinks::class);
        $this->app->scoped(AnfrageHalter::class);
        $this->app->singleton(AktuelleAnfrage::class, LaravelAktuelleAnfrage::class);
        $this->app->singleton(MeldeAlert::class);
        $this->app->singleton(AlertReporter::class, StandardAlertReporter::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'alerts');

        $this->publishes([
            __DIR__.'/Config/alerts.php' => config_path('alerts.php'),
        ], 'alerts-config');

        $this->publishes([
            __DIR__.'/Resources/views' => resource_path('views/vendor/alerts'),
        ], 'alerts-views');

        if ($this->app->runningInConsole()) {
            $this->commands([ResetCommand::class]);
        }

        $this->registriereRouten();
        $this->registriereEingaenge();
    }

    private function registriereRouten(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $middleware = array_values(array_filter((array) config('alerts.freigabe.middleware', [])));

        Route::middleware([...$middleware, 'signed'])
            ->prefix((string) config('alerts.freigabe.prefix', 'alerts'))
            ->group(__DIR__.'/Routes/freigabe.php');
    }

    private function registriereEingaenge(): void
    {
        // Der Callback darf nie false liefern, sonst unterdrückt Laravel sein normales Logging.
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->reportable(function (\Throwable $e): void {
                    $this->app->make(ExceptionErfasser::class)->erfasse($e);
                });
            }
        });

        $events = $this->app['events'];

        $events->listen(MessageLogged::class, fn (MessageLogged $e) => $this->app->make(LogErfasser::class)->behandle($e));
        $events->listen(Routing::class, fn (Routing $e) => $this->app->make(AnfrageHalter::class)->setze($e->request));
        $events->listen(RequestHandled::class, function (RequestHandled $e): void {
            $this->app->make(StatusErfasser::class)->behandle($e);
            $this->app->make(AnfrageHalter::class)->vergiss();
        });
    }
}
