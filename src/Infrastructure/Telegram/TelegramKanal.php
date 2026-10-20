<?php

declare(strict_types=1);

namespace ITHilbert\Alerts\Infrastructure\Telegram;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\HttpClientException;
use ITHilbert\Alerts\Exceptions\TelegramVersandFehlgeschlagen;

/** Adapter zur Telegram-Bot-API. Übersetzt alle Fremd-Exceptions, damit der Token nie nach oben gelangt. */
final class TelegramKanal
{
    public function __construct(
        private readonly Factory $http,
        private readonly Repository $config,
    ) {}

    /** @throws TelegramVersandFehlgeschlagen */
    public function sende(string $chatId, string $text): void
    {
        $url = rtrim((string) $this->config->get('alerts.telegram.api_url'), '/')
            .'/bot'.$this->config->get('alerts.telegram.bot_token').'/sendMessage';

        try {
            $antwort = $this->http
                ->timeout((int) $this->config->get('alerts.telegram.timeout', 5))
                ->connectTimeout((int) $this->config->get('alerts.telegram.connect_timeout', 3))
                ->post($url, ['chat_id' => $chatId, 'text' => $text]);
        } catch (HttpClientException|GuzzleException $e) {
            throw TelegramVersandFehlgeschlagen::verbindung($e::class);
        }

        if ($antwort->failed() || $antwort->json('ok') === false) {
            throw TelegramVersandFehlgeschlagen::status($antwort->status());
        }
    }
}
