<?php
/**
 * Customer
 *
 * Model tabel `customers` (pemesan jasa photobooth).
 * Aturan khusus: email harus unik, dan customer yang masih punya booking
 * tidak boleh dihapus.
 */
class Customer extends BaseModel
{
    protected string $table = 'customers';

    /** Daftar satu halaman beserta jumlah booking tiap customer (LEFT JOIN + COUNT). */
    public function listPage(int $limit, int $offset): array
    {
        return $this->run(
            'SELECT c.*, COUNT(b.id) AS booking_count
               FROM customers c
          LEFT JOIN bookings b ON b.customer_id = c.id
           GROUP BY c.id
           ORDER BY c.id DESC
              LIMIT :limit OFFSET :offset',
            [':limit' => $limit, ':offset' => $offset]
        )->fetchAll();
    }

    /** Menambah customer baru. @throws ValidationException */
    public function create(array $data): int
    {
        $d = $this->prepareData($data, null);

        $this->run(
            'INSERT INTO customers (name, phone, email, organization)
             VALUES (:name, :phone, :email, :organization)',
            [
                ':name' => $d['name'], ':phone' => $d['phone'],
                ':email' => $d['email'], ':organization' => $d['organization'],
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /** Mengubah data customer. @throws ValidationException */
    public function update(int $id, array $data): bool
    {
        $d = $this->prepareData($data, $id);

        $this->run(
            'UPDATE customers
                SET name = :name, phone = :phone, email = :email, organization = :organization
              WHERE id = :id',
            [
                ':name' => $d['name'], ':phone' => $d['phone'],
                ':email' => $d['email'], ':organization' => $d['organization'],
                ':id' => $id,
            ]
        );
        return true;
    }

    /**
     * Merapikan input, memvalidasi, dan memeriksa keunikan email.
     * @param int|null $ignoreId ID yang dikecualikan saat cek email (untuk update).
     */
    private function prepareData(array $data, ?int $ignoreId): array
    {
        $d = [
            'name'         => trim((string) ($data['name'] ?? '')),
            'phone'        => trim((string) ($data['phone'] ?? '')),
            'email'        => trim((string) ($data['email'] ?? '')),
            'organization' => trim((string) ($data['organization'] ?? '')),
        ];

        $this->validate($d, [
            'name'         => ['required', 'max:100'],
            'phone'        => ['required', 'phone', 'max:20'],
            'email'        => ['email', 'max:100'],
            'organization' => ['max:100'],
        ], [
            'name' => 'Nama', 'phone' => 'Nomor telepon',
            'email' => 'Email', 'organization' => 'Organisasi',
        ]);

        if ($d['email'] !== '' && $this->emailTaken($d['email'], $ignoreId)) {
            throw new ValidationException(['email' => 'Email sudah dipakai customer lain.']);
        }

        // Kolom opsional yang kosong disimpan sebagai NULL (bukan string kosong).
        $d['email']        = $d['email'] === '' ? null : $d['email'];
        $d['organization'] = $d['organization'] === '' ? null : $d['organization'];
        return $d;
    }

    /** Apakah email sudah dipakai customer lain? (id 0 = tidak ada yang dikecualikan) */
    private function emailTaken(string $email, ?int $ignoreId): bool
    {
        $row = $this->run(
            'SELECT id FROM customers WHERE email = :email AND id <> :ignore LIMIT 1',
            [':email' => $email, ':ignore' => $ignoreId ?? 0]
        )->fetch();
        return $row !== false;
    }

    /** Menolak hapus bila customer masih punya booking. */
    protected function ensureDeletable(int $id): void
    {
        $used = $this->countUsage('bookings', 'customer_id', $id);
        if ($used > 0) {
            $row = $this->find($id);
            throw new DeleteRestrictedException(
                'Customer "' . ($row['name'] ?? '') . '" tidak dapat dihapus karena masih memiliki '
                . $used . ' booking. Hapus booking terkait terlebih dahulu.'
            );
        }
    }
}
