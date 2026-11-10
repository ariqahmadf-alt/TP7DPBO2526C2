<?php
/**
 * Auth
 *
 * Autentikasi admin demo. Kredensial dibaca dari config; password diverifikasi
 * dengan password_verify() terhadap hash, bukan dibandingkan sebagai teks polos.
 */
class Auth
{
    /**
     * Mencoba login. Mengembalikan true bila username dan password cocok.
     */
    public static function attempt(string $username, string $password): bool
    {
        $usernameOk = hash_equals(ADMIN_USERNAME, $username);

        // password_verify selalu dijalankan (walau username salah) agar waktu
        // respons tidak membocorkan apakah username benar.
        $passwordOk = password_verify($password, ADMIN_PASSWORD_HASH);

        if ($usernameOk && $passwordOk) {
            // Ganti ID session setelah login untuk mencegah session fixation.
            session_regenerate_id(true);
            $_SESSION['admin'] = ADMIN_USERNAME;
            return true;
        }
        return false;
    }

    /** Apakah ada admin yang sedang login? */
    public static function check(): bool
    {
        return !empty($_SESSION['admin']);
    }

    /** Nama admin yang sedang login (atau null). */
    public static function username(): ?string
    {
        return $_SESSION['admin'] ?? null;
    }

    /** Logout: hapus data session, cookie session, dan ganti session baru. */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
