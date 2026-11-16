# Plan: empfaenger-je-quelle

Spec: [spec.md](spec.md) · Branch: `feature/empfaenger-je-quelle` (Paket-Repo)

## Aufgaben
- [ ] T1 (AK1–AK5) test-writer: neue Tests, vorhandene Standard-Tests der Sammelwerte anpassen — Tests: tests/Feature/EmpfaengerJeQuelleTest.php, tests/Feature/ScannerRauschenTest.php
- [ ] T2 (AK1–AK6) [pro] `NotificationVersand` (Empfänger je Quelle), Config `mail.to_je_quelle` und `kanaele` per Env, Sammel-Standard `ab` 1 / 600 s, README, CHANGELOG — Tests: tests/Feature/EmpfaengerJeQuelleTest.php tests/Feature/ScannerRauschenTest.php

Bestehende Tests: tests/Feature/KanaeleJeQuelleTest.php tests/Feature/VersandTest.php tests/Feature/KonfigurationTest.php tests/Feature/ProjektConfigTest.php

## Stand
Spec geschrieben, selbst freigegeben. Review: entfällt (kein neuer Endpunkt, keine Migration, kein Sicherheitsbezug, < 50 Zeilen Produktivcode).
