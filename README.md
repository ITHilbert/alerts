# Alerts

Basis-Package `ithilbert/alerts` für Störungsmeldungen per Mail und Telegram. Es ist für
**jedes** Laravel-Projekt gedacht und wird dort einmal installiert. Andere Packages
melden Störungen über den Port `AlertReporter`, ohne die Kanäle zu kennen.

Das Package erfasst automatisch unbehandelte Exceptions, kritische Log-Einträge sowie
404- und 403-Antworten. Wiederholungen werden pro Fehlerursache gedrosselt. Nach der
Behebung gibt der Entwickler die Sperre per Befehl oder signiertem Link wieder frei.

## Installation

Das Package liegt auf GitHub (privates Repository). In der `composer.json` des Projekts
das VCS-Repository eintragen:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/ITHilbert/alerts.git"
    }
]
```

Dann installieren:

```bash
composer require ithilbert/alerts
```

Solange noch kein Release-Tag existiert, mit `composer require ithilbert/alerts:dev-main`
installieren.

Da das Repository privat ist, braucht Composer Zugriff auf GitHub (z.B. über einen
GitHub-Token in `auth.json` oder `COMPOSER_AUTH`).

Die Konfiguration ist optional. Das Package bringt Standardwerte mit und bindet sie selbst ein.
Veröffentlichen nur nötig, wenn Werte in `config/alerts.php` angepasst werden sollen:

```bash
php artisan vendor:publish --tag=alerts-config
```

Die ServiceProvider-Registrierung erfolgt über Laravel-Auto-Discovery.

Voraussetzungen: PHP ^8.5, Laravel ^12.

## Konfiguration (`.env`)

Alle Werte stammen aus `src/Config/alerts.php`. Die Spalte „Standard“ zeigt den Wert ohne
gesetzte Variable.

| Variable | Standard | Bedeutung |
|---|---|---|
| `ALERTS_ENABLED` | `true` | `false` schaltet Erfassung und Versand komplett ab. Sinnvoll für lokale Entwicklung und Tests. |
| `ALERTS_CHANNELS` | `mail,telegram` | Aktive Kanäle, kommagetrennt. Gültig: `mail`, `telegram`. |
| `ALERTS_MAIL_TO` | leer | Empfänger, kommagetrennt. Leer = Mail-Kanal inaktiv. |
| `ALERTS_MAILER` | leer | Name eines Mailers aus `config/mail.php`. Leer = `MAIL_MAILER`. |
| `ALERTS_MAIL_FEHLERTEXT` | `true` | Fehlertext (Exception-Meldung bzw. Fehlertext des Ports) in der Mail. |
| `ALERTS_TELEGRAM_BOT_TOKEN` | leer | Token des Bots von BotFather. Leer = Telegram-Kanal inaktiv. |
| `ALERTS_TELEGRAM_CHAT_ID` | leer | Chat-ID des Empfängers (Person oder Gruppe). Leer = Telegram-Kanal inaktiv. |
| `ALERTS_TELEGRAM_FEHLERTEXT` | `false` | Fehlertext in der Telegram-Nachricht. Standard aus Datenschutzgründen aus, siehe unten. |
| `ALERTS_TELEGRAM_TIMEOUT` | `5` | Gesamt-Timeout des Telegram-Aufrufs in Sekunden. |
| `ALERTS_TELEGRAM_CONNECT_TIMEOUT` | `3` | Verbindungs-Timeout in Sekunden. |
| `ALERTS_SPERRE_STUNDEN` | `24` | Sperrdauer pro Fingerabdruck in Stunden. Dezimalwerte sind erlaubt. |
| `ALERTS_CACHE_STORE` | leer | Cache-Store für Sperren. Leer = Standard-Store der App. |
| `ALERTS_EXCEPTIONS` | `true` | Unbehandelte Exceptions erfassen. |
| `ALERTS_LOG` | `true` | Log-Einträge ab Level `critical` erfassen. |
| `ALERTS_HTTP_404` | `true` | 404 mit internem Referer erfassen. |
| `ALERTS_HTTP_403` | `true` | 403 bei angemeldetem Nutzer erfassen. |
| `ALERTS_FREIGABE_TAGE` | `7` | Gültigkeit des Freigabe-Links in Tagen. |

Nicht über `.env` einstellbar (nur in `config/alerts.php`):

- `alerts.telegram.api_url` ist fest auf `https://api.telegram.org` gesetzt. So kann kein
  Env-Fehler den Bot-Token an einen fremden Host senden.
- `alerts.drosselung.prefix` (`alerts`) und `alerts.freigabe.prefix` (`alerts`) bestimmen
  Cache-Präfix und URL-Präfix der Freigabe-Routen.
- `alerts.freigabe.middleware` (Standard `web`, `throttle:6,1`) schützt die Freigabe-Route.

Ein aktiver Kanal ohne vollständige Konfiguration wird still übersprungen. Ist in
`ALERTS_CHANNELS` also `telegram` gesetzt, ohne Token oder Chat-ID, passiert nichts
und es erscheint kein Hinweis.

`APP_NAME` und `APP_ENV` stehen in jeder Nachricht. `APP_URL` muss das öffentliche Projekt-URL
sein, weil daraus der Freigabe-Link gebaut wird und die Erkennung interner 404-Referer darauf beruht.

## Telegram einrichten

1. **Bot anlegen:** In Telegram den Kontakt `@BotFather` öffnen, `/newbot` senden und Namen
   sowie Benutzernamen (Endung `bot`) vergeben. BotFather liefert den Token. Er gehört
   nur in die `.env` (`ALERTS_TELEGRAM_BOT_TOKEN`) und nie ins Repository.
2. **Chat öffnen:** Den neuen Bot in Telegram anschreiben und `/start` senden. Bei einer
   Gruppe den Bot in die Gruppe aufnehmen und dort eine Nachricht schreiben.
3. **Chat-ID ermitteln:** Im Browser aufrufen:

   ```
   https://api.telegram.org/bot<TOKEN>/getUpdates
   ```

   In der Antwort steht bei `"chat":{"id": ...}` die Chat-ID. Gruppen-IDs sind negativ
   und beginnen mit `-`. Die ID in `ALERTS_TELEGRAM_CHAT_ID` eintragen.

   `getUpdates` liefert nur Nachrichten, die nicht älter als etwa 24 Stunden sind und noch nicht
   per Webhook abgeholt wurden. Ist die Antwort leer, eine neue Nachricht schreiben und
   den Aufruf wiederholen.
4. **Test:** Ein Aufruf über den Port (siehe unten) auslösen und prüfen, ob die Nachricht ankommt.

## Was wird erfasst

| Quelle | Auslöser | Fingerabdruck | Titel |
|---|---|---|---|
| Exception (`Exception`) | Unbehandelte Exception, die Laravel meldet. `dontReport`-Exceptions (z.B. `ValidationException`, `HttpException`) bleiben außen vor. | `exception.<klasse>.<hash>` | Klassenname ohne Namespace |
| Log (`Log`) | `Log::critical()`, `Log::alert()`, `Log::emergency()`. `error` und darunter lösen nichts aus. | In dieser Reihenfolge: gültiger `alert_key` im Kontext; Exception im Kontext (`exception`) → wie bei der Exception-Erfassung; sonst `log.<meldung-normalisiert>.<hash>` | Log-Meldung |
| 404 (`404`) | Antwort 404 und Referer gehört zur Domain aus `APP_URL`. Ohne Referer oder mit fremdem Referer keine Meldung. | `404.<pfad>.<hash>` | „Seite nicht gefunden (404)“ |
| 403 (`403`) | Antwort 403 und ein angemeldeter Nutzer. Anonyme 403 werden ignoriert. | `403.<pfad>.<hash>` | „Zugriff verweigert (403)“ |
| Port (`Meldung`) | Aufruf von `AlertReporter::melde()` | genau der übergebene Schlüssel | `titel` aus `Meldung` |

Bei 404 und 403 wird nur der Pfad für den Fingerabdruck verwendet. Der Query-String fehlt in
allen Nachrichten. Die 403-Nachricht enthält keine Nutzerdaten (kein Name, keine ID, keine E-Mail).

### Fallstricke bei der Erfassung

- **Exception-Fingerabdruck:** Er enthält Klasse, Datei und Zeile, aber nicht die Meldung.
  Verschiebt ein Deployment die Zeile, gilt das als neue Störung und löst erneut eine Meldung aus.
- **Exception zusätzlich auf `critical` geloggt:** Wird die Exception im Log-Kontext als `exception`
  übergeben (`Log::critical('…', ['exception' => $e])`), nutzt der Log-Kanal denselben Fingerabdruck
  wie die Exception-Erfassung. Es entsteht nur **eine** Meldung, unabhängig von der Reihenfolge.
- **Log ohne `alert_key`:** Der Fingerabdruck entsteht aus dem Meldungstext, wobei jede Ziffernfolge
  vorher durch `#` ersetzt wird. „Import 17 abgebrochen“ und „Import 18 abgebrochen“ ergeben also
  dieselbe Meldung. Andere variable Werte (Namen, Hex-IDs, UUIDs, Dateinamen) werden nicht normalisiert
  und erzeugen je eigene Meldungen. Für wiederkehrende Fehler mit solchen Werten `alert_key` setzen.
- **Ungültiger `alert_key`:** Entspricht der Schlüssel nicht dem Format (siehe unten), wird eine Warnung
  geloggt und der Fingerabdruck wie ohne Schlüssel abgeleitet.
- **404 je Pfad:** Jede betroffene URL hat einen eigenen Fingerabdruck. Viele tote URLs mit
  unterschiedlichen IDs erzeugen viele Meldungen.

## Nutzung aus anderen Packages

Ein Package meldet Störungen ausschließlich über den Port `AlertReporter`. Der Port wird per
Dependency Injection aufgelöst. Kein `new` des Dienstes, keine Facade, keine direkte
Abhängigkeit auf Interna des Alert-Packages.

```php
<?php

declare(strict_types=1);

namespace Vendor\Rechtstexte;

use ITHilbert\Alerts\Contracts\AlertReporter;
use ITHilbert\Alerts\Domain\Meldung;

final class RechtstextLader
{
    public function __construct(private readonly AlertReporter $alerts) {}

    public function lade(string $typ): ?string
    {
        // ... Abruf schlägt fehl und es gibt keinen Fallback ...

        $this->alerts->melde(new Meldung(
            schluessel: 'consentlaw.kein-fallback',
            titel: 'Rechtstext nicht lieferbar',
            fehlertext: 'API antwortete mit HTTP 503',
            details: ['typ' => $typ],
        ));

        return null;
    }
}
```

Die Parameter von `Meldung` sind benannt:

| Parameter | Typ | Pflicht | Bedeutung |
|---|---|---|---|
| `schluessel` | `string` | ja | Fingerabdruck der Störung, siehe Format unten |
| `titel` | `string` | ja | Betreff und Überschrift der Nachricht |
| `fehlertext` | `?string` | nein | Technischer Text. Wird je nach Kanal-Einstellung mitgesendet |
| `details` | `array<string, scalar\|null>` | nein | Zusatzfelder, z.B. `['typ' => 'impressum']` |

**Schlüsselformat:** `/^[a-z0-9][a-z0-9._-]{0,99}$/`. Empfohlen ist `<paket>.<ursache>`, z.B.
`consentlaw.kein-fallback`. Der Schlüssel muss fest und sprechend sein, nicht aus Werten
zusammengesetzt. Ein ungültiger Schlüssel wirft `ITHilbert\Alerts\Exceptions\UngueltigerSchluessel`
(eine `\DomainException`) schon beim Erzeugen von `Meldung`. Das ist ein Entwicklungsfehler
und sollte in den Tests des Packages auffallen.

`melde()` wirft keine Exception wegen Versandfehlern. Ein Package darf den Aufruf deshalb
ohne eigenes try/catch verwenden.

Das Package ist eine Pflicht-Abhängigkeit für jedes Package, das den Port nutzt. Ohne
installiertes `ithilbert/alerts` schlägt die DI-Auflösung fehl.

## Drosselung und Freigabe

### Sperre

- Die erste Störung eines Fingerabdrucks wird sofort gemeldet. Danach ist dieser Fingerabdruck
  für `ALERTS_SPERRE_STUNDEN` (Standard 24 h) gesperrt.
- Weitere Auftreten innerhalb der Sperre werden nur gezählt und nicht verschickt.
- Nach Ablauf der Sperre kommt die nächste Störung als Sammelmeldung mit dem Hinweis
  „X× seit letzter Meldung“.
- Die Sperre gilt auch dann, wenn der Versand fehlschlägt. Ein Fehlschlag soll keine
  Meldungsflut auslösen.
- Die Sperre liegt im Cache (`ALERTS_CACHE_STORE`), nicht in der Datenbank. Meldungen funktionieren
  so auch bei ausgefallener Datenbank.

**Fallstrick:** Ist der Cache nicht erreichbar, wird die Meldung ohne Sperre verschickt und der
Fehler auf `warning` geloggt. Bei einem Cache-Ausfall gibt es also keine Drosselung.

### Freigabe per Befehl

```bash
# Eine bestimmte Sperre aufheben (Sperre und Zähler)
php artisan alerts:reset consentlaw.kein-fallback

# Alle Sperren aufheben
php artisan alerts:reset --all
```

- Das nächste Auftreten nach einem Reset wird sofort gemeldet.
- Ist der Fingerabdruck unbekannt, gibt der Befehl einen Hinweis aus und endet mit Exit-Code `1`.
- Ohne Fingerabdruck und ohne `--all` endet der Befehl ebenfalls mit Exit-Code `1`.

### Freigabe per Link

Jede Mail und jede Telegram-Nachricht enthält einen signierten Link:

```
https://<APP_URL>/alerts/freigabe/<fingerabdruck>?expires=…&signature=…
```

Der Link ist standardmäßig 7 Tage gültig (`ALERTS_FREIGABE_TAGE`).

- **GET** öffnet nur eine Bestätigungsseite. Die Sperre bleibt bestehen.
- **POST** mit CSRF-Token gibt die Sperre frei. Erst dieser Schritt ändert etwas.
- Ungültige oder abgelaufene Signatur liefert HTTP 403.
- Die Route ist gedrosselt auf 6 Anfragen pro Minute.

**Warum zweistufig:** Mail-Scanner, Link-Vorschauen und Messenger rufen Links automatisch per
GET auf. Eine Freigabe per GET würde Sperren ungewollt lösen, bevor jemand den Fehler gesehen hat.

**Fallstrick:** Der Link ist die Berechtigung. Wer ihn besitzt, kann die Sperre innerhalb der
Gültigkeit ohne Anmeldung aufheben. Deshalb nur an eigene Empfänger senden.

Die Bestätigungsseite und die Erfolgsseite laden keine externen Assets, Schriften oder Skripte
(DSGVO).

## Fehlertext je Kanal und Datenschutz

Der Fehlertext ist die technische Meldung einer Exception oder der Parameter `fehlertext` aus
`Meldung`. Ob er mitgesendet wird, legt jeder Kanal für sich fest:

| Kanal | Variable | Standard | Begründung |
|---|---|---|---|
| Mail | `ALERTS_MAIL_FEHLERTEXT` | `true` | Die Mail geht nur an die eigenen Adressen. Der Text hilft bei der Fehlersuche. |
| Telegram | `ALERTS_TELEGRAM_FEHLERTEXT` | `false` | Telegram ist ein Drittanbieter außerhalb der EU. Fehlertexte können personenbezogene Daten enthalten. |

Der Ort (Datei und Zeile) bei Exceptions erscheint unabhängig davon immer.

Datenschutz-Hinweise:

- Es werden keine Nutzerdaten, keine Secrets und kein Bot-Token gesendet. Der Token wird
  auch im Log nicht ausgegeben.
- Fehlertexte von Exceptions können Werte enthalten, z.B. E-Mail-Adressen oder IDs. Mit
  `ALERTS_TELEGRAM_FEHLERTEXT=true` übernimmt der Betreiber die Verantwortung dafür, dass diese
  Daten an Telegram übertragen werden dürfen.
- `details` in `Meldung` landen unverändert in Mail und Telegram. Dort keine personenbezogenen
  Daten übergeben.
- Die URL in der Nachricht enthält keinen Query-String.

## Vorlagen überschreiben

Die Vorlagen für Mail und Telegram sowie die Freigabe-Seiten lassen sich veröffentlichen:

```bash
php artisan vendor:publish --tag=alerts-views
```

Die Kopien liegen danach unter `resources/views/vendor/alerts/` und haben Vorrang vor dem Package.
Die Vorlagen erhalten die Variablen `$alert` und `$mitFehlertext`.

Die Telegram-Vorlage wird als Klartext gesendet, ohne HTML- oder Markdown-Auszeichnung.

## Tests

Das Package testet gegen eine echte Laravel-Anwendung (Orchestra Testbench):

```bash
composer install
vendor/bin/phpunit
```

## Hinweise

- **Versand ist synchron.** Mail und Telegram werden direkt im Request oder Befehl verschickt.
  Ein Queue-Worker ist nicht nötig. Der Grund: Fällt der Worker aus, bliebe sonst genau die
  Meldung aus, die den Ausfall anzeigen soll. Telegram hat einen knappen Timeout (5 s).
- Die Abschaltung über `ALERTS_ENABLED=false` wirkt auf Erfassung und Versand.
- Eigene Kanäle (z.B. Slack) sind nicht enthalten. Sie lassen sich später als zusätzliche
  Notification-Kanäle ergänzen, ohne den Port zu ändern.
