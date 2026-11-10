<?php
/**
 * Flash
 *
 * Penyimpan data sekali-pakai di session untuk pola Post-Redirect-Get:
 *  - pesan notifikasi (sukses / error / info)
 *  - input lama (old input) agar form tidak kosong saat validasi gagal
 *  - pesan error per field dari ValidationException
 * Data dibaca satu kali lalu otomatis dihapus.
 */
class Flash
{
    /** Cache lokal agar old()/error() boleh dipanggil berkali-kali dalam satu render. */
    private static ?array $oldCache = null;
    private static ?array $errorCache = null;

    // ---------- Pesan notifikasi ----------

    /** Menyimpan pesan dengan tipe: success | error | info. */
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void { self::set('success', $message); }
    public static function error(string $message): void   { self::set('error', $message); }
    public static function info(string $message): void    { self::set('info', $message); }

    /** Mengambil semua pesan lalu menghapusnya dari session. */
    public static function consume(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    // ---------- Old input & error per field ----------

    /** Menyimpan input form + error validasi sebelum redirect kembali ke form. */
    public static function withValidation(array $oldInput, array $errors): void
    {
        $_SESSION['_old']    = $oldInput;
        $_SESSION['_errors'] = $errors;
    }

    /** Mengambil nilai input lama untuk satu field (atau nilai bawaan). */
    public static function old(string $key, mixed $default = ''): mixed
    {
        if (self::$oldCache === null) {
            self::$oldCache = $_SESSION['_old'] ?? [];
            unset($_SESSION['_old']);
        }
        return self::$oldCache[$key] ?? $default;
    }

    /** Mengambil pesan error satu field, atau null bila tidak ada. */
    public static function fieldError(string $field): ?string
    {
        if (self::$errorCache === null) {
            self::$errorCache = $_SESSION['_errors'] ?? [];
            unset($_SESSION['_errors']);
        }
        return self::$errorCache[$field] ?? null;
    }
}
