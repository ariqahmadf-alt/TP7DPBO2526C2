<?php
/**
 * config/config.php
 *
 * Berisi seluruh konfigurasi aplikasi: kredensial database, tarif overtime,
 * jumlah data per halaman, dan akun admin demo.
 * File ini dimuat paling awal oleh index.php.
 */

// ---------- Zona waktu ----------
// Dipakai agar fungsi date() dan perhitungan "bulan ini" sesuai waktu lokal.
date_default_timezone_set('Asia/Jakarta');

// ---------- Identitas aplikasi ----------
define('APP_NAME', 'Cekrek.in Booking Manager');

// ---------- Koneksi database ----------
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_cekrekin');
define('DB_USER', 'root');   // bawaan Laragon/XAMPP
define('DB_PASS', '');       // bawaan Laragon/XAMPP (kosong)
define('DB_CHARSET', 'utf8mb4');

// ---------- Aturan bisnis ----------
// Tarif tambahan durasi (overtime) per jam, dalam Rupiah.
define('OVERTIME_RATE', 250000);

// Jumlah baris data per halaman pada tampilan daftar (pagination).
define('PER_PAGE', 10);

// ---------- Akun admin demo ----------
// Password TIDAK disimpan polos, melainkan hasil password_hash().
// Akun demo -> username: admin | password: admin123
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$Xb35DAgl3NUZXcs30lRVDOYKeh/1fbeuv6SXu/wzy6guyt/Z0pZ9m');
