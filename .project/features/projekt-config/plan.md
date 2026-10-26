# Plan: projekt-config

Spec: [spec.md](spec.md) · Branch: `feature/projekt-config`

## Aufgaben
- [x] T1 (AK1–AK6) test-writer: Tests, rot nachweisen
- [x] T2 (AK1, AK2) [pro] Config rekursiv zusammenführen (Listen ersetzen) statt `mergeConfigFrom` — Tests: tests/Feature/ProjektConfigTest.php
- [x] T3 (AK3–AK7) [pro] Kanäle je Quelle in `NotificationVersand`, Config-Schlüssel `kanaele`, README, CHANGELOG — Tests: tests/Feature/KanaeleJeQuelleTest.php
- [x] T4 GATE + NACHWEIS (Moderator): Paket-Suite; danach it-hilbert.com: eigene `config/alerts.php` (404-Muster, Kanäle), Env-Eintrag `ALERTS_HTTP_404_IGNORIEREN` aus `.env.example`/`deploy.md` entfernen, echter Lauf

## Stand
Spec vom User freigegeben (2026-10-26); Kanäle it-hilbert.com: 404 und 403 nur Mail, Rest Mail + Telegram. T1 face54e (53 Tests, 33 rot). T2 [pro]: GATE GRÜN (14 passed, Pint ok über Host); `mische()` rekursiv, Listen ersetzt. Annahmen des Coders übernommen: zweites Zusammenführen in `boot()` (Testbench setzt Config erst nach `register()`, idempotent), kein Abbruch bei gecachter Config (ergänzt nur neue Paket-Schlüssel). T3 [pro]: HANDOFF (Gegenproben `exception` mit gleichem Fingerabdruck → test-writer 2794c43), Zusatz „Kanalliste als String“ ohne AK zurückgebaut (kein Array = nicht gesetzt); GATE GRÜN (39 passed, Pint ok). Paket-Suite 172 grün. T4: it-hilbert.com `config/alerts.php` (nur 404-Muster und Kanäle), `config:show` zeigt zusammengeführte Abschnitte vollständig; echter Lauf mit Mail + Telegram (Attrappe): 33 gemeldete 404 + Gegenprobe → 2 Mails, kein Telegram-Versuch; `Log::critical` → Mail + Telegram-Versuch. Fertig.
