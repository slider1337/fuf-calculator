<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Infrastructure\Http\Auth\Session::csrfToken(), ENT_QUOTES) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mein Konto — FUF Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/fuf.css">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 480px;">
    <h1 class="h4">Mein Konto</h1>
    <p class="text-muted"><?= htmlspecialchars((string) $email, ENT_QUOTES) ?></p>
    <h2 class="h5">easyVerein-Anmeldung</h2>
    <?php if (!empty($flash)): ?>
        <div class="alert alert-info" role="status"><?= htmlspecialchars((string) $flash, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($linked): ?>
        <p><span class="badge text-bg-success">Verknüpft</span></p>
        <p>Du kannst dich mit easyVerein oder deinem bisherigen Passwort anmelden.</p>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h3 class="h6">Verknüpfung entfernen</h3>
                <p class="small">Bestätige dein Kalkulator-Passwort. Danach bleibt die Anmeldung mit E-Mail und Passwort verfügbar. Deine Reisen und Rechte bleiben erhalten.</p>
                <form method="post" action="/account/easyverein/unlink">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Infrastructure\Http\Auth\Session::csrfToken(), ENT_QUOTES) ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                    <label class="form-label" for="unlink-password">Kalkulator-Passwort</label>
                    <input class="form-control mb-3" id="unlink-password" type="password" name="password" autocomplete="current-password" required>
                    <button class="btn btn-outline-danger" type="submit">Verknüpfung entfernen</button>
                </form>
            </div>
        </div>
    <?php elseif (!$enabled): ?>
        <p>Die easyVerein-Anmeldung ist noch nicht eingerichtet.</p>
    <?php else: ?>
        <p><span class="badge text-bg-secondary">Nicht verknüpft</span></p>
        <p>Bestätige dein bisheriges Kalkulator-Passwort. Anschließend meldest du dich bei easyVerein an. Deine Reisen und Rechte bleiben erhalten.</p>
        <form method="post" action="/account/easyverein/link">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Infrastructure\Http\Auth\Session::csrfToken(), ENT_QUOTES) ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
            <label class="form-label" for="password">Bisheriges Kalkulator-Passwort</label>
            <input class="form-control mb-3" id="password" type="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-primary" type="submit">Mit easyVerein verknüpfen</button>
        </form>
    <?php endif; ?>
    <p class="mt-3"><a href="/">Zurück zum Kalkulator</a></p>
</main>
</body>
</html>
