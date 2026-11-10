<?php
/**
 * Package
 *
 * Model tabel `packages` (paket sewa berdasarkan durasi).
 * Paket yang masih dipakai booking tidak boleh dihapus.
 */
class Package extends BaseModel
{
    protected string $table = 'packages';

    /** Daftar satu halaman beserta jumlah booking yang memakai tiap paket. */
    public function listPage(int $limit, int $offset): array
    {
        return $this->run(
            'SELECT p.*, COUNT(b.id) AS booking_count
               FROM packages p
          LEFT JOIN bookings b ON b.package_id = p.id
           GROUP BY p.id
           ORDER BY p.id DESC
              LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    /** Menambah paket baru. @throws ValidationException */
    public function create(array $data): int
    {
        $d = $this->prepareData($data);

        $this->run(
            'INSERT INTO packages (name, price, duration_hours, description)
             VALUES (:name, :price, :duration, :description)',
            [
                ':name' => $d['name'], ':price' => $d['price'],
                ':duration' => $d['duration_hours'], ':description' => $d['description'],
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Mengubah paket. @throws ValidationException */
    public function update(int $id, array $data): bool
    {
        $d = $this->prepareData($data);

        $this->run(
            'UPDATE packages
                SET name = :name, price = :price, duration_hours = :duration, description = :description
              WHERE id = :id',
            [
                ':name' => $d['name'], ':price' => $d['price'],
                ':duration' => $d['duration_hours'], ':description' => $d['description'],
                ':id' => $id,
            ]
        );
        return true;
    }

    /** Merapikan dan memvalidasi input paket. */
    private function prepareData(array $data): array
    {
        $d = [
            'name'           => trim((string) ($data['name'] ?? '')),
            'price'          => trim((string) ($data['price'] ?? '')),
            'duration_hours' => trim((string) ($data['duration_hours'] ?? '')),
            'description'    => trim((string) ($data['description'] ?? '')),
        ];

        $this->validate($d, [
            'name'           => ['required', 'max:100'],
            'price'          => ['required', 'decimal', 'positive'],
            'duration_hours' => ['required', 'integer', 'between:1,24'],
            'description'    => ['max:2000'],
        ], [
            'name' => 'Nama paket', 'price' => 'Harga',
            'duration_hours' => 'Durasi (jam)', 'description' => 'Deskripsi',
        ]);

        $d['duration_hours'] = (int) $d['duration_hours'];
        $d['description']    = $d['description'] === '' ? null : $d['description'];
        return $d;
    }

    /** Menolak hapus bila paket masih dipakai booking. */
    protected function ensureDeletable(int $id): void
    {
        $used = $this->countUsage('bookings', 'package_id', $id);
        if ($used > 0) {
            $row = $this->find($id);
            throw new DeleteRestrictedException(
                'Paket "' . ($row['name'] ?? '') . '" tidak dapat dihapus karena masih dipakai oleh '
                . $used . ' booking.'
            );
        }
    }
}
