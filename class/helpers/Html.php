<?php
/**
 * Html
 *
 * Pembantu tampilan: escape output dan format angka. Dipakai di view agar
 * semua keluaran ke HTML aman dari XSS.
 */
class Html
{
    /** Escape nilai apa pun menjadi teks HTML aman (htmlspecialchars). */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Format Rupiah, mis. 1500000 -> "Rp 1.500.000". */
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    /** Memotong teks panjang menjadi ringkasan dengan tanda "...". */
    public static function excerpt(?string $text, int $width = 80): string
    {
        return mb_strimwidth((string) $text, 0, $width, '...', 'UTF-8');
    }

    /** Nama bulan singkat bahasa Indonesia. */
    private const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Format tanggal Y-m-d menjadi "14 Nov 2026". */
    public static function date(?string $date): string
    {
        $t = $date ? strtotime($date) : false;
        return $t ? date('j', $t) . ' ' . self::MONTHS[(int) date('n', $t)] . ' ' . date('Y', $t) : '-';
    }

    /** Format jam H:i:s menjadi "H:i". */
    public static function time(?string $time): string
    {
        return $time ? substr($time, 0, 5) : '-';
    }

    /** Badge status booking (pill berwarna). */
    public static function bookingBadge(string $status): string
    {
        $map = [
            'pending'   => ['badge-pending',   'Menunggu'],
            'confirmed' => ['badge-primary',   'Terkonfirmasi'],
            'done'      => ['badge-success',   'Selesai'],
            'cancelled' => ['badge-cancelled', 'Dibatalkan'],
        ];
        [$class, $label] = $map[$status] ?? ['badge-primary', $status];
        return '<span class="badge ' . $class . '">' . self::e($label) . '</span>';
    }

    /** Badge status pembayaran turunan: belum | dp | lunas. */
    public static function paymentBadge(string $status): string
    {
        $map = [
            'belum' => ['badge-cancelled', 'Belum bayar'],
            'dp'    => ['badge-pending',   'DP'],
            'lunas' => ['badge-success',   'Lunas'],
        ];
        [$class, $label] = $map[$status] ?? ['badge-primary', $status];
        return '<span class="badge ' . $class . '">' . self::e($label) . '</span>';
    }
}
