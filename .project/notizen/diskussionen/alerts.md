# Diskussion: Alert-Package `ithilbert/alerts`

## 1. Status & Kontext
Aufbau eines zentralen Packages für Störungsmeldungen (Mail + Telegram), das in jedem Projekt installiert ist.
**Aktueller Status:** `[Beschlossen]`. Spec und Plan: `.project/features/grundgeruest/`.
Stand: 2026-10-20

## 2. Ausgangslage
Jedes Package/Projekt baut bisher eigene Fehlermeldungen (Beispiel: consentlaw-client, Feature `stoerungs-meldung` mit eigenem Mail-Listener, eigener 24-h-Sperre und Config-Block `alerts`). Ziel: ein Package mit einheitlichen Vorlagen und Kanälen, einer Sperre gegen Nachrichtenflut und einer Freigabe nach Behebung. Fach-Packages melden nur noch, sie versenden nicht selbst.

## 3. Evaluierte Ansätze

### Eingang der Meldungen
- **Variante A: Fach-Packages lösen eigene Events aus, Alerts hört auf jede Event-Klasse**
  - **Pro:** Fach-Packages bleiben fachlich.
  - **Contra:** Alerts müsste jede Event-Klasse jedes Packages kennen → Kopplung nur umgedreht. **Verworfen.**
- **Variante B: Gemeinsames Interface in eigenem Contracts-Package**
  - **Pro:** Packages bleiben unabhängig, auch wenn Alerts fehlt.
  - **Contra:** Unnötig, da Alerts Basis-Package in jedem Projekt ist. **Verworfen.**
- **Variante C: Nur Log-Kanal (`Log::critical()` / Exceptions)**
  - **Pro:** Packages brauchen keinerlei Abhängigkeit.
  - **Contra:** Fingerabdruck wird aus der Meldung gebildet. Wechselnde Inhalte (URL, Zeit, Werte) erzeugen jedes Mal einen neuen Fingerabdruck → Sperre und Reset greifen nicht.
- **Variante D: Log-Kanal als Auffangnetz + Port `AlertReporter` für ausdrückliche Meldungen** ✅
  - **Pro:** Unbehandelte Fehler werden ohne Zutun erfasst; Packages geben über den Port einen festen Schlüssel mit (z.B. `consentlaw.kein-fallback`) → Sperre und Reset funktionieren verlässlich.
  - **Contra:** Packages mit ausdrücklichen Meldungen brauchen `require ithilbert/alerts` (unkritisch, Basis-Package).

### 404 / 403
- **a) Nur 404 mit Referer der eigenen Domain (kaputter interner Link)** ✅
- **b) Schwellwert (z.B. 20× dieselbe URL/Stunde)** — nicht gewählt.
- **c) Tägliche Zusammenfassung per Mail** — nicht gewählt.
- Jede 404 / 403 einzeln melden — **verworfen** (Bots fragen täglich `/wp-admin`, `/.env` usw. ab → Nachrichtenflut, echte Fehler gehen unter).

## 4. Offene Fragen & Abstimmungen
1. **Pfadkonvention** dieser Mitschrift (`.project/notizen/diskussionen/` vs. `.project/diskussionen/` im Root) — zurückgestellt.

## 5. Beschlüsse
1. **Basis-Package:** `ithilbert/alerts` ist in jedem Projekt installiert. Andere Packages dürfen es per `require` einbinden. Kein Contracts-Package.
2. **Eingang (Variante D):**
   - Log-Kanal als Auffangnetz für unbehandelte Fehler.
   - Port `AlertReporter` (per Dependency Injection, keine Facade, kein `new`) für ausdrückliche Meldungen mit festem Schlüssel.
3. **Erfassung:**
   - Unbehandelte Exceptions (500er) direkt über einen `reportable()`-Callback im Exception-Handler — nicht über den Log-Kanal (Laravel loggt Exceptions nur auf `error`).
   - Log-Kanal ab `critical` für `Log::critical()`, `alert`, `emergency`.
   - 404 nur bei Referer der eigenen Domain (kaputter interner Link).
   - 403 nur, wenn ein angemeldeter Nutzer betroffen ist.
4. **Kanäle:** Mail und Telegram von Anfang an, über Laravel Notifications. Telegram über eigenen Adapter mit Laravels HTTP-Client. Bot-Token, Chat-ID und aktive Kanäle aus `.env`.
   - Versand **synchron** mit kurzem Timeout (nicht über die Queue — fällt der Worker aus, käme sonst nichts an).
   - Die Sperre greift auch bei gescheitertem Versand; der Fehlschlag wird auf `warning` geloggt (nie `critical`, sonst Endlosschleife).
   - Fehlertext pro Kanal per Config/`.env` zuschaltbar (`ALERTS_MAIL_FEHLERTEXT`, Standard `true`; `ALERTS_TELEGRAM_FEHLERTEXT`, Standard `false` — Privacy by Default, Telegram ist Drittanbieter außerhalb der EU).
   - Inhalt: Projektname (`APP_NAME`), Umgebung, URL ohne Query-String, Quelle, Fingerabdruck, Zeitpunkt, Anzahl unterdrückter Wiederholungen. Keine personenbezogenen Daten, keine Secrets.
5. **Sperre gegen Nachrichtenflut:**
   - Pro Fehler ein Fingerabdruck (fester Schlüssel aus dem Port; bei Exceptions Klasse + Datei + Zeile, ohne Meldung — Meldungen enthalten wechselnde Werte), gedrosselt über Cache-Lock.
   - Standard-Sperrdauer 24 h, per `.env` einstellbar.
   - Nach Ablauf der Sperre eine Sammelmeldung („X× seit letzter Meldung“).
6. **Freigabe nach Behebung:**
   - `php artisan alerts:reset {fingerprint}` und `php artisan alerts:reset --all`.
   - Zusätzlich signierter, zeitlich begrenzter Freigabe-Link in Mail und Telegram.
   - Der Link öffnet eine Bestätigungsseite; freigegeben wird erst per POST-Button (Schutz vor Mail-Scannern, die Links automatisch aufrufen).
   - Fingerabdruck steht sichtbar in jeder Nachricht.
7. **Rückbau consentlaw-client** (eigener Schritt nach Fertigstellung):
   - Mail-Listener, Mail-Vorlage, eigene 24-h-Sperre und Config-Block `alerts` entfallen.
   - Die Events bleiben; ein Listener im Package ruft den Port auf.
