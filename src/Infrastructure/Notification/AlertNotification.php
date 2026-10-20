<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure\Notification;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use ITHilbert\Alerts\Domain\Alert;

/** Bewusst kein ShouldQueue: der Versand muss auch bei ausgefallener Queue funktionieren. */
final class AlertNotification extends Notification
{
    public const KANAL_MAIL = 'mail';

    public const KANAL_TELEGRAM = 'telegram';

    public function __construct(
        private readonly Alert $alert,
        private readonly string $kanal,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return [$this->kanal === self::KANAL_MAIL ? 'mail' : TelegramChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $nachricht = (new MailMessage)
            ->subject('['.$this->alert->projekt.'] '.$this->alert->vorfall->titel)
            ->view('alerts::mail.alert', $this->daten());

        $mailer = config('alerts.mail.mailer');

        return is_string($mailer) && $mailer !== '' ? $nachricht->mailer($mailer) : $nachricht;
    }

    public function telegramText(): string
    {
        return trim(view('alerts::telegram.alert', $this->daten())->render());
    }

    /** @return array{alert: Alert, mitFehlertext: bool} */
    private function daten(): array
    {
        return [
            'alert' => $this->alert,
            'mitFehlertext' => filter_var(config('alerts.'.$this->kanal.'.fehlertext'), FILTER_VALIDATE_BOOL),
        ];
    }
}
