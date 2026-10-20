<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Meldung freigegeben</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; color: #1f2937; margin: 0; padding: 2rem 1rem; }
        main { max-width: 32rem; margin: 0 auto; background: #fff; padding: 1.5rem; border-radius: .5rem; }
        code { background: #f3f4f6; padding: .1rem .3rem; border-radius: .25rem; }
    </style>
</head>
<body>
<main>
    <h1>Erledigt</h1>
    @if ($warGesperrt)
        <p>Die Meldung <code>{{ $fingerabdruck }}</code> wurde freigegeben.</p>
    @else
        <p>Die Meldung <code>{{ $fingerabdruck }}</code> war bereits freigegeben.</p>
    @endif
</main>
</body>
</html>
