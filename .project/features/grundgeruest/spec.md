# Spec: Grundgerüst Alert-Package

Diskussion: [alerts.md](../../notizen/diskussionen/alerts.md)

## Ziel
Ein Basis-Package `ithilbert/alerts` (Namespace `ITHilbert\Alerts`), das in jedem Projekt Störungen erfasst und per Mail und Telegram meldet. Wiederholungen werden gedrosselt; nach der Behebung gibt der Entwickler die Meldung per Befehl oder signiertem Link wieder frei.

## Akzeptanzkriterien

### Eingänge
- **AK1 — Port:** Gegeben ein Package ruft `AlertReporter::melde()` mit festem Schlüssel (z.B. `consentlaw.kein-fallback`), Titel und Details auf, dann wird eine Meldung mit genau diesem Schlüssel als Fingerabdruck verschickt. Der Port wird per Dependency Injection aufgelöst.
- **AK2 — Unbehandelte Exception:** Gegeben eine Exception wird vom Exception-Handler gemeldet (`report()`), dann wird eine Meldung verschickt. Fingerabdruck: Exception-Klasse + Datei + Zeile (ohne Meldung).
- **AK3 — Nicht meldepflichtige Exceptions:** Exceptions, die Laravel nicht meldet (`dontReport`, z.B. `ValidationException`, `HttpException`), lösen keine Meldung aus.
- **AK4 — Log-Kanal:** Gegeben `Log::critical()`, `Log::alert()` oder `Log::emergency()` wird aufgerufen, dann wird eine Meldung verschickt. `Log::error()` und darunter lösen nichts aus. Ein Kontext-Schlüssel `alert_key` überschreibt den Fingerabdruck.
- **AK5 — 404 intern:** Gegeben eine Antwort hat Status 404 und der Referer gehört zur eigenen Domain (`APP_URL`), dann wird eine Meldung mit der aufgerufenen URL und dem Referer verschickt. Fingerabdruck: URL-Pfad.
- **AK6 — 404 extern:** Gegeben Status 404 ohne Referer oder mit fremdem Referer, dann keine Meldung.
- **AK7 — 403:** Gegeben Status 403 und ein angemeldeter Nutzer, dann Meldung (ohne Nutzerdaten, nur „angemeldet“). Ohne Anmeldung keine Meldung.

### Drosselung & Freigabe
- **AK8 — Sperre:** Gegeben ein Fingerabdruck wurde gemeldet, dann wird er für `ALERTS_SPERRE_STUNDEN` (Standard 24) nicht erneut verschickt. Wiederholungen werden gezählt.
- **AK9 — Sammelmeldung:** Gegeben die Sperre ist abgelaufen und der Fehler tritt erneut auf, dann enthält die neue Meldung „X× seit letzter Meldung“.
- **AK10 — Reset einzeln:** `php artisan alerts:reset {fingerprint}` hebt die Sperre und den Zähler dieses Fingerabdrucks auf; das nächste Auftreten wird sofort gemeldet. Unbekannter Fingerabdruck → Hinweis, Exit-Code 1.
- **AK11 — Reset alle:** `php artisan alerts:reset --all` hebt alle Sperren auf.
- **AK12 — Freigabe-Link:** Jede Meldung enthält einen signierten, zeitlich begrenzten Link (Standard 7 Tage). GET zeigt eine Bestätigungsseite und gibt **nichts** frei. Erst POST mit CSRF-Token gibt frei. Ungültige oder abgelaufene Signatur → 403. Die Route ist gedrosselt (`throttle`).

### Versand
- **AK13 — Kanäle:** `ALERTS_CHANNELS` (kommagetrennt, `mail`, `telegram`) bestimmt die aktiven Kanäle. Mail an `ALERTS_MAIL_TO` (kommagetrennt), optional über `ALERTS_MAILER`. Telegram an `ALERTS_TELEGRAM_CHAT_ID` mit `ALERTS_TELEGRAM_BOT_TOKEN`. Kanal ohne Konfiguration wird übersprungen.
- **AK14 — Inhalt:** Projektname (`APP_NAME`), Umgebung, URL ohne Query-String, Quelle (Port/Exception/Log/404/403), Titel, Fingerabdruck, Zeitpunkt, Anzahl Wiederholungen, Freigabe-Link. Ort (Datei:Zeile) immer. Fehlertext pro Kanal schaltbar: `ALERTS_MAIL_FEHLERTEXT` (Standard `true`), `ALERTS_TELEGRAM_FEHLERTEXT` (Standard `false`). Keine Secrets, keine Nutzerdaten.
- **AK15 — Synchron:** Versand erfolgt synchron, Telegram mit Timeout ≤ 5 s.
- **AK16 — Seite bleibt heil:** Schlägt ein Kanal fehl, wird das auf `warning` geloggt (Bot-Token nie im Log), der andere Kanal wird trotzdem bedient, die Sperre bleibt gesetzt, die Anfrage verhält sich wie ohne Meldung.
- **AK17 — Keine Schleife:** Fehler innerhalb des Alert-Packages lösen keine neue Meldung aus (Wiedereintritts-Schutz).
- **AK18 — Abschaltbar:** `ALERTS_ENABLED=false` → keine Erfassung, kein Versand.
- **AK19 — Vorlagen:** Mail- und Telegram-Vorlage per `vendor:publish --tag=alerts-views` überschreibbar. Bestätigungsseite ohne externe Assets (DSGVO).

## Randfälle
- Cache nicht erreichbar → Meldung wird ohne Sperre verschickt, Fehler auf `warning` geloggt.
- Datenbank ausgefallen → kein Einfluss, da nur Cache genutzt wird.
- `abort(500)` ohne Exception ist eine `HttpException` und wird nach AK3 nicht gemeldet.
- Parallele Anfragen mit demselben Fehler → Cache-Lock sorgt für genau eine Meldung.
- Exception wird zusätzlich auf `critical` geloggt → nur eine Meldung (gleicher Request, gleicher Fingerabdruck).

## Nicht-Ziele
- Weitere Kanäle (Slack, Teams) — durch Notifications später ohne Umbau ergänzbar.
- Dashboard / Liste aktiver Sperren.
- Tägliche Zusammenfassung für 404.
- Rückbau consentlaw-client (eigenes Feature dort, siehe Plan T7).
