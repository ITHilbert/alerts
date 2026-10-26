# Changelog

Alle nennenswerten Änderungen am Package `ithilbert/alerts`.

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/).

## Unveröffentlicht

### Hinzugefügt

- Projekt-Config: Eine `config/alerts.php` im Projekt braucht nur die abweichenden Schlüssel, die Paket-Config wird rekursiv zusammengeführt. Listen werden ersetzt, nicht positionsweise gemischt.
- Kanäle je Quelle: `alerts.kanaele.<quelle>` (`exception`, `log`, `meldung`, `404`, `403`) mit Liste aus `mail`/`telegram`. Nicht gesetzt = alle aktiven Kanäle wie bisher, leere Liste = kein Versand. Unbekannte Kanalnamen werden ignoriert und als Warnung geloggt.
- Port `ITHilbert\Alerts\Contracts\AlertReporter` mit `melde(Meldung $meldung)` zur Auflösung per Dependency Injection.
- Value Object `ITHilbert\Alerts\Domain\Meldung` mit benannten Parametern `schluessel`, `titel`, `fehlertext`, `details`. Ungültige Schlüssel werfen `UngueltigerSchluessel`.
- Automatische Erfassung unbehandelter Exceptions über den Exception-Handler (`reportable`). Fingerabdruck aus Klasse, Datei und Zeile, ohne Meldungstext.
- Log-Erfassung ab Level `critical`, `alert` und `emergency`. Kontext-Schlüssel `alert_key` überschreibt den Fingerabdruck.
- Exception im Log-Kontext (`exception`) nutzt denselben Fingerabdruck wie die Exception-Erfassung. Dadurch entsteht nur eine Meldung.
- Log-Fingerabdruck ohne `alert_key` normalisiert Ziffernfolgen (`\d+` wird zu `#`), damit z.B. „Import 17“ und „Import 18“ eine Meldung ergeben.
- Erfassung von 404 nur bei Referer der eigenen Domain (`APP_URL`) und von 403 nur bei angemeldetem Nutzer.
- 404-Rauschen: Keine Meldung mehr, wenn der Referer-Pfad dem angefragten Pfad entspricht (Groß-/Kleinschreibung, Prozent-Kodierung, abschließender `/`, Schema, Query-String egal). Neue Einstellung `alerts.erfassung.http_404_ignorieren` (Env `ALERTS_HTTP_404_IGNORIEREN`, kommagetrennt, Muster wie `Request::is()`) für bewusst entfernte Pfade. Standard leer.
- Drosselung pro Fingerabdruck über Cache-Sperre (atomar per `add`), Standard 24 h, mit Zähler und Sammelmeldung „X× seit letzter Meldung“.
- Befehl `php artisan alerts:reset {fingerabdruck}` zur Freigabe einer Sperre. Mit `--all` werden alle Sperren aufgehoben.
- Signierter, zeitlich begrenzter Freigabe-Link (Standard 7 Tage) in jeder Nachricht. GET zeigt eine Bestätigungsseite, erst POST mit CSRF-Token gibt frei. Route gedrosselt (`throttle:6,1`).
- Kanal Mail über Laravel Notifications mit konfigurierbaren Empfängern und optionalem Mailer.
- Kanal Telegram über Adapter mit Laravels HTTP-Client, synchron, Timeout 5 s, Bot-Token nie im Log.
- Fehlertext je Kanal schaltbar (`ALERTS_MAIL_FEHLERTEXT` Standard `true`, `ALERTS_TELEGRAM_FEHLERTEXT` Standard `false`).
- Schalter `ALERTS_ENABLED` zum kompletten Abschalten von Erfassung und Versand.
- Veröffentlichbare Konfiguration (`alerts-config`) und Vorlagen (`alerts-views`). Die Freigabe-Seiten kommen ohne externe Assets aus.
- Testsuite auf Basis von Orchestra Testbench.
- Mail: URL, Detail `Referer` und die ausgeschriebene Freigabe-Adresse sind klickbare Links (nur `http://`/`https://`, sonst Text). Wer die Mail-Vorlage veröffentlicht hat, übernimmt das neue Partial `mail/adresse.blade.php`.

### Hinweise

- Fehler innerhalb des Packages lösen keine neue Meldung aus (Wiedereintritts-Schutz).
- Ungültiger `alert_key` im Log-Kontext wird als Warnung geloggt, danach wird auf den abgeleiteten Fingerabdruck zurückgefallen.
- Schlägt ein Kanal fehl, wird das auf `warning` geloggt. Der andere Kanal wird trotzdem bedient und die Sperre bleibt gesetzt.
