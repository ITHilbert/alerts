# Spec: 404-rauschen

## Ausgangslage
it-hilbert.com, 23.–26.10.2026: 33 von 34 Alert-Mails waren 404 von Bots, die den Referer selbst auf eine URL der eigenen Domain setzen. Dadurch kommen sie durch die Prüfung aus AK5/AK6 (`grundgeruest`), und jede geratene URL erzeugt einen eigenen Fingerabdruck und damit eine eigene Mail. Der einzige echte Fehler im selben Zeitraum (ViewException) ging im Rauschen fast unter. Analyse: `it-hilbert.com/.project/notizen/404-meldungen.md`.
- 11 Mails: Der Referer ist dieselbe URL wie die angefragte (gleich oder nur in Groß- und Kleinschreibung bzw. Schema anders, z. B. Referer `/access/Access-Programmierer-Brakel` → 301 → angefragt `/access/access-programmierer-brakel`). Eine Seite, die selbst 404 liefert, kann nicht auf sich verlinkt haben.
- 22 Mails: alte Pfade unter `/entwickler/…`, die es bewusst nicht mehr gibt (it-hilbert.com, Entscheidung 0012).

User 2026-10-26: beide Regeln ins Paket („kannst die Anpassungen direkt vornehmen“). Das hebt das Nicht-Ziel „Filter für 404 bei Umleitungs-Referer – vorerst nicht“ aus `mail-links` (User 2026-10-23) auf.
Ergänzt AK5 der Spec `grundgeruest`.

## Akzeptanzkriterien
- **AK1 — Referer gleich angefragter URL:** Gegeben eine Antwort mit Status 404 und ein Referer der eigenen Domain — Wenn der Pfad des Referers dem angefragten Pfad entspricht — Dann wird keine Meldung verschickt. Als gleich gelten Pfade, die sich nur in Groß- und Kleinschreibung, in der Prozent-Kodierung (`%C3%9F` = `ß`), in einem abschließenden `/`, im Schema (`http`/`https`) oder im Query-String unterscheiden.
- **AK2 — Gegenprobe zu AK1:** Gegeben Status 404 und ein Referer der eigenen Domain mit einem anderen Pfad als dem angefragten — Dann wird wie bisher gemeldet (AK5 `grundgeruest`).
- **AK3 — Ignorierte Pfade:** Gegeben die Konfiguration `alerts.erfassung.http_404_ignorieren` enthält Muster (z. B. `entwickler/*`, `manifest.json`) — Wenn eine 404-Antwort mit internem Referer einen Pfad hat, der einem Muster entspricht — Dann wird keine Meldung verschickt. Muster wie bei `Request::is()`: Pfad ohne führenden `/`, `*` als Platzhalter, Groß- und Kleinschreibung egal.
- **AK4 — Gegenprobe zu AK3:** Gegeben Muster sind gesetzt — Wenn der Pfad keinem Muster entspricht — Dann wird wie bisher gemeldet.
- **AK5 — Konfiguration:** Der Standard für `alerts.erfassung.http_404_ignorieren` ist eine leere Liste (Verhalten unverändert). Er lässt sich über die Umgebungsvariable `ALERTS_HTTP_404_IGNORIEREN` als kommagetrennte Liste setzen (Leerzeichen um die Einträge werden entfernt, leere Einträge ignoriert) oder in der veröffentlichten `config/alerts.php` als Array.
- **AK6 — Doku:** README nennt die beiden Regeln in der Erfassungs-Tabelle (404) und `ALERTS_HTTP_404_IGNORIEREN` in der Konfigurations-Tabelle; CHANGELOG hat einen Eintrag unter „Unreleased“.

## Randfälle
- `ALERTS_HTTP_404_IGNORIEREN` nicht gesetzt und leer gesetzt (`''`): beide ergeben die leere Liste, es wird wie bisher gemeldet.
- Muster `*` allein: ignoriert alle 404 mit internem Referer (gewollt, wenn ein Projekt das so konfiguriert).
- 404 ohne Referer oder mit fremdem Referer: bleibt ohne Meldung (AK6 `grundgeruest`), unabhängig von den Mustern.
- 403-Erfassung bleibt von beiden Regeln unberührt.

## Nicht-Ziele
- Keine Erkennung von Bots über den User-Agent.
- Keine Tabelle oder Datenbank für bekannte URLs.
- Fingerabdruck und Drosselung bleiben unverändert.

## Entscheidungsspielraum
- Keine neuen Composer-Pakete.
