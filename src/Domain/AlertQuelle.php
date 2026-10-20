<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Domain;

enum AlertQuelle: string
{
    case Meldung = 'meldung';
    case Exception = 'exception';
    case Log = 'log';
    case NichtGefunden = '404';
    case Verboten = '403';

    public function label(): string
    {
        return match ($this) {
            self::Meldung => 'Meldung',
            self::Exception => 'Exception',
            self::Log => 'Log',
            self::NichtGefunden => '404',
            self::Verboten => '403',
        };
    }
}
