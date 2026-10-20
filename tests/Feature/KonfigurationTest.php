<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use ITHilbert\Alerts\AlertsServiceProvider;
use ITHilbert\Alerts\Tests\TestCase;

/** Standardwerte der Config und veröffentlichbare Dateien (AK18, AK19). */
final class KonfigurationTest extends TestCase
{
    public function test_standardwerte_ohne_env(): void
    {
        $config = require __DIR__.'/../../src/Config/alerts.php';

        $this->assertTrue($config['enabled']);
        $this->assertSame('mail,telegram', $config['channels']);
        $this->assertNull($config['mail']['to']);
        $this->assertNull($config['mail']['mailer']);
        $this->assertTrue($config['mail']['fehlertext']);
        $this->assertFalse($config['telegram']['fehlertext'], 'Telegram ohne Fehlertext (Privacy by Default).');
        $this->assertSame(5, $config['telegram']['timeout']);
        $this->assertSame(24, $config['drosselung']['sperre_stunden']);
        $this->assertSame(7, $config['freigabe']['link_gueltig_tage']);
        $this->assertArrayNotHasKey('middleware_group', $config['erfassung']);

        foreach (['exceptions', 'log', 'http_404', 'http_403'] as $quelle) {
            $this->assertTrue($config['erfassung'][$quelle], "Erfassung {$quelle} standardmäßig an.");
        }
    }

    public function test_config_ist_unter_alerts_geladen(): void
    {
        $this->assertSame('https://api.telegram.org', config('alerts.telegram.api_url'));
    }

    public function test_config_und_vorlagen_sind_veroeffentlichbar(): void
    {
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(AlertsServiceProvider::class, 'alerts-config'));
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(AlertsServiceProvider::class, 'alerts-views'));
    }

    public function test_vorlagen_existieren(): void
    {
        foreach (['alerts::mail.alert', 'alerts::telegram.alert', 'alerts::freigabe.bestaetigen'] as $view) {
            $this->assertTrue(view()->exists($view), "View {$view} fehlt.");
        }
    }
}
