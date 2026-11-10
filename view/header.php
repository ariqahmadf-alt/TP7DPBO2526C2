<?php
/**
 * view/header.php
 *
 * Bagian atas setiap halaman: <head>, navbar, dan pesan flash.
 * Variabel dari controller: $currentPage (menu aktif) dan $label (judul halaman).
 */
$navItems = [
    'dashboard'   => 'Dashboard',
    'bookings'    => 'Booking',
    'payments'    => 'Pembayaran',
    'customers'   => 'Customer',
    'packages'    => 'Paket',
    'frames'      => 'Frame',
    'booth_units' => 'Unit Booth',
];
$pageTitle = !empty($label) ? $label . ' | ' . APP_NAME : APP_NAME;
$flashIcons = ['success' => '&#10003;', 'error' => '&#10005;', 'info' => 'i'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="preload" href="assets/fonts/plus-jakarta-sans-latin-800-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<a class="skip-link" href="#konten">Lewati ke konten</a>

<header class="site-header">
    <div class="nav-bar">
        <a class="brand" href="<?= Html::e(Http::url(['page' => 'dashboard'])) ?>">
            <img src="assets/img/logo-inverted.png" alt="Cekrek.in"
                 onerror="this.style.display='none';this.nextElementSibling.hidden=false;">
            <span class="brand-text" hidden>Cekrek.in</span>
        </a>

        <button type="button" class="nav-toggle" id="nav-toggle"
                aria-expanded="false" aria-controls="nav-menu">Menu</button>

        <div class="nav-menu" id="nav-menu">
            <nav aria-label="Navigasi utama">
                <ul class="nav-list">
                    <?php foreach ($navItems as $slug => $text): ?>
                        <li>
                            <a class="nav-link<?= ($currentPage ?? '') === $slug ? ' active' : '' ?>"
                               href="<?= Html::e(Http::url(['page' => $slug])) ?>"
                               <?= ($currentPage ?? '') === $slug ? 'aria-current="page"' : '' ?>><?= Html::e($text) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="nav-user">
                <span><?= Html::e(Auth::username() ?? '') ?></span>
                <form method="post" action="<?= Html::e(Http::url(['page' => 'logout'])) ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</header>

<main class="container" id="konten">
<?php $flashMessages = Flash::consume(); ?>
<?php if (!empty($flashMessages)): ?>
    <div class="flash-stack">
        <?php foreach ($flashMessages as $msg): ?>
            <?php $type = isset($flashIcons[$msg['type']]) ? $msg['type'] : 'info'; ?>
            <div class="flash flash-<?= $type ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
                <span class="flash-icon" aria-hidden="true"><?= $flashIcons[$type] ?></span>
                <span><?= Html::e($msg['message']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
