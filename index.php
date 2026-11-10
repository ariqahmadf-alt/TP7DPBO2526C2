<?php
/**
 * index.php - Front controller Cekrek.in Booking Manager
 *
 * Seluruh request masuk lewat file ini. Urutannya:
 *   1. muat konfigurasi + autoload, mulai session
 *   2. tangani halaman login / logout
 *   3. pastikan admin sudah login
 *   4. baca ?page=...&action=... lalu serahkan ke controller yang sesuai
 *   5. controller memanggil model (class/), lalu merender view (view/)
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/autoload.php';

// Halaman yang dibuka bila ?page= kosong atau setelah login.
const DEFAULT_PAGE = 'dashboard';

// Cookie session tidak dapat dibaca JavaScript dan tidak ikut request lintas situs.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Parameter URL diambil hanya bila berupa string (menolak ?page[]=x).
$page   = is_string($_GET['page'] ?? null) ? $_GET['page'] : DEFAULT_PAGE;
$action = is_string($_GET['action'] ?? null) ? $_GET['action'] : 'index';

/**
 * Menampilkan halaman error sederhana (dipakai bila database tidak dapat dihubungi).
 * Pesan di-escape agar aman.
 */
function renderFatal(string $message): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Terjadi kesalahan</title></head>'
        . '<body style="font-family:sans-serif;padding:2rem"><h1>Terjadi kesalahan</h1><p>'
        . Html::e($message) . '</p></body></html>';
    exit;
}

// ---------------------------------------------------------------------
// 1) Login
// ---------------------------------------------------------------------
if ($page === 'login') {
    if (Auth::check()) {
        Http::redirect(Http::url(['page' => DEFAULT_PAGE]));
    }

    if (Http::isPost()) {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Flash::error('Token keamanan tidak valid. Silakan coba lagi.');
            Http::redirect(Http::url(['page' => 'login']));
        }

        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if (Auth::attempt($username, $password)) {
            Http::redirect(Http::url(['page' => DEFAULT_PAGE]));
        }

        usleep(400000); // jeda singkat memperlambat tebak-tebakan password
        Flash::withValidation(['username' => $username], []);
        Flash::error('Username atau password salah.');
        Http::redirect(Http::url(['page' => 'login']));
    }

    // GET: tampilkan form login (tanpa header/footer aplikasi).
    require __DIR__ . '/view/login.php';
    exit;
}

// ---------------------------------------------------------------------
// 2) Semua halaman lain wajib login
// ---------------------------------------------------------------------
if (!Auth::check()) {
    Http::redirect(Http::url(['page' => 'login']));
}

// ---------------------------------------------------------------------
// 3) Logout (hanya lewat POST + token CSRF)
// ---------------------------------------------------------------------
if ($page === 'logout') {
    if (Http::isPost() && Csrf::verify($_POST['_csrf'] ?? null)) {
        Auth::logout();
        session_start(); // session baru agar pesan flash dapat ditampilkan
        Flash::info('Anda telah keluar.');
        Http::redirect(Http::url(['page' => 'login']));
    }
    Http::redirect(Http::url(['page' => DEFAULT_PAGE]));
}

// ---------------------------------------------------------------------
// 4) Pastikan database bisa dihubungi sebelum memanggil model
// ---------------------------------------------------------------------
try {
    Database::getInstance();
} catch (RuntimeException $e) {
    renderFatal($e->getMessage());
}

// ---------------------------------------------------------------------
// 5) Peta halaman -> controller
// ---------------------------------------------------------------------
// Entitas sederhana ditangani CrudController generik.
$simpleEntities = [
    'customers' => [
        'model' => Customer::class, 'label' => 'Customer', 'view' => 'customers',
        'fields' => ['name', 'phone', 'email', 'organization'],
    ],
    'packages' => [
        'model' => Package::class, 'label' => 'Paket', 'view' => 'packages',
        'fields' => ['name', 'price', 'duration_hours', 'description'],
    ],
    'frames' => [
        'model' => Frame::class, 'label' => 'Frame', 'view' => 'frames',
        'fields' => ['name', 'theme', 'extra_price'],
    ],
    'booth_units' => [
        'model' => BoothUnit::class, 'label' => 'Unit booth', 'view' => 'booth_units',
        'fields' => ['code', 'type', 'status'],
    ],
];

try {
    if (isset($simpleEntities[$page])) {
        $cfg = $simpleEntities[$page];
        $controller = new CrudController(
            new $cfg['model'](), $page, $cfg['label'], $cfg['view'], $cfg['fields']
        );
        $controller->handle($action);
        exit;
    }

    // Halaman dengan logika khusus punya controller sendiri.
    switch ($page) {
        case 'dashboard':
            (new DashboardController(new Booking(), new Payment(), new BoothUnit()))->handle();
            exit;
        case 'bookings':
            (new BookingController(new Booking()))->handle($action);
            exit;
        case 'payments':
            (new PaymentController(new Payment(), new Booking()))->handle($action);
            exit;
    }

    Flash::error('Halaman tidak ditemukan.');
    Http::redirect(Http::url(['page' => DEFAULT_PAGE]));
} catch (RuntimeException $e) {
    // Dilempar antara lain oleh Csrf::validateRequest() bila token tidak cocok.
    Flash::error($e->getMessage());
    $known = array_merge(array_keys($simpleEntities), ['dashboard', 'bookings', 'payments']);
    Http::redirect(Http::url(['page' => in_array($page, $known, true) ? $page : DEFAULT_PAGE]));
} catch (Throwable $e) {
    // Kesalahan tak terduga: dicatat di log, pengguna hanya melihat pesan umum.
    error_log('Kesalahan tak terduga: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    renderFatal('Terjadi kesalahan pada aplikasi. Silakan coba lagi.');
}
