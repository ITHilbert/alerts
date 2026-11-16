# Plan: empfaenger-je-quelle

Spec: [spec.md](spec.md) · Branch: `feature/empfaenger-je-quelle` (Paket-Repo)

## Aufgaben
- [x] T1 (AK1–AK5) test-writer: neue Tests, vorhandene Standard-Tests der Sammelwerte anpassen — Tests: tests/Feature/EmpfaengerJeQuelleTest.php, tests/Feature/ScannerRauschenTest.php
- [x] T2 (AK1–AK6) [pro] `NotificationVersand` (Empfänger je Quelle), Config `mail.to_je_quelle` und `kanaele` per Env, Sammel-Standard `ab` 1 / 600 s, README, CHANGELOG — Tests: tests/Feature/EmpfaengerJeQuelleTest.php tests/Feature/ScannerRauschenTest.php

Bestehende Tests: tests/Feature/KanaeleJeQuelleTest.php tests/Feature/VersandTest.php tests/Feature/KonfigurationTest.php tests/Feature/ProjektConfigTest.php

## Stand
T1 Tests 6ea6f5a (18 neue, 15 rot vor dem Code). T2 [pro]: Paket-Suite 497 grün, Pint ok (PHPStan im Paket nicht vorhanden). Coder-HANDOFF (HttpStatusErfassungTest rot mit `ab` 1) → AK6 „gleicher Pfad zählt einmal“ erprobt, wieder zurückgenommen (Randfall 3 der Scanner-Rauschen-Spec bleibt); Test setzt `ab` 5 selbst. Offener Punkt a2089fac: AK-fremd/entschieden (Spec „Gleicher Pfad“). Abweichung a2089fac: keine. Echtlauf: entfällt (Paket ohne UI; Verhalten über Feature-Tests mit echtem Erfassungsweg und Mail-Fake belegt). Annahme im Code: keine.
Spec geschrieben, selbst freigegeben. Review: entfällt (kein neuer Endpunkt, keine Migration, kein Sicherheitsbezug, < 50 Zeilen Produktivcode).
