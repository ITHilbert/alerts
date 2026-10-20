<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Meldung freigeben</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; color: #1f2937; margin: 0; padding: 2rem 1rem; }
        main { max-width: 32rem; margin: 0 auto; background: #fff; padding: 1.5rem; border-radius: .5rem; }
        code { background: #f3f4f6; padding: .1rem .3rem; border-radius: .25rem; }
        button { background: #1d4ed8; color: #fff; border: 0; padding: .6rem 1.2rem; border-radius: .375rem; cursor: pointer; }
    </style>
</head>
<body>
<main>
    <h1>Meldung freigeben</h1>
    <p>Die Sperre für <code>{{ $fingerabdruck }}</code> wird aufgehoben. Tritt der Fehler erneut auf, wird sofort wieder gemeldet.</p>
    <form method="post" action="{{ $aktion }}">
        @csrf
        <button type="submit">Jetzt freigeben</button>
    </form>
</main>
</body>
</html>
