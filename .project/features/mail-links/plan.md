# Plan: mail-links

Spec: [spec.md](spec.md) · Branch: `fix/mail-links`

## Aufgaben
- [x] T1 (AK1–AK4) test-writer: Regressionstest, rot nachweisen
- [x] T2 (AK1–AK4) [pro] Mail-Vorlage: URL, Referer und Freigabe-Adresse als Link, nur http(s) — Tests: tests/Feature/MailLinksTest.php
- [x] T3 GATE + NACHWEIS (Moderator): ganze Suite, gerenderte Mail ansehen; danach `composer update ithilbert/alerts` in it-hilbert.com

## Stand
T1: 12 Tests, 7 rot; Nachtrag AK4 (bool-Referer bleibt ja/nein) +2 Tests rot. T2: coder-pro, GATE GRÜN im Paket (12, dann 14) – erster Lauf mit Zeile `PROJEKT:`, Schreibbereich „prüft zusätzlich“ belegt. Partial `mail/adresse.blade.php` verlinkt nur http(s) und setzt bool → ja/nein an einer Stelle. NACHWEIS: ganze Suite 82 grün. Fertig.
