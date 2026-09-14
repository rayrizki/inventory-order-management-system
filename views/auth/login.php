<?php
/**
 * @var bool $loginFailed
 * @var string $csrfToken
 */
$loginFailed ??= false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Inventory &amp; Order Management System</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="login-title">
            <h1 id="login-title" class="auth-card__title">Masuk</h1>
            <p class="auth-card__subtitle">Inventory &amp; Order Management System</p>

            <p class="form-error" role="alert" id="login-error" <?= $loginFailed ? '' : 'hidden' ?>>
                Email atau password salah.
            </p>

            <form method="post" action="/login" novalidate>
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="form-field">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="username"
                        aria-describedby="email-required login-error"
                        required
                    >
                    <p class="form-hint form-hint--error" id="email-required" hidden>Email wajib diisi.</p>
                </div>

                <div class="form-field">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        aria-describedby="password-required login-error"
                        required
                    >
                    <p class="form-hint form-hint--error" id="password-required" hidden>Password wajib diisi.</p>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Masuk</button>
            </form>

            <p class="auth-card__footer">
                Belum punya akun? Hubungi Admin - tidak ada pendaftaran mandiri.
            </p>
        </section>
    </main>
    <script src="/assets/js/form-validation.js" defer></script>
</body>
</html>
