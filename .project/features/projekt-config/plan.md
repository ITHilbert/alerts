# Plan: projekt-config

Spec: [spec.md](spec.md) · Branch: `feature/projekt-config`

## Aufgaben
- [ ] T1 (AK1–AK6) test-writer: Tests, rot nachweisen
- [ ] T2 (AK1, AK2) [pro] Config rekursiv zusammenführen (Listen ersetzen) statt `mergeConfigFrom` — Tests: tests/Feature/ProjektConfigTest.php
- [ ] T3 (AK3–AK7) [pro] Kanäle je Quelle in `NotificationVersand`, Config-Schlüssel `kanaele`, README, CHANGELOG — Tests: tests/Feature/KanaeleJeQuelleTest.php
- [ ] T4 GATE + NACHWEIS (Moderator): Paket-Suite; danach it-hilbert.com: eigene `config/alerts.php` (404-Muster, Kanäle), Env-Eintrag `ALERTS_HTTP_404_IGNORIEREN` aus `.env.example`/`deploy.md` entfernen, echter Lauf

## Stand
Spec vom User freigegeben (2026-10-26); Kanäle it-hilbert.com: 404 und 403 nur Mail, Rest Mail + Telegram.
