<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Tests\Fakes;

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\RawMessage;

/** Loggt beim Senden selbst auf `critical` — prüft den Wiedereintritts-Schutz (AK17). */
final class PlaudernderTransport extends ArrayTransport
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        Log::critical('Transport meldet sich während des Versands');

        return parent::send($message, $envelope);
    }
}
