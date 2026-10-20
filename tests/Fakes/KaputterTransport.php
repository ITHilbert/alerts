<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Fakes;

use Illuminate\Mail\Transport\ArrayTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;

/** Simuliert „SMTP nicht erreichbar“. */
final class KaputterTransport extends ArrayTransport
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new TransportException('SMTP nicht erreichbar');
    }
}
