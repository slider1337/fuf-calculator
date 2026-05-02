<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort vergeben &mdash; FUF Gruppenreise Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 480px;">
    <?php
    $isInvitation = ($purpose ?? null) === 'invitation';
    $title = $isInvitation ? 'Konto aktivieren' : 'Neues Passwort vergeben';
    ?>
    <h1 class="h4 mb-3"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <?php if (!empty($expired)): ?>
        <p>Bitte fordere einen neuen Link an.</p>
        <p><a href="/login">Zum Login</a> &middot; <a href="/forgot-password">Passwort vergessen</a></p>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (!empty($email)): ?>
                    <p class="text-muted small mb-3">F&uuml;r <strong><?= htmlspecialchars((string) $email, ENT_QUOTES) ?></strong></p>
                <?php endif; ?>
                <form method="post" action="/set-password">
                    <input type="hidden" name="token" value="<?= htmlspecialchars((string) ($token ?? ''), ENT_QUOTES) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">Passwort</label>
                        <input id="password" class="form-control" type="password" name="password" minlength="10" required autofocus>
                        <div class="form-text">Mindestens 10 Zeichen.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Passwort wiederholen</label>
                        <input id="password_confirmation" class="form-control" type="password" name="password_confirmation" minlength="10" required>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">Passwort speichern</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
