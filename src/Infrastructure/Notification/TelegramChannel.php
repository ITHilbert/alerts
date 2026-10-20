<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure\Notification;

use ITHilbert\Alerts\Infrastructure\Telegram\TelegramKanal;

final class TelegramChannel
{
    public function __construct(private readonly TelegramKanal $telegram) {}

    public function send(object $notifiable, AlertNotification $notification): void
    {
        $chatId = $notifiable->routeNotificationFor(self::class, $notification);

        $this->telegram->sende((string) $chatId, $notification->telegramText());
    }
}
