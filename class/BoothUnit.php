<?php
/**
 * BoothUnit
 *
 * Model tabel `booth_units` (unit fisik photobooth).
 * Aturan khusus: kode unik, dan unit yang masih dipakai booking tidak boleh
 * dihapus. Unit berstatus maintenance tidak boleh dipilih untuk booking baru
 * (lihat availableUnits()).
 */
class BoothUnit extends BaseModel
{
    protected string $table = 'booth_units';

    /** Pilihan status yang sah beserta labelnya. */
    public const STATUSES = ['available' => 'Tersedia', 'maintenance' => 'Perawatan'];

    /** Daftar satu halaman beserta jumlah booking pada tiap unit. */
    public function listPage(int $limit, int $offset): array
    {
        return $this->run(
            'SELECT u.*, COUNT(b.id) AS booking_count
               FROM booth_units u
          LEFT JOIN bookings b ON b.booth_unit_id = u.id
           GROUP BY u.id
           ORDER BY u.id ASC
              LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    /** Unit yang boleh dipilih untuk booking (status available). Dipakai dropdown form booking. */
    public function availableUnits(): array
    {
        return $this->run(
            "SELECT * FROM booth_units WHERE status = 'available' ORDER BY code ASC"
        )->fetchAll();
    }

    /**
     * Unit yang paling sering dipakai (booking non-cancelled), untuk dashboard.
     * @return array<int,array{code:string,type:string,total:int|string}>
     */
    public function mostUsed(int $limit = 3): array
    {
        return $this->run(
            "SELECT u.code, u.type, COUNT(b.id) AS total
               FROM booth_units u
               JOIN bookings b ON b.booth_unit_id = u.id AND b.status <> 'cancelled'
           GROUP BY u.id
           ORDER BY total DESC, u.code ASC
              LIMIT :limit",
            [':limit' => $limit]
        )->fetchAll();
    }

    /** Menambah unit baru. @throws ValidationException */
    public function create(array $data): int
    {
        $d = $this->prepareData($data, null);

        $this->run(
            'INSERT INTO booth_units (code, type, status) VALUES (:code, :type, :status)',
            [':code' => $d['code'], ':type' => $d['type'], ':status' => $d['status']]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Mengubah unit. @throws ValidationException */
    public function update(int $id, array $data): bool
    {
        $d = $this->prepareData($data, $id);

        $this->run(
            'UPDATE booth_units SET code = :code, type = :type, status = :status WHERE id = :id',
            [':code' => $d['code'], ':type' => $d['type'], ':status' => $d['status'], ':id' => $id]
        );
        return true;
    }

    /** Merapikan input, memvalidasi, dan memeriksa keunikan kode. */
    private function prepareData(array $data, ?int $ignoreId): array
    {
        $d = [
            'code'   => strtoupper(trim((string) ($data['code'] ?? ''))),
            'type'   => trim((string) ($data['type'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'available')),
        ];

        $this->validate($d, [
            'code'   => ['required', 'max:20'],
            'type'   => ['required', 'max:50'],
            'status' => ['required', 'in:' . implode(',', array_keys(self::STATUSES))],
        ], [
            'code' => 'Kode unit', 'type' => 'Tipe', 'status' => 'Status',
        ]);

        $row = $this->run(
            'SELECT id FROM booth_units WHERE code = :code AND id <> :ignore LIMIT 1',
            [':code' => $d['code'], ':ignore' => $ignoreId ?? 0]
        )->fetch();
        if ($row !== false) {
            throw new ValidationException(['code' => 'Kode unit sudah dipakai unit lain.']);
        }

        return $d;
    }

    /** Menolak hapus bila unit masih dipakai booking. */
    protected function ensureDeletable(int $id): void
    {
        $used = $this->countUsage('bookings', 'booth_unit_id', $id);
        if ($used > 0) {
            $row = $this->find($id);
            throw new DeleteRestrictedException(
                'Unit "' . ($row['code'] ?? '') . '" tidak dapat dihapus karena masih dipakai oleh '
                . $used . ' booking. Ubah status menjadi Perawatan bila unit tidak ingin dipakai.'
            );
        }
    }
}
