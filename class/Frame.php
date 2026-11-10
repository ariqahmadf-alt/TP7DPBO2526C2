<?php
/**
 * Frame
 *
 * Model tabel `frames` (desain frame cetak, bisa menambah biaya).
 * Frame yang masih dipilih pada booking (tabel pivot booking_frames) tidak
 * boleh dihapus.
 */
class Frame extends BaseModel
{
    protected string $table = 'frames';

    /** Daftar satu halaman beserta jumlah booking yang memilih tiap frame. */
    public function listPage(int $limit, int $offset): array
    {
        return $this->run(
            'SELECT f.*, COUNT(bf.booking_id) AS booking_count
               FROM frames f
          LEFT JOIN booking_frames bf ON bf.frame_id = f.id
           GROUP BY f.id
           ORDER BY f.id DESC
              LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    /** Menambah frame baru. @throws ValidationException */
    public function create(array $data): int
    {
        $d = $this->prepareData($data);

        $this->run(
            'INSERT INTO frames (name, theme, extra_price) VALUES (:name, :theme, :extra)',
            [':name' => $d['name'], ':theme' => $d['theme'], ':extra' => $d['extra_price']]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Mengubah frame. @throws ValidationException */
    public function update(int $id, array $data): bool
    {
        $d = $this->prepareData($data);

        $this->run(
            'UPDATE frames SET name = :name, theme = :theme, extra_price = :extra WHERE id = :id',
            [':name' => $d['name'], ':theme' => $d['theme'], ':extra' => $d['extra_price'], ':id' => $id]
        );
        return true;
    }

    /** Merapikan dan memvalidasi input frame. */
    private function prepareData(array $data): array
    {
        $extra = trim((string) ($data['extra_price'] ?? ''));

        $d = [
            'name'        => trim((string) ($data['name'] ?? '')),
            'theme'       => trim((string) ($data['theme'] ?? '')),
            // Biaya tambahan kosong dianggap 0.
            'extra_price' => $extra === '' ? '0' : $extra,
        ];

        $this->validate($d, [
            'name'        => ['required', 'max:100'],
            'theme'       => ['max:50'],
            'extra_price' => ['decimal', 'nonnegative'],
        ], [
            'name' => 'Nama frame', 'theme' => 'Tema', 'extra_price' => 'Biaya tambahan',
        ]);

        $d['theme'] = $d['theme'] === '' ? null : $d['theme'];
        return $d;
    }

    /** Menolak hapus bila frame masih dipilih pada booking. */
    protected function ensureDeletable(int $id): void
    {
        $used = $this->countUsage('booking_frames', 'frame_id', $id);
        if ($used > 0) {
            $row = $this->find($id);
            throw new DeleteRestrictedException(
                'Frame "' . ($row['name'] ?? '') . '" tidak dapat dihapus karena masih dipilih pada '
                . $used . ' booking.'
            );
        }
    }
}
