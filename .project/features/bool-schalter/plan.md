# Plan: bool-schalter (Bugfix, Dashboard #91)

Branch: `fix/bool-schalter` (Paket alerts) · Spec: `../grundgeruest/spec.md`, AK18 (ergänzt 2026-10-31, selbst freigegeben in der Nachtschicht)

Fehlerbild: `src/Config/alerts.php` liest `env('ALERTS_ENABLED', true)` ohne Cast. Erfasser (`LogErfasser`, `StatusErfasser`, `ExceptionErfasser`) prüfen `! config('alerts.enabled')`, der Versand (`MeldeAlert`) `filter_var(…, FILTER_VALIDATE_BOOL)`. Bei `ALERTS_ENABLED=off` erfassen die Erfasser, der Versand nicht. Gleiches Muster bei den übrigen Bool-Schaltern.

## Aufgaben
- [ ] T1 (AK18) test-writer: Regressionstest — Tests: `tests/Feature/BoolSchalterTest.php`
- [ ] T2 (AK18) [pro] Bool-Schalter in `src/Config/alerts.php` einmal per `filter_var(…, FILTER_VALIDATE_BOOL)` casten; README-Zeile zu `ALERTS_ENABLED` nennt die erlaubten Werte — Tests: `tests/Feature/BoolSchalterTest.php`
- [ ] T3 NACHWEIS (Moderator)

## Stand
Plan angelegt.
