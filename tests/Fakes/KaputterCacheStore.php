<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Fakes;

use Illuminate\Contracts\Cache\Store;
use RuntimeException;

/** Simuliert einen Cache, der bei jedem Zugriff wirft (Redis weg, Verbindung gekappt). */
final class KaputterCacheStore implements Store
{
    private function aus(): never
    {
        throw new RuntimeException('Cache nicht erreichbar');
    }

    public function get($key): never
    {
        $this->aus();
    }

    public function many(array $keys): never
    {
        $this->aus();
    }

    public function put($key, $value, $seconds): never
    {
        $this->aus();
    }

    public function putMany(array $values, $seconds): never
    {
        $this->aus();
    }

    public function increment($key, $value = 1): never
    {
        $this->aus();
    }

    public function decrement($key, $value = 1): never
    {
        $this->aus();
    }

    public function forever($key, $value): never
    {
        $this->aus();
    }

    public function touch($key, $seconds): never
    {
        $this->aus();
    }

    public function forget($key): never
    {
        $this->aus();
    }

    public function flush(): never
    {
        $this->aus();
    }

    public function getPrefix(): string
    {
        return '';
    }
}
