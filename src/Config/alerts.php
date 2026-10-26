<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Schalter
    |---------------------------------------------------------------------------
    | false = keine Erfassung und kein Versand. Gedacht für lokale Entwicklung
    | und Tests, damit dort keine echten Meldungen verschickt werden.
    */
    'enabled' => env('ALERTS_ENABLED', true),

    /*
    |---------------------------------------------------------------------------
    | Aktive Kanäle
    |---------------------------------------------------------------------------
    | Kommagetrennt, z.B. 'mail,telegram'. Der String wird im Code geparst.
    | Ein Kanal ohne vollständige Konfiguration wird still übersprungen, damit
    | ein fehlender Schlüssel nicht zu einem Abbruch der Meldung führt.
    */
    'channels' => env('ALERTS_CHANNELS', 'mail,telegram'),

    /*
    |---------------------------------------------------------------------------
    | Kanäle je Quelle
    |---------------------------------------------------------------------------
    | Schlüssel: exception, log, meldung, 404, 403. Wert: Liste von Kanälen
    | ('mail', 'telegram'), z.B. '404' => ['mail'].
    | Nicht gesetzt = alle aktiven Kanäle aus `channels`. Leere Liste = für diese
    | Quelle kein Versand. Ein Kanal der Liste wird nur genutzt, wenn er in
    | `channels` aktiv und eingerichtet ist. Unbekannte Namen werden ignoriert
    | und als Warnung ins Log geschrieben.
    */
    'kanaele' => [],

    /*
    |---------------------------------------------------------------------------
    | Mail
    |---------------------------------------------------------------------------
    | to: Eine oder mehrere Adressen, kommagetrennt. Leer = Mail-Kanal inaktiv.
    | mailer: null nutzt den Standard-Mailer der App (MAIL_MAILER). Ein eigener
    | Mailer aus config/mail.php ist nur nötig, wenn Alarme anders zugestellt
    | werden sollen als der Rest der App.
    | fehlertext: Standard true, weil Fehlertexte für den Betreiber zur
    | Fehlersuche gebraucht werden. Die Mail geht nur an die eigenen Adressen.
    */
    'mail' => [
        'to' => env('ALERTS_MAIL_TO'),
        'mailer' => env('ALERTS_MAILER'),
        'fehlertext' => env('ALERTS_MAIL_FEHLERTEXT', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | Telegram
    |---------------------------------------------------------------------------
    | Telegram ist ein Drittanbieter außerhalb der EU. Fehlertexte können
    | personenbezogene Daten enthalten (z.B. Werte aus einer Exception-Nachricht),
    | daher ist der Fehlertext standardmäßig AUS (Privacy by Default). Wer ihn
    | aktiviert, übernimmt diese Verantwortung bewusst.
    |
    | timeout: Der Versand ist synchron. Die Seite darf wegen eines hängenden
    | Bot-Aufrufs nicht lange warten, deshalb knapp bemessen.
    | api_url ist fest, damit kein Env-Fehler den Token an einen fremden Host sendet.
    */
    'telegram' => [
        'bot_token' => env('ALERTS_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('ALERTS_TELEGRAM_CHAT_ID'),
        'fehlertext' => env('ALERTS_TELEGRAM_FEHLERTEXT', false),
        'api_url' => 'https://api.telegram.org',
        'timeout' => env('ALERTS_TELEGRAM_TIMEOUT', 5),
        'connect_timeout' => env('ALERTS_TELEGRAM_CONNECT_TIMEOUT', 3),
    ],

    /*
    |---------------------------------------------------------------------------
    | Drosselung
    |---------------------------------------------------------------------------
    | sperre_stunden: Ein identischer Fingerabdruck wird in diesem Zeitraum nicht
    | erneut verschickt, sondern nur gezählt. 24 h bedeutet: höchstens eine
    | Meldung pro Fehlerursache und Tag. Freigabe vorzeitig per `alerts:reset`.
    |
    | store: Bewusst der Cache statt der Datenbank. Meldungen sollen auch dann
    | funktionieren, wenn genau die Datenbank ausgefallen ist. null = Standard-Store.
    | prefix: Präfix für Cache-Schlüssel, um Kollisionen mit anderen Packages
    | im selben Store zu vermeiden.
    */
    'drosselung' => [
        'sperre_stunden' => env('ALERTS_SPERRE_STUNDEN', 24),
        'store' => env('ALERTS_CACHE_STORE'),
        'prefix' => 'alerts',
    ],

    /*
    |---------------------------------------------------------------------------
    | Erfassung
    |---------------------------------------------------------------------------
    | exceptions: Unbehandelte Exceptions über den Exception-Handler. Nur Fehler,
    | die Laravel ohnehin meldet – dontReport-Ausnahmen bleiben außen vor.
    | log: Log-Einträge ab Level `critical`. Darunter sind Einträge Routinerauschen.
    | http_404: Nur bei Referer der eigenen Domain, denn das ist ein kaputter
    | interner Link. Fremde Referer und Bots würden die Mail sonst fluten.
    | Ebenfalls ohne Meldung: Referer-Pfad gleich dem angefragten Pfad (Groß-/
    | Kleinschreibung, Prozent-Kodierung, abschließender /, Schema und Query-String
    | zählen nicht) – eine Seite, die selbst 404 liefert, kann nicht auf sich verlinken.
    | http_404_ignorieren: Pfad-Muster wie bei Request::is() (ohne führenden /,
    | * als Platzhalter, Groß-/Kleinschreibung egal), z.B. ['entwickler/*', 'manifest.json'].
    | Env: kommagetrennt. Leer = alles wie bisher melden.
    | http_403: Nur bei angemeldetem Nutzer. Anonyme 403 sind meist Scanner.
    */
    'erfassung' => [
        'exceptions' => env('ALERTS_EXCEPTIONS', true),
        'log' => env('ALERTS_LOG', true),
        'http_404' => env('ALERTS_HTTP_404', true),
        'http_404_ignorieren' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('ALERTS_HTTP_404_IGNORIEREN', ''))),
            static fn (string $eintrag): bool => $eintrag !== '',
        )),
        'http_403' => env('ALERTS_HTTP_403', true),
    ],

    /*
    |---------------------------------------------------------------------------
    | Freigabe
    |---------------------------------------------------------------------------
    | link_gueltig_tage: Gültigkeit des signierten Links in Mail/Telegram.
    | prefix: URL-Präfix der Freigabe-Routen.
    | middleware: Throttle schützt die Freigabe-Route vor Missbrauch durch
    | Brute-Force auf Signaturen.
    |
    | Der Link öffnet nur eine Bestätigungsseite. Freigegeben wird erst per POST.
    | Grund: Mail-Scanner und Link-Vorschauen rufen URLs automatisch per GET auf;
    | ein GET-Freigabe würde Sperren ungewollt lösen.
    */
    'freigabe' => [
        'link_gueltig_tage' => env('ALERTS_FREIGABE_TAGE', 7),
        'prefix' => 'alerts',
        'middleware' => ['web', 'throttle:6,1'],
    ],

];
