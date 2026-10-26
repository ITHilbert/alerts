# Plan: 404-rauschen

Spec: [spec.md](spec.md) · Branch: `feature/404-rauschen`

## Aufgaben
- [x] T1 (AK1–AK5) test-writer: Tests, rot nachweisen
- [x] T2 (AK1–AK6) [pro] `StatusErfasser::nichtGefunden()`: Selbstverweis und ignorierte Pfade; Config-Schlüssel `erfassung.http_404_ignorieren` mit Env-Liste; README, CHANGELOG — Tests: tests/Feature/Http404RauschenTest.php
- [x] T3 GATE + NACHWEIS (Moderator): ganze Suite des Pakets; danach in it-hilbert.com `composer update ithilbert/alerts` und `ALERTS_HTTP_404_IGNORIEREN`

## Stand
T1 Tests 52ef8b0 (37 Tests, 28 rot wegen fehlenden Verhaltens). T2 [pro]: GATE GRÜN (37 passed); Selbstverweis über normalisierten Pfad, Muster per `Str::is(..., ignoreCase)` auf `decodedPath()`. Paket-Suite 119 grün, Pint ok. T3: it-hilbert.com auf ddd95f9, echter Lauf mit den 33 gemeldeten 404 + Gegenprobe (`php artisan serve`, Log-Mailer): mit `ALERTS_HTTP_404_IGNORIEREN="entwickler/*,manifest.json"` 2 Mails (Foto, Gegenprobe), ohne Liste 23 (21 `/entwickler`, Foto, Gegenprobe; die 11 Selbstverweise nie). Fertig.
