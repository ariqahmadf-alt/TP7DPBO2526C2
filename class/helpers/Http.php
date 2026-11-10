<?php
/**
 * Http
 *
 * Pembantu kecil untuk urusan HTTP: membuat URL, redirect, dan cek metode request.
 */
class Http
{
    /**
     * Membuat URL relatif ke index.php, mis. url(['page'=>'customers','action'=>'edit','id'=>3])
     * menghasilkan "index.php?page=customers&action=edit&id=3".
     */
    public static function url(array $params = []): string
    {
        return 'index.php' . (empty($params) ? '' : '?' . http_build_query($params));
    }

    /** Mengarahkan browser ke URL lain lalu menghentikan eksekusi (pola Post-Redirect-Get). */
    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** Apakah request ini bermetode POST? */
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
