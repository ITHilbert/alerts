<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

use Carbon\Carbon;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use ITHilbert\Alerts\Application\Drosselung;
use ITHilbert\Alerts\Domain\Fensterstand;
use ITHilbert\Alerts\Domain\Fingerabdruck;
use Psr\Log\LoggerInterface;

final class CacheDrosselung implements Drosselung
{
    /** Der Zähler soll die Sperre überdauern, damit die Sammelmeldung ihn noch kennt. */
    private const ZAEHLER_SEKUNDEN = 30 * 86400;

    public function __construct(
        private readonly Factory $caches,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function betrete(Fingerabdruck $fingerabdruck): ?int
    {
        try {
            $cache = $this->cache();
            $wert = $fingerabdruck->wert;
            $sekunden = max(1, (int) round((float) $this->config->get('alerts.drosselung.sperre_stunden', 24) * 3600));

            if (! $cache->add($this->schluessel('sperre', $wert), 1, $sekunden)) {
                $cache->add($this->schluessel('zaehler', $wert), 0, self::ZAEHLER_SEKUNDEN);
                $cache->increment($this->schluessel('zaehler', $wert));

                return null;
            }

            $zaehler = (int) $cache->pull($this->schluessel('zaehler', $wert), 0);
            $this->indexAendern($cache, static fn (array $index): array => array_values(array_unique([...$index, $wert])));

            return $zaehler;
        } catch (\Throwable $e) {
            $this->logger->warning('Alerts: Drosselung nicht verfügbar, Meldung wird ohne Sperre verschickt ('.$e::class.').');

            return 0;
        }
    }

    public function zaehleImFenster(string $quelle, string $eintrag, int $fensterSekunden, int $merken): ?Fensterstand
    {
        try {
            $cache = $this->cache();
            $schluessel = $this->schluessel('fenster', $quelle);

            return $this->exklusiv($cache, $schluessel.'-sperre', function () use ($cache, $schluessel, $eintrag, $fensterSekunden, $merken): Fensterstand {
                $jetzt = Carbon::now()->getTimestamp();
                $stand = $cache->get($schluessel);

                if (
                    ! is_array($stand)
                    || ! is_int($stand['beginn'] ?? null)
                    || ! is_int($stand['anzahl'] ?? null)
                    || ! is_array($stand['eintraege'] ?? null)
                    || $jetzt - $stand['beginn'] >= $fensterSekunden
                ) {
                    $stand = ['beginn' => $jetzt, 'anzahl' => 0, 'eintraege' => []];
                }

                $eintraege = array_values(array_filter($stand['eintraege'], 'is_string'));

                if (count($eintraege) < $merken) {
                    $eintraege[] = $eintrag;
                }

                $stand = ['beginn' => $stand['beginn'], 'anzahl' => $stand['anzahl'] + 1, 'eintraege' => $eintraege];

                // Der Eintrag läuft mit dem Fenster ab, nie später.
                $cache->put($schluessel, $stand, max(1, $stand['beginn'] + $fensterSekunden - $jetzt));

                return new Fensterstand($stand['anzahl'], $eintraege);
            });
        } catch (\Throwable $e) {
            $this->logger->warning('Alerts: Fensterzählung nicht verfügbar, Meldung wird einzeln verschickt ('.$e::class.').');

            return null;
        }
    }

    public function gibFrei(string $fingerabdruck): bool
    {
        $cache = $this->cache();
        $bekannt = $cache->has($this->schluessel('sperre', $fingerabdruck))
            || $cache->has($this->schluessel('zaehler', $fingerabdruck))
            || in_array($fingerabdruck, $this->index($cache), true);

        $this->vergiss($cache, $fingerabdruck);
        $this->indexAendern($cache, static fn (array $index): array => array_values(array_diff($index, [$fingerabdruck])));

        return $bekannt;
    }

    public function gibAlleFrei(): int
    {
        $cache = $this->cache();
        $index = $this->index($cache);

        foreach ($index as $fingerabdruck) {
            $this->vergiss($cache, $fingerabdruck);
        }

        $cache->forget($this->schluessel('index'));

        return count($index);
    }

    private function cache(): Cache
    {
        $store = $this->config->get('alerts.drosselung.store');

        return $this->caches->store(is_string($store) && $store !== '' ? $store : null);
    }

    private function schluessel(string $art, string $wert = ''): string
    {
        $praefix = (string) $this->config->get('alerts.drosselung.prefix', 'alerts');

        return $praefix.'.'.$art.($wert !== '' ? '.'.$wert : '');
    }

    private function vergiss(Cache $cache, string $fingerabdruck): void
    {
        $cache->forget($this->schluessel('sperre', $fingerabdruck));
        $cache->forget($this->schluessel('zaehler', $fingerabdruck));
    }

    /** @return list<string> */
    private function index(Cache $cache): array
    {
        $index = $cache->get($this->schluessel('index'), []);

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    /** @param \Closure(list<string>): list<string> $aenderung */
    private function indexAendern(Cache $cache, \Closure $aenderung): void
    {
        $this->exklusiv($cache, $this->schluessel('index-sperre'), fn () => $cache->forever($this->schluessel('index'), $aenderung($this->index($cache))));
    }

    /**
     * Führt Lesen und Schreiben unter einer Cache-Sperre aus, wenn der Store Sperren kann.
     *
     * @template T
     *
     * @param  \Closure(): T  $arbeit
     * @return T
     */
    private function exklusiv(Cache $cache, string $sperre, \Closure $arbeit): mixed
    {
        $store = $cache instanceof CacheRepository ? $cache->getStore() : null;

        if ($store instanceof LockProvider) {
            return $store->lock($sperre, 5)->block(2, $arbeit);
        }

        return $arbeit();
    }
}
