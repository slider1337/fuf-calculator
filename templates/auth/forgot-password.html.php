<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Passwort vergessen &mdash; FUF Gruppenreise Kalkulator</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 480px;">
    <h1 class="h4 mb-3">Passwort zur&uuml;cksetzen</h1>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-info"><?= htmlspecialchars((string) $flash, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars((string) $error, ENT_QUOTES) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <p class="text-muted small">Gib deine E-Mail-Adresse ein. Wir senden dir einen Link, um ein neues Passwort zu vergeben.</p>
            <form method="post" action="/forgot-password">
                <div class="mb-3">
                    <label class="form-label" for="email">E-Mail</label>
                    <input id="email" class="form-control" type="email" name="email" required autofocus>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" type="submit">Link senden</button>
                </div>
            </form>
        </div>
    </div>

    <div class="text-center mt-3 small">
        <a href="/login">Zur&uuml;ck zum Login</a>
    </div>
</div>
</body>
</html>
