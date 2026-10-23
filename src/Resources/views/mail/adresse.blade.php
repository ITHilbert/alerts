@if ($verlinken && is_string($wert) && preg_match('#^https?://#', $wert) === 1)<a href="{{ $wert }}">{{ $wert }}</a>@else{{ is_bool($wert) ? ($wert ? 'ja' : 'nein') : $wert }}@endif
