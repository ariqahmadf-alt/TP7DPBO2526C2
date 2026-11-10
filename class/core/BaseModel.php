<?php
/**
 * BaseModel (abstract)
 *
 * Induk dari semua class model. Menyimpan koneksi PDO dan nama tabel, serta
 * menyediakan operasi generik (all, find, delete, count, exists) yang seluruhnya
 * memakai prepared statement lewat method run().
 *
 * create() dan update() sengaja abstract: tiap turunan punya kolom berbeda,
 * jadi wajib mendefinisikannya sendiri.
 */
abstract class BaseModel
{
    use Validator;

    /** @var PDO Koneksi database bersama (dari Database singleton). */
    protected PDO $db;

    /** @var string Nama tabel; diisi oleh class turunan. Bukan dari input pengguna. */
    protected string $table;

    /**
     * @param PDO|null $db Koneksi opsional (berguna untuk pengujian); bila kosong
     *                     dipakai koneksi singleton.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Operasi wajib dimiliki tiap turunan
    // ------------------------------------------------------------------

    /**
     * Menyimpan data baru. Mengembalikan ID baris yang dibuat.
     * @throws ValidationException bila data tidak valid.
     */
    abstract public function create(array $data): int;

    /**
     * Mengubah data yang sudah ada. Mengembalikan true bila query berhasil.
     * @throws ValidationException bila data tidak valid.
     */
    abstract public function update(int $id, array $data): bool;

    // ------------------------------------------------------------------
    // Operasi generik
    // ------------------------------------------------------------------

    /**
     * Mengambil semua baris tabel, urut berdasarkan id. LIMIT/OFFSET (bila
     * diisi) di-bind sebagai integer.
     */
    public function all(?int $limit = null, int $offset = 0): array
    {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY id ASC";
        $params = [];

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit']  = max(0, $limit);
            $params[':offset'] = max(0, $offset);
        }

        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * Data satu halaman untuk tampilan daftar (pagination). Bawaan: sama dengan
     * all(); turunan boleh meng-override untuk menambahkan JOIN/agregat
     * (mis. jumlah booking per customer).
     */
    public function listPage(int $limit, int $offset): array
    {
        return $this->all($limit, $offset);
    }

    /**
     * Mencari satu baris berdasarkan id. Mengembalikan null bila tidak ada.
     */
    public function find(int $id): ?array
    {
        $row = $this->run(
            "SELECT * FROM `{$this->table}` WHERE id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Menghapus satu baris. Sebelum dihapus, ensureDeletable() dipanggil agar
     * turunan dapat menolak penghapusan data yang masih dipakai.
     * @throws DeleteRestrictedException bila data masih dipakai.
     */
    public function delete(int $id): bool
    {
        $this->ensureDeletable($id);

        $stmt = $this->run("DELETE FROM `{$this->table}` WHERE id = :id", [':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Menghitung jumlah seluruh baris pada tabel. */
    public function count(): int
    {
        $row = $this->run("SELECT COUNT(*) AS total FROM `{$this->table}`")->fetch();
        return (int) $row['total'];
    }

    /** Memeriksa apakah baris dengan id tersebut ada. */
    public function exists(int $id): bool
    {
        $row = $this->run(
            "SELECT 1 AS ada FROM `{$this->table}` WHERE id = :id LIMIT 1",
            [':id' => $id]
        )->fetch();

        return $row !== false;
    }

    // ------------------------------------------------------------------
    // Hook & helper untuk turunan
    // ------------------------------------------------------------------

    /**
     * Hook pengecekan sebelum delete(). Bawaan: tidak ada pembatasan.
     * Turunan meng-override dan melempar DeleteRestrictedException bila perlu.
     */
    protected function ensureDeletable(int $id): void
    {
        // sengaja kosong
    }

    /**
     * Menghitung berapa baris di tabel lain yang merujuk id tertentu.
     * Nama tabel/kolom berasal dari kode (konstan), bukan input pengguna.
     */
    protected function countUsage(string $table, string $column, int $id): int
    {
        $row = $this->run(
            "SELECT COUNT(*) AS total FROM `$table` WHERE `$column` = :id",
            [':id' => $id]
        )->fetch();

        return (int) $row['total'];
    }

    /**
     * SATU-SATUNYA pintu eksekusi query: selalu prepare() lalu execute().
     * Tipe parameter dikenali otomatis supaya integer (mis. LIMIT/OFFSET)
     * di-bind sebagai PDO::PARAM_INT.
     *
     * @param string $sql    Query dengan placeholder bernama (:nama).
     * @param array  $params Peta :placeholder => nilai.
     */
    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);

        foreach ($params as $name => $value) {
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif ($value === null) {
                $type = PDO::PARAM_NULL;
            } else {
                $type = PDO::PARAM_STR;
            }
            $stmt->bindValue($name, $value, $type);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Menjalankan sekumpulan operasi dalam SATU transaksi: beginTransaction,
     * commit bila semua berhasil, rollBack bila ada exception apa pun, lalu
     * exception dilempar ulang. Bila sudah berada dalam transaksi (dipanggil
     * dari model lain), callback langsung dijalankan di transaksi yang sama
     * sehingga rollback tetap membatalkan seluruh rangkaian.
     *
     * @param callable $callback Fungsi berisi operasi database.
     * @return mixed Nilai kembalian callback.
     */
    protected function transaction(callable $callback): mixed
    {
        if ($this->db->inTransaction()) {
            return $callback();
        }

        $this->db->beginTransaction();
        try {
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
