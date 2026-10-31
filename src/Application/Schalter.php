<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Application;

use Illuminate\Contracts\Config\Repository;

/** Liest einen Bool-Schalter der Config und wertet ihn wie `filter_var(…, FILTER_VALIDATE_BOOL)`. */
final class Schalter
{
    public static function an(Repository $config, string $schluessel, bool $vorgabe): bool
    {
        if (! $config->has($schluessel)) {
            return $vorgabe;
        }

        return filter_var($config->get($schluessel), FILTER_VALIDATE_BOOL);
    }
}
