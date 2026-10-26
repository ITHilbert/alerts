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
        foreach ($this->empfaenger($alert) as $kanal => $notifiable) {
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
    private function empfaenger(Alert $alert): array
    {
        $kanaele = array_filter(array_map('trim', explode(',', strtolower((string) $this->config->get('alerts.channels', '')))));
        $erlaubt = $this->kanaeleDerQuelle($alert);

        if ($erlaubt !== null) {
            $kanaele = array_values(array_intersect($kanaele, $erlaubt));
        }

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

    /**
     * Kanäle aus `alerts.kanaele.<quelle>`; null = nichts gesetzt (alle aktiven Kanäle).
     * Der Schlüssel wird über das Array gelesen, weil `404`/`403` in PHP Integer-Schlüssel sind.
     *
     * @return list<string>|null
     */
    private function kanaeleDerQuelle(Alert $alert): ?array
    {
        $alle = $this->config->get('alerts.kanaele');
        $quelle = $alert->vorfall->quelle->value;

        if (! is_array($alle) || ! array_key_exists($quelle, $alle) || ! is_array($alle[$quelle])) {
            return null;
        }

        $liste = $alle[$quelle];
        $bekannt = [AlertNotification::KANAL_MAIL, AlertNotification::KANAL_TELEGRAM];
        $ergebnis = [];

        foreach ($liste as $name) {
            $name = strtolower(trim(is_scalar($name) ? (string) $name : ''));

            if (in_array($name, $bekannt, true)) {
                $ergebnis[] = $name;
            } elseif ($name !== '') {
                $this->logger->warning('Alerts: Unbekannter Kanal "'.$name.'" in alerts.kanaele.'.$quelle.' wird ignoriert.');
            }
        }

        return $ergebnis;
    }
}
