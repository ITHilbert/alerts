<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Exceptions;

final class UngueltigerSchluessel extends \DomainException
{
    public static function fuer(string $schluessel): self
    {
        return new self(sprintf(
            'Ungültiger Alert-Schlüssel "%s": erlaubt sind a-z, 0-9, ".", "_" und "-" (max. 100 Zeichen, Beginn mit Buchstabe oder Ziffer).',
            mb_strimwidth($schluessel, 0, 60, '…'),
        ));
    }
}
