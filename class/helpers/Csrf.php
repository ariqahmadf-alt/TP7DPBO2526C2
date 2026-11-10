<?php
/**
 * Csrf
 *
 * Perlindungan terhadap Cross-Site Request Forgery. Satu token acak dibuat per
 * session, disisipkan sebagai input tersembunyi di setiap form POST, lalu
 * diverifikasi di server sebelum aksi apa pun dijalankan.
 */
class Csrf
{
    /** Mengambil token session; membuatnya bila belum ada. */
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Menghasilkan tag <input hidden> siap tempel di form. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /** Membandingkan token kiriman dengan token session (aman dari timing attack). */
    public static function verify(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /**
     * Memverifikasi token dari $_POST. Bila tidak cocok, request dihentikan
     * dengan exception sehingga aksi tidak pernah dijalankan.
     * @throws RuntimeException
     */
    public static function validateRequest(): void
    {
        if (!self::verify($_POST['_csrf'] ?? null)) {
            throw new RuntimeException('Token keamanan tidak valid atau kedaluwarsa. Silakan muat ulang halaman.');
        }
    }
}
