<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

/** Beschreibt eine Exception fürs Log, ohne Bot-Token preiszugeben (Fehlertexte enthalten oft die URL). */
final class SichereFehlerbeschreibung
{
    public static function von(\Throwable $e, ?string $geheimnis = null): string
    {
        $text = $e->getMessage();

        if ($geheimnis !== null && $geheimnis !== '') {
            $text = str_replace($geheimnis, '***', $text);
        }

        $text = (string) preg_replace('#bot\d+:[A-Za-z0-9_-]+#', 'bot***', $text);

        return $e::class.': '.mb_strimwidth($text, 0, 300, '…');
    }
}
