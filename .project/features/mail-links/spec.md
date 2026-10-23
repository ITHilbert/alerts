# Spec: mail-links

## Ausgangslage
Alert-Mail aus it-hilbert.com (2026-10-23): Klickbar ist nur „Meldung freigeben“. Die darunter ausgeschriebene Freigabe-Adresse, die `URL` und der Detailwert `Referer` stehen als reiner Text in der Mail (`src/Resources/views/mail/alert.blade.php`); das Mail-Programm des Users verlinkt sie nicht selbst.
User 2026-10-23: „Link anpassung ja“ – URL, Referer und ausgeschriebene Freigabe-Adresse als Link.
Ergänzt AK14 (Inhalt) der Spec `grundgeruest`.

## Akzeptanzkriterien
- **AK1 — URL verlinkt:** Gegeben eine Meldung mit URL — Wenn die Mail erzeugt wird — Dann steht die URL im HTML-Teil als Link (`<a href="<url>">`), dessen Text die URL ist.
- **AK2 — Referer verlinkt:** Gegeben eine Meldung mit dem Detail `Referer`, dessen Wert mit `http://` oder `https://` beginnt — Wenn die Mail erzeugt wird — Dann steht der Referer als Link auf genau diesen Wert, Text ist der Wert.
- **AK3 — Freigabe-Adresse verlinkt:** Gegeben eine Meldung — Wenn die Mail erzeugt wird — Dann ist die unter „Meldung freigeben“ ausgeschriebene Freigabe-Adresse ebenfalls ein Link auf dieselbe Adresse; „Meldung freigeben“ bleibt ein Link.
- **AK4 — Nur http(s):** Gegeben ein Wert für URL oder `Referer`, der nicht mit `http://` oder `https://` beginnt (z. B. `javascript:…`) — Dann wird er nicht verlinkt, sondern wie bisher escaped als Text ausgegeben.

## Randfälle
- Sonderzeichen in der Adresse (`&`, `"`, `<`): im `href` und im Linktext escaped, die Signatur der Freigabe-Adresse bleibt gültig (Link führt auf dieselbe Adresse).
- Andere Details (außer `Referer`) bleiben Text, auch wenn ihr Wert eine Adresse ist.

## Nicht-Ziele
- Telegram-Vorlage bleibt unverändert (Telegram verlinkt selbst).
- Kein zusätzlicher Text-Teil der Mail.
- Filter für 404 bei Umleitungs-Referer (User 2026-10-23: vorerst nicht).
