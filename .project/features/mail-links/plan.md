# Plan: mail-links

Spec: [spec.md](spec.md) · Branch: `fix/mail-links`

## Aufgaben
- [x] T1 (AK1–AK4) test-writer: Regressionstest, rot nachweisen
- [ ] T2 (AK1–AK4) [pro] Mail-Vorlage: URL, Referer und Freigabe-Adresse als Link, nur http(s) — Tests: tests/Feature/MailLinksTest.php
- [ ] T3 GATE + NACHWEIS (Moderator): ganze Suite, gerenderte Mail ansehen; danach `composer update ithilbert/alerts` in it-hilbert.com

## Stand
Spec freigegeben (User 2026-10-23). T1: 12 Tests, 7 rot (fehlender Link); 5 grün begründet („bleibt so“: „Meldung freigeben“, nur http(s), andere Details Text). Hooks: „prüft zusätzlich …/alerts“ im GATE-Log von it-hilbert.com. Nächster Schritt: T2.
