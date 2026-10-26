# Spec: projekt-config

## Ausgangslage
Ein Projekt kann Einstellungen bisher nur über `.env` ändern oder die ganze Paket-Config veröffentlichen (`vendor:publish --tag=alerts-config`). Laravel führt mit `mergeConfigFrom` nur die oberste Ebene zusammen: Eine Projekt-`config/alerts.php`, die nur `erfassung.http_404_ignorieren` setzt, löscht damit `erfassung.exceptions`, `log`, `http_404` und `http_403`. Neue Paket-Schlüssel kämen in veröffentlichten Configs nie an.
Außerdem gehen alle Meldungen über alle aktiven Kanäle (`alerts.channels`). Ein Projekt kann nicht festlegen, dass z. B. 404 nur per Mail kommen und Exceptions zusätzlich per Telegram.

User 2026-10-26: „baue eine eigene config, da kommt bestimmt noch mehr … bei welchen Nachrichten soll es bei Telegram und Mail raus“.
Ergänzt die Konfiguration aus `grundgeruest` und `404-rauschen`.

## Akzeptanzkriterien
- **AK1 — Teil-Config im Projekt:** Gegeben das Projekt hat eine `config/alerts.php`, die nur einzelne Schlüssel setzt (z. B. nur `erfassung.http_404_ignorieren`) — Dann gelten diese Werte, und alle übrigen Schlüssel behalten den Paket-Standard, auch die im selben Abschnitt (z. B. `erfassung.exceptions`).
- **AK2 — Listen werden ersetzt:** Gegeben ein Wert im Projekt ist eine Liste (z. B. `erfassung.http_404_ignorieren` oder eine Kanalliste aus AK3) — Dann ersetzt sie die Liste des Pakets vollständig; Einträge werden nicht nach Position zusammengeführt.
- **AK3 — Kanäle je Quelle:** Gegeben `alerts.kanaele.<quelle>` ist eine Liste von Kanälen (`mail`, `telegram`) für eine der Quellen `exception`, `log`, `meldung`, `404`, `403` — Wenn ein Alert dieser Quelle verschickt wird — Dann geht er nur über die Kanäle dieser Liste, die zugleich in `alerts.channels` aktiv und eingerichtet sind.
- **AK4 — Standard unverändert:** Gegeben für eine Quelle ist in `alerts.kanaele` nichts gesetzt — Dann geht der Alert wie bisher über alle aktiven Kanäle aus `alerts.channels`.
- **AK5 — Leere Liste:** Gegeben `alerts.kanaele.<quelle>` ist eine leere Liste — Dann wird für diese Quelle über keinen Kanal versendet.
- **AK6 — Unbekannter Kanal:** Gegeben eine Kanalliste enthält einen unbekannten Namen (z. B. `sms`) — Dann wird er ignoriert, die übrigen Kanäle der Liste gelten, und es entsteht eine Log-Warnung (Level `warning`) mit dem Namen.
- **AK7 — Doku:** README beschreibt die Projekt-Config (nur abweichende Schlüssel eintragen, Listen ersetzen) mit einem Beispiel und `alerts.kanaele`; CHANGELOG-Eintrag unter „Unveröffentlicht“.

## Randfälle
- Groß- und Kleinschreibung sowie Leerzeichen in Kanalnamen (`" Mail "`) zählen nicht.
- Kanal in `alerts.kanaele` gesetzt, aber global nicht aktiv oder nicht eingerichtet (z. B. kein Bot-Token): kein Versand über ihn, keine Warnung (wie bisher).
- `php artisan config:cache`: Die zusammengeführte Config wird gecacht und gilt unverändert.
- Projekt ohne eigene `config/alerts.php`: Verhalten unverändert.
- Drosselung und Freigabe hängen weiter nur am Fingerabdruck, nicht am Kanal.

## Nicht-Ziele
- Keine Kanäle je Port-Schlüssel oder Log-Level (Struktur soll es später zulassen).
- Keine Ruhezeiten, keine Zusammenfassungen.
- Keine Änderung an Inhalt und Vorlagen der Nachrichten.

## Entscheidungsspielraum
- Keine neuen Composer-Pakete. Die bestehenden Env-Variablen bleiben gültig.
