# Plan: bool-schalter (Bugfix, Dashboard #91)

Branch: `fix/bool-schalter` (Paket alerts) · Spec: `../grundgeruest/spec.md`, AK18 (ergänzt 2026-10-31, selbst freigegeben in der Nachtschicht)

Fehlerbild: `src/Config/alerts.php` liest `env('ALERTS_ENABLED', true)` ohne Cast. Erfasser (`LogErfasser`, `StatusErfasser`, `ExceptionErfasser`) prüfen `! config('alerts.enabled')`, der Versand (`MeldeAlert`) `filter_var(…, FILTER_VALIDATE_BOOL)`. Bei `ALERTS_ENABLED=off` erfassen die Erfasser, der Versand nicht. Gleiches Muster bei den übrigen Bool-Schaltern.

## Aufgaben
- [x] T1 (AK18) test-writer (5e8fd4f, 8 rot: Erfasser werten „off“/„0“ als an): Regressionstest — Tests: `tests/Feature/BoolSchalterTest.php`
- [x] T2 (AK18) [pro] Bool-Schalter in `src/Config/alerts.php` einmal per `filter_var(…, FILTER_VALIDATE_BOOL)` casten; README-Zeile zu `ALERTS_ENABLED` nennt die erlaubten Werte — Tests: `tests/Feature/BoolSchalterTest.php`
- [x] T3 NACHWEIS (Moderator)
- [ ] T4 REVIEW (reviewer, Review-Platz) → `pruefung.md`

## Stand
T1–T3 erledigt. NACHWEIS: GATE GRÜN (coder-pro, 07:32, 47 Tests der Aufgabe); vom Moderator erneut: BoolSchalterTest 47 grün, Paket-Suite 219 grün, Pint `src` grün, Tests unverändert, AK18 in 15 Testfällen; echte Config-Wertung `ALERTS_ENABLED=off|0|no` → `false`, `true` → `true`. Der Host lädt alerts aus `vendor/ithilbert/alerts`, ein Host-Lauf geht erst nach Push und `composer update`. Annahmen: `MeldeAlert` castet nicht mehr selbst (eine Wertung in der Config); leerer Wert = `false` wie vorher. Offen (kein AK betroffen): redundanter `filter_var` in `AlertNotification.php:50`. Weiter: T4 REVIEW (wartet auf Review-Platz unter ~/projekte/.worktrees).
