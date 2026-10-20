# Plan: grundgeruest

Spec: [spec.md](spec.md) · Branch: `feature/grundgeruest`

## Hinweise
- Aufbau wie consentlaw-client: `composer.json` (`ithilbert/alerts`, PHP ^8.5, illuminate ^11 || ^12), Testbench, `extra.laravel.providers`.
- Schichten:
  - Domain: `Alert` (final readonly), `Fingerabdruck` (Value Object), Enums `AlertQuelle`, `Schweregrad`.
  - Application: Port `Contracts/AlertReporter` (öffentlicher Vertrag), Port `Drosselung`, UseCases `MeldeAlert`, `GibAlertFrei`.
  - Infrastructure: `CacheDrosselung` (Cache-Lock + Index-Schlüssel für `--all`), `TelegramKanal` (HTTP-Client, eigene Exception `TelegramVersandFehlgeschlagen`), Notification `AlertNotification` (Mail + Telegram).
  - Eingänge: `reportable()`-Callback über `callAfterResolving(ExceptionHandler::class)`, Listener auf `MessageLogged`, Listener auf `Illuminate\Foundation\Http\Events\RequestHandled` für 404/403 (keine Middleware: 404 ohne passende Route entsteht vor der Gruppen-Middleware).
  - HTTP: Freigabe-Controller (dünn), signierte Routen GET (Bestätigung) + POST (Freigabe), Blade-Vorlage ohne externe Assets.
- Alle Einstellungen in `src/Config/alerts.php` (Schalter, Kanäle, Fehlertext pro Kanal, Telegram-Timeouts, Sperrdauer, Erfassung je Quelle, Freigabe-Link). Code liest ausschließlich über `config('alerts.…')`.
- Wiedereintritts-Schutz für AK17 im UseCase `MeldeAlert`.
- GitHub-Repo: https://github.com/ITHilbert/alerts (privat, angelegt 2026-10-20).

## Aufgaben
- [x] T0 Gerüst [junior]: Verzeichnis, `composer.json`, ServiceProvider, Config `alerts.php`, `phpunit.xml`, Testbench lauffähig.
- [x] T1 TESTS (Moderator): AK1–AK19, Rot-Nachweis (61 Tests, 51 rot; grün nur Config-Standardwerte und Negativfälle).
- [x] T2 Kern [senior]: Domain, Port `AlertReporter`, UseCase `MeldeAlert`, `CacheDrosselung`, Mail-Kanal (AK1, AK8, AK9, AK13–AK18).
- [x] T3 Telegram [senior]: `TelegramKanal`, Exception-Übersetzung, Timeout (AK13–AK16).
- [x] T4 Eingänge [senior]: Exception-Callback, Log-Listener, 404/403-Listener (`RequestHandled`) (AK2–AK7).
- [x] T5 Freigabe [senior]: `alerts:reset`, signierte Routen, Bestätigungsseite, Vorlagen veröffentlichbar (AK10–AK12, AK19).
- [x] T6 Einbindung: Repo (privat), Submodule in `packages_dev`, VCS-Repository in Root-`composer.json`, README.
- [ ] T7 Folge-Feature consentlaw-client: `require ithilbert/alerts`, Listener auf eigene Events ruft `AlertReporter`; Mail-Listener, Vorlage, Sperre, Config-Block `alerts` entfernen.
- [x] NACHWEIS · REVIEW · Doku-Writer

## Stand
Grundgerüst fertig und gepusht (67/67 Tests grün, Pint sauber, README + CHANGELOG). Offen: T7 (consentlaw-client).

## Entscheidungen (Moderator)
- Testbasis Testbench (package-lokales `composer install`), nicht wie consentlaw-client eine handgebaute App — Exception-Handler, HTTP-Kernel und Signatur-Middleware müssen echt laufen.
- 404/403 über `RequestHandled` statt Middleware; Config-Schlüssel `erfassung.middleware_group` entfernt.
- T2–T5 in einem Senior-Lauf.
- Review 1: `LaravelAktuelleAnfrage` verzweigte auf `runningUnitTests()` (Test-Weiche im Produktivcode) → neuer Test `test_exception_ausserhalb_einer_anfrage_nennt_keine_url`; Lösung: `scoped` `AnfrageHalter`, gefüllt über Event `Routing`, geleert nach `RequestHandled`.
- Review 1: Index für `--all` jetzt unter `Cache::lock`.
- Review 2 (Hinweis Doku-Writer): Spec-Randfall „Exception zusätzlich auf critical geloggt“ war ungetestet → Tests ergänzt. Log-Fingerabdruck: gültiger `alert_key` → Exception im Kontext (`Fingerabdruck::ausException`, einzige Ableitungsstelle) → Text mit normalisierten Ziffern. Ungültiger `alert_key` → Warnung + Fallback.
- APP_KEY für Tests in `TestCase::defineEnvironment`, nicht in `phpunit.xml`.
