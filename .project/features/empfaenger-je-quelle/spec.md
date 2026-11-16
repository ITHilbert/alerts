# Spec: empfaenger-je-quelle

Task #1295 (Auftrag Joerg #1292, 2026-11-16). Risiko: normal. Selbst freigegeben (automode 2026-11-16-1008).

## Ausgangslage
- `alerts.mail.to` (`ALERTS_MAIL_TO`) gilt für alle Quellen. 404-Meldungen sollen an `404@it-hilbert.com` gehen (der Homeserver-Admin prüft dieses Postfach täglich); die Adresse steht nicht im Code, sondern in der `.env`.
- `alerts.kanaele` ist ein festes `[]` und lässt sich nicht per `.env` setzen.
- Langsame Scanner (ein Abruf alle 30–40 s, vba-programmierer.com 11.11.2026: 11 Einzelmails) unterlaufen `http_404_sammeln` (`ab` 5, Fenster 60 s).

## Akzeptanzkriterien
- **AK1 — Empfänger je Quelle:** Gegeben `alerts.mail.to_je_quelle.404` ist `404@it-hilbert.com` (aus `ALERTS_MAIL_TO_404`) und `alerts.mail.to` ist `admin@example.com` — Wenn eine 404-Meldung versendet wird — Dann geht die Mail nur an `404@it-hilbert.com`. Eine Exception-Meldung geht weiterhin an `admin@example.com`.
- **AK2 — Alle Quellen, mehrere Adressen:** Das Muster gilt für alle fünf Quellen (`exception`, `log`, `meldung`, `404`, `403`) mit den Schlüsseln `ALERTS_MAIL_TO_EXCEPTION`, `ALERTS_MAIL_TO_LOG`, `ALERTS_MAIL_TO_MELDUNG`, `ALERTS_MAIL_TO_404`, `ALERTS_MAIL_TO_403`. Mehrere Adressen werden wie bei `ALERTS_MAIL_TO` kommagetrennt angegeben.
- **AK3 — Ohne Schlüssel wie bisher:** Gegeben der Schlüssel der Quelle ist nicht gesetzt oder leer (auch nur Leerzeichen oder Kommas) — Dann gilt `alerts.mail.to` wie bisher. Ohne einen der neuen Schlüssel verhält sich das Paket unverändert.
- **AK4 — Kanäle je Quelle per .env:** Gegeben `ALERTS_KANAELE_404=mail` — Dann ist `alerts.kanaele.404` gleich `['mail']`, und 404-Meldungen gehen nicht an Telegram. Gleiches Muster für alle fünf Quellen (`ALERTS_KANAELE_EXCEPTION`, `_LOG`, `_MELDUNG`, `_404`, `_403`), Wert kommagetrennt. Ist der Schlüssel nicht gesetzt, fehlt der Eintrag in `alerts.kanaele` (= alle aktiven Kanäle, wie bisher). Ein gesetzter, aber leerer Wert (`ALERTS_KANAELE_404=`) ergibt ebenfalls keinen Eintrag; „kein Versand“ bleibt der leeren Liste in einer veröffentlichten `config/alerts.php` vorbehalten.
- **AK5 — Langsame Scanner:** Gegeben die Standard-Einstellung von `http_404_sammeln` (`ab` 1, `fenster_sekunden` 600) — Wenn eine IP 11 meldepflichtige 404 mit verschiedenen Pfaden im Abstand von 35 s abruft — Dann entstehen höchstens 1 Einzelmeldung und 1 Sammelmeldung „Häufung von 404 (eine Quelle)“.
- **AK6 — Doku:** README nennt `ALERTS_MAIL_TO_<QUELLE>`, `ALERTS_KANAELE_<QUELLE>` und die neuen Standardwerte von `ALERTS_HTTP_404_SAMMELN_AB`/`_FENSTER` in der Konfigurations-Tabelle bzw. im Abschnitt zur Erfassung; CHANGELOG hat Einträge unter „Unveröffentlicht“. Eine `.env.example` gibt es im Paket nicht.

## Randfälle
- Unbekannte Kanalnamen in `ALERTS_KANAELE_*` werden wie in der Config ignoriert und als Warnung geloggt (bestehendes Verhalten).
- Hat eine Quelle einen eigenen Empfänger, der Mail-Kanal ist aber für sie nicht erlaubt (`kanaele`), geht keine Mail raus.
- Ist `alerts.mail.to` leer, aber `to_je_quelle.404` gesetzt: 404-Meldungen gehen per Mail an die Quell-Adresse, andere Quellen versenden keine Mail.
- Die Config-Schlüssel mit `404`/`403` sind in PHP Integer-Schlüssel; der Zugriff liest über das Array (wie bei `kanaele`).

## Nicht-Ziele
- Keine Empfänger je Quelle für Telegram.
- Kein fest eingetragener Wert (kein `404@it-hilbert.com`) im Code; die Projekte setzen die `.env`-Werte beim nächsten Update.

## Entscheidungsspielraum / Entscheidungen
- **Sammel-Standard (Annahme):** AK „11 Abrufe im Abstand 35 s → höchstens 1 Einzel- und 1 Sammelmeldung“ verlangt `ab` = 1; das Fenster 600 s deckt 11 × 35 s = 385 s ab. Ein zweites, längeres Fenster wäre zusätzlicher Code für dasselbe Ergebnis, daher werden nur die Standardwerte geändert (`ab` 5 → 1, Fenster 60 → 600). Folge: Eine IP mit zwei verschiedenen meldepflichtigen 404 innerhalb von 10 Minuten löst die Sammelmeldung aus; Projekte stellen alte Werte per `ALERTS_HTTP_404_SAMMELN_AB`/`_FENSTER` ein. Umkehrbar über die Env-Werte.
- Die Standardwerte aus der früheren Spec `alerts-scanner-rauschen` (host `.project/features/`) werden damit ersetzt; die Tests dazu, die den Standard prüfen, werden angepasst.
- Keine neuen Composer-Pakete.
