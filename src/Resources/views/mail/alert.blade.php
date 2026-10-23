<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #1f2937; line-height: 1.5;">
    <h2 style="margin: 0 0 12px;">{{ $alert->vorfall->titel }}</h2>
    <p>Projekt: {{ $alert->projekt }}</p>
    <p>Umgebung: {{ $alert->umgebung }}</p>
    <p>Quelle: {{ $alert->vorfall->quelle->label() }}</p>
    <p>Fingerabdruck: {{ $alert->vorfall->fingerabdruck->wert }}</p>
    <p>Zeitpunkt: {{ $alert->zeitpunkt->format('d.m.Y H:i:s') }}</p>
    @if ($alert->vorfall->url)
        <p>URL: @include('alerts::mail.adresse', ['wert' => $alert->vorfall->url, 'verlinken' => true])</p>
    @endif
    @if ($alert->vorfall->ort)
        <p>Ort: {{ $alert->vorfall->ort }}</p>
    @endif
    @foreach ($alert->vorfall->details as $schluessel => $wert)
        <p style="white-space: pre-line;">{{ $schluessel }}: @include('alerts::mail.adresse', ['wert' => $wert, 'verlinken' => $schluessel === 'Referer'])</p>
    @endforeach
    @if ($mitFehlertext && $alert->vorfall->fehlertext)
        <p style="white-space: pre-wrap;">Fehlertext: {{ $alert->vorfall->fehlertext }}</p>
    @endif
    @if ($alert->wiederholungen > 0)
        <p><strong>{{ $alert->wiederholungen }}× seit letzter Meldung</strong></p>
    @endif
    <p>
        <a href="{{ $alert->freigabeLink }}">Meldung freigeben</a><br>
        @include('alerts::mail.adresse', ['wert' => $alert->freigabeLink, 'verlinken' => true])
    </p>
</div>
