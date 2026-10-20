<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Exceptions;

final class TelegramVersandFehlgeschlagen extends \RuntimeException
{
    public static function status(int $status): self
    {
        return new self('Telegram antwortete mit HTTP '.$status.'.');
    }

    public static function verbindung(string $ursache): self
    {
        return new self('Telegram nicht erreichbar ('.$ursache.').');
    }
}
