<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>easyVerein verknüpfen — FUF Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/fuf.css">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 480px;">
    <h1 class="h4">easyVerein verknüpfen</h1>
    <?php if (!empty($flash)): ?>
        <div class="alert alert-info" role="status"><?= htmlspecialchars((string) $flash, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($linked): ?>
        <p>Dein Konto ist bereits mit easyVerein verknüpft. Du kannst dich mit easyVerein oder deinem bisherigen Passwort anmelden.</p>
    <?php elseif (!$enabled): ?>
        <p>Die easyVerein-Anmeldung ist noch nicht eingerichtet.</p>
    <?php else: ?>
        <p>Bestätige dein bisheriges Kalkulator-Passwort. Anschließend meldest du dich bei easyVerein an. Deine Reisen und Rechte bleiben erhalten.</p>
        <form method="post" action="/account/easyverein/link">
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
