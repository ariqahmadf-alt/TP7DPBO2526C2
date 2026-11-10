<?php
/**
 * config/db.php
 *
 * Class Database: pembungkus koneksi PDO dengan pola Singleton.
 * Singleton menjamin hanya ada SATU koneksi ke MySQL selama satu request,
 * sehingga semua class model berbagi koneksi yang sama (hemat resource dan
 * memungkinkan transaksi lintas model memakai koneksi yang sama).
 */
class Database
{
    /** @var Database|null Satu-satunya instance class ini. */
    private static ?Database $instance = null;

    /** @var PDO Objek koneksi PDO. */
    private PDO $connection;

    /**
     * Constructor bersifat private agar objek tidak bisa dibuat dengan "new"
     * dari luar class. Koneksi dibuat satu kali di sini.
     */
    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            // Setiap kesalahan SQL dilempar sebagai PDOException (bukan diam-diam gagal).
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Hasil fetch berupa array asosiatif (nama kolom => nilai).
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Matikan emulasi: prepared statement benar-benar diproses oleh MySQL,
            // sehingga parameter tidak pernah digabung ke teks SQL (anti SQL injection).
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Pesan asli PDOException bisa memuat host/user, jadi dicatat ke log
            // server dan yang ditampilkan hanya pesan umum.
            error_log('Koneksi database gagal: ' . $e->getMessage());
            throw new RuntimeException(
                'Tidak dapat terhubung ke database. Pastikan MySQL menyala dan database "'
                . DB_NAME . '" sudah diimpor.'
            );
        }
    }

    /** Mencegah penggandaan instance lewat clone. */
    private function __clone() {}

    /** Mencegah pembuatan instance lewat unserialize. */
    public function __wakeup()
    {
        throw new LogicException('Singleton Database tidak boleh di-unserialize.');
    }

    /**
     * Mengambil instance tunggal Database (dibuat saat pertama kali dipanggil).
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Mengembalikan objek PDO untuk dipakai class model.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
