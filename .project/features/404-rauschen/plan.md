# Plan: 404-rauschen

Spec: [spec.md](spec.md) · Branch: `feature/404-rauschen`

## Aufgaben
- [ ] T1 (AK1–AK5) test-writer: Tests, rot nachweisen
- [ ] T2 (AK1–AK6) [pro] `StatusErfasser::nichtGefunden()`: Selbstverweis und ignorierte Pfade; Config-Schlüssel `erfassung.http_404_ignorieren` mit Env-Liste; README, CHANGELOG — Tests: tests/Feature/Http404RauschenTest.php
- [ ] T3 GATE + NACHWEIS (Moderator): ganze Suite des Pakets; danach in it-hilbert.com `composer update ithilbert/alerts` und `ALERTS_HTTP_404_IGNORIEREN`

## Stand
Spec geschrieben (2026-10-26), vom User beauftragt.
