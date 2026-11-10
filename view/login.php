<?php
/**
 * view/login.php
 *
 * Halaman login admin: latar navy, kartu putih di tengah. Dimuat langsung oleh
 * index.php (tanpa header/footer aplikasi). Pesan error datang dari Flash.
 */
$messages = Flash::consume();
$oldUsername = Flash::old('username', '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | <?= Html::e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preload" href="assets/fonts/plus-jakarta-sans-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
    <main class="login-card">
        <div class="login-brand">
            <img src="assets/img/logo.png" alt="Cekrek.in"
                 onerror="this.style.display='none';this.nextElementSibling.hidden=false;">
            <span class="brand-text" hidden>Cekrek.in</span>
        </div>

        <h1>Masuk ke Booking Manager</h1>
        <p class="login-sub">Khusus admin Cekrek.in.</p>

        <?php foreach ($messages as $msg): ?>
            <?php $type = in_array($msg['type'], ['success', 'error', 'info'], true) ? $msg['type'] : 'info'; ?>
            <div class="flash flash-<?= $type ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>" style="margin-bottom:1rem">
                <span><?= Html::e($msg['message']) ?></span>
            </div>
        <?php endforeach; ?>

        <form method="post" action="<?= Html::e(Http::url(['page' => 'login'])) ?>">
            <?= Csrf::field() ?>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus
                       autocomplete="username" value="<?= Html::e($oldUsername) ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary">Masuk</button>
        </form>
    </main>
</body>
</html>
