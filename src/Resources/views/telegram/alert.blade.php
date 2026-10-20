{!! $alert->vorfall->titel !!}

Projekt: {!! $alert->projekt !!}
Umgebung: {!! $alert->umgebung !!}
Quelle: {!! $alert->vorfall->quelle->label() !!}
Fingerabdruck: {!! $alert->vorfall->fingerabdruck->wert !!}
Zeitpunkt: {!! $alert->zeitpunkt->format('d.m.Y H:i:s') !!}
@if ($alert->vorfall->url)
URL: {!! $alert->vorfall->url !!}
@endif
@if ($alert->vorfall->ort)
Ort: {!! $alert->vorfall->ort !!}
@endif
@foreach ($alert->vorfall->details as $schluessel => $wert)
{!! $schluessel !!}: {!! is_bool($wert) ? ($wert ? 'ja' : 'nein') : $wert !!}
@endforeach
@if ($mitFehlertext && $alert->vorfall->fehlertext)
Fehlertext: {!! $alert->vorfall->fehlertext !!}
@endif
@if ($alert->wiederholungen > 0)
{!! $alert->wiederholungen !!}× seit letzter Meldung
@endif

Freigabe: {!! $alert->freigabeLink !!}
