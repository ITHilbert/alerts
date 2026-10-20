<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Notifications\AnonymousNotifiable;
use ITHilbert\Alerts\Application\AlertVersand;
use ITHilbert\Alerts\Domain\Alert;
use ITHilbert\Alerts\Infrastructure\Notification\AlertNotification;
use ITHilbert\Alerts\Infrastructure\Notification\TelegramChannel;
use Psr\Log\LoggerInterface;

final class NotificationVersand implements AlertVersand
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function sende(Alert $alert): void
    {
        foreach ($this->empfaenger() as $kanal => $notifiable) {
            try {
                $this->dispatcher->sendNow($notifiable, new AlertNotification($alert, $kanal));
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Alerts: Versand über Kanal "'.$kanal.'" fehlgeschlagen: '
                    .SichereFehlerbeschreibung::von($e, (string) $this->config->get('alerts.telegram.bot_token')),
                );
            }
        }
    }

    /** @return array<string, AnonymousNotifiable> */
    private function empfaenger(): array
    {
        $kanaele = array_filter(array_map('trim', explode(',', strtolower((string) $this->config->get('alerts.channels', '')))));
        $ergebnis = [];

        if (in_array(AlertNotification::KANAL_MAIL, $kanaele, true)) {
            $adressen = array_values(array_filter(array_map('trim', explode(',', (string) $this->config->get('alerts.mail.to')))));

            if ($adressen !== []) {
                $ergebnis[AlertNotification::KANAL_MAIL] = (new AnonymousNotifiable)->route('mail', $adressen);
            }
        }

        $chatId = trim((string) $this->config->get('alerts.telegram.chat_id'));

        if (in_array(AlertNotification::KANAL_TELEGRAM, $kanaele, true)
            && $chatId !== '' && trim((string) $this->config->get('alerts.telegram.bot_token')) !== '') {
            $ergebnis[AlertNotification::KANAL_TELEGRAM] = (new AnonymousNotifiable)->route(TelegramChannel::class, $chatId);
        }

        return $ergebnis;
    }
}
