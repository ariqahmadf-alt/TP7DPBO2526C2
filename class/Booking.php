<?php
/**
 * Booking
 *
 * Model tabel `bookings` (pemesanan acara) beserta relasi frame (pivot
 * booking_frames). Pusat logika bisnis aplikasi:
 *
 *  A. Deteksi bentrok jadwal per unit booth (booking cancelled diabaikan).
 *  B. Validasi jam selesai harus setelah jam mulai.
 *  C. Harga dihitung otomatis di sini (bukan di view).
 *  D. Create/update dalam satu transaksi (booking + frame + DP awal).
 *  F. Status "done" hanya boleh bila booking sudah lunas.
 *  G. Booking yang sudah punya pembayaran tidak boleh dihapus.
 *  I. Pencarian, filter status/tanggal, dan pagination.
 */
class Booking extends BaseModel
{
    protected string $table = 'bookings';

    /** Status booking beserta label tampilannya. */
    public const STATUSES = [
        'pending'   => 'Menunggu',
        'confirmed' => 'Terkonfirmasi',
        'done'      => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];

    /** Bagian SELECT + JOIN yang dipakai bersama oleh daftar dan detail. */
    private const SELECT_LIST =
        "SELECT b.*, c.name AS customer_name, c.phone AS customer_phone,
                p.name AS package_name, u.code AS booth_code,
                (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.booking_id = b.id) AS paid_total,
                (SELECT GROUP_CONCAT(f.name ORDER BY f.name SEPARATOR ', ')
                   FROM booking_frames bf JOIN frames f ON f.id = bf.frame_id
                  WHERE bf.booking_id = b.id) AS frame_names
           FROM bookings b
           JOIN customers c   ON c.id = b.customer_id
           JOIN packages p    ON p.id = b.package_id
           JOIN booth_units u ON u.id = b.booth_unit_id";

    // ==================================================================
    // READ: daftar, pencarian, detail
    // ==================================================================

    /**
     * Menyusun klausa WHERE dari filter. Semua nilai memakai parameter terikat.
     * Filter: q (nama event/customer), status, date_from, date_to.
     *
     * @return array{0:string,1:array} [klausa WHERE, parameter]
     */
    private function buildFilter(array $filters): array
    {
        $where  = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Karakter khusus LIKE (% _ \) di-escape agar dicari apa adanya.
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(b.event_name LIKE :q_event OR c.name LIKE :q_customer)';
            $params[':q_event']    = $like;
            $params[':q_customer'] = $like;
        }

        $status = (string) ($filters['status'] ?? '');
        if (isset(self::STATUSES[$status])) {
            $where[] = 'b.status = :status';
            $params[':status'] = $status;
        }

        $from = (string) ($filters['date_from'] ?? '');
        if ($from !== '' && $this->isValidDate($from)) {
            $where[] = 'b.event_date >= :date_from';
            $params[':date_from'] = $from;
        }

        $to = (string) ($filters['date_to'] ?? '');
        if ($to !== '' && $this->isValidDate($to)) {
            $where[] = 'b.event_date <= :date_to';
            $params[':date_to'] = $to;
        }

        return [empty($where) ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }

    /** Jumlah booking yang cocok dengan filter (untuk pagination). */
    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildFilter($filters);

        $row = $this->run(
            'SELECT COUNT(*) AS total
               FROM bookings b JOIN customers c ON c.id = b.customer_id' . $where,
            $params
        )->fetch();
        return (int) $row['total'];
    }

    /** Daftar booking satu halaman (terbaru dahulu) sesuai filter. */
    public function search(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildFilter($filters);
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $rows = $this->run(
            self::SELECT_LIST . $where
            . ' ORDER BY b.event_date DESC, b.start_time DESC, b.id DESC LIMIT :limit OFFSET :offset',
            $params
        )->fetchAll();

        return $this->decorate($rows);
    }

    /** Detail satu booking beserta info relasi dan status bayar; null bila tidak ada. */
    public function findDetail(int $id): ?array
    {
        $rows = $this->run(self::SELECT_LIST . ' WHERE b.id = :id LIMIT 1', [':id' => $id])->fetchAll();
        $rows = $this->decorate($rows);
        return $rows[0] ?? null;
    }

    /** ID frame yang dipilih pada sebuah booking. */
    public function frameIds(int $bookingId): array
    {
        $ids = $this->run(
            'SELECT frame_id FROM booking_frames WHERE booking_id = :id ORDER BY frame_id',
            [':id' => $bookingId]
        )->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $ids);
    }

    /**
     * Booking terdekat yang akan datang (pending/confirmed, mulai hari ini dan seterusnya).
     * Dipakai dashboard.
     */
    public function upcoming(int $limit, string $today): array
    {
        $rows = $this->run(
            self::SELECT_LIST
            . " WHERE b.status IN ('pending','confirmed') AND b.event_date >= :today
                ORDER BY b.event_date ASC, b.start_time ASC LIMIT :limit",
            [':today' => $today, ':limit' => $limit]
        )->fetchAll();

        return $this->decorate($rows);
    }

    /** Jumlah booking aktif (non-cancelled) yang acaranya jatuh pada rentang tanggal. */
    public function countBetween(string $firstDay, string $lastDay): int
    {
        $row = $this->run(
            "SELECT COUNT(*) AS total FROM bookings
              WHERE status <> 'cancelled' AND event_date BETWEEN :first_day AND :last_day",
            [':first_day' => $firstDay, ':last_day' => $lastDay]
        )->fetch();
        return (int) $row['total'];
    }

    /**
     * Booking aktif yang masih punya sisa tagihan; dipakai dropdown form pembayaran.
     * Tiap baris dilengkapi 'summary' (terbayar, sisa, status).
     */
    public function payableOptions(): array
    {
        $rows = $this->run(
            "SELECT b.id, b.event_name, b.total_price, c.name AS customer_name,
                    (SELECT COALESCE(SUM(pay.amount), 0) FROM payments pay WHERE pay.booking_id = b.id) AS paid_total
               FROM bookings b JOIN customers c ON c.id = b.customer_id
              WHERE b.status <> 'cancelled'
              ORDER BY b.event_date DESC, b.id DESC"
        )->fetchAll();

        $options = [];
        foreach ($rows as $row) {
            $row['summary'] = Payment::summarize($row['total_price'], $row['paid_total']);
            if (Money::toCents($row['summary']['remaining']) > 0) {
                $options[] = $row;
            }
        }
        return $options;
    }

    /** Menambahkan ringkasan pembayaran turunan (paid, remaining, status) ke tiap baris. */
    private function decorate(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['summary'] = Payment::summarize($row['total_price'], $row['paid_total']);
        }
        return $rows;
    }

    // ==================================================================
    // C. HITUNG HARGA
    // ==================================================================

    /**
     * Menghitung harga booking:
     *   total = harga paket + jumlah extra_price frame terpilih
     *           + (overtime_hours x OVERTIME_RATE) - discount
     * Diskon tidak boleh melebihi subtotal sehingga total tidak pernah negatif.
     *
     * @param int    $packageId     ID paket.
     * @param int[]  $frameIds      ID frame terpilih (unik).
     * @param int    $overtimeHours Jam tambahan.
     * @param string $discount      Diskon dalam rupiah (string desimal).
     * @return array{package_price:string,frames_total:string,overtime_total:string,subtotal:string,discount:string,total:string,total_cents:int}
     * @throws ValidationException bila paket/frame tidak ada atau diskon melebihi subtotal.
     */
    public function calculatePricing(int $packageId, array $frameIds, int $overtimeHours, string $discount): array
    {
        // Harga paket.
        $package = $this->run('SELECT price FROM packages WHERE id = :id', [':id' => $packageId])->fetch();
        if ($package === false) {
            throw new ValidationException(['package_id' => 'Paket tidak ditemukan.']);
        }
        $packageC = Money::toCents($package['price']);

        // Total extra_price frame terpilih (placeholder dibuat dinamis, nilainya tetap terikat).
        $framesC = 0;
        $frameIds = array_values(array_unique(array_map('intval', $frameIds)));
        if (!empty($frameIds)) {
            $placeholders = [];
            $params = [];
            foreach ($frameIds as $i => $frameId) {
                $placeholders[] = ':frame' . $i;
                $params[':frame' . $i] = $frameId;
            }
            $rows = $this->run(
                'SELECT id, extra_price FROM frames WHERE id IN (' . implode(',', $placeholders) . ')',
                $params
            )->fetchAll();

            if (count($rows) !== count($frameIds)) {
                throw new ValidationException(['frame_ids' => 'Ada frame yang dipilih tidak ditemukan.']);
            }
            foreach ($rows as $row) {
                $framesC += Money::toCents($row['extra_price']);
            }
        }

        $overtimeC = max(0, $overtimeHours) * Money::toCents(OVERTIME_RATE);
        $subtotalC = $packageC + $framesC + $overtimeC;
        $discountC = Money::toCents($discount);

        if ($discountC > $subtotalC) {
            throw new ValidationException([
                'discount' => 'Diskon tidak boleh melebihi subtotal (Rp '
                    . number_format($subtotalC / 100, 0, ',', '.') . ').',
            ]);
        }

        $totalC = $subtotalC - $discountC;

        return [
            'package_price'  => Money::fromCents($packageC),
            'frames_total'   => Money::fromCents($framesC),
            'overtime_total' => Money::fromCents($overtimeC),
            'subtotal'       => Money::fromCents($subtotalC),
            'discount'       => Money::fromCents($discountC),
            'total'          => Money::fromCents($totalC),
            'total_cents'    => $totalC,
        ];
    }

    // ==================================================================
    // CREATE
    // ==================================================================

    /**
     * Membuat booking baru. Seluruh langkah berjalan dalam SATU transaksi:
     *   kunci unit booth -> cek bentrok -> insert bookings -> insert booking_frames
     *   -> (opsional) insert DP awal. Bila salah satu gagal, semuanya dibatalkan.
     *
     * @throws ValidationException       data tidak valid.
     * @throws BookingConflictException  jadwal bentrok.
     */
    public function create(array $data): int
    {
        $d = $this->normalizeInput($data);
        $this->validateInput($d, true);
        $pricing = $this->calculatePricing(
            (int) $d['package_id'], $d['frame_ids'], (int) $d['overtime_hours'], $d['discount']
        );

        return (int) $this->transaction(function () use ($d, $pricing) {
            // Cek ketersediaan unit + bentrok jadwal (unit dikunci selama transaksi).
            $this->lockAndCheckSchedule($d, null, null);

            $this->run(
                'INSERT INTO bookings
                    (customer_id, package_id, booth_unit_id, event_name, venue, event_date,
                     start_time, end_time, overtime_hours, discount, total_price, status, notes)
                 VALUES
                    (:customer_id, :package_id, :booth_unit_id, :event_name, :venue, :event_date,
                     :start_time, :end_time, :overtime_hours, :discount, :total_price, :status, :notes)',
                $this->bookingParams($d, $pricing)
            );
            $bookingId = (int) $this->db->lastInsertId();

            $this->syncFrames($bookingId, $d['frame_ids']);

            // DP awal (opsional) dicatat lewat Payment di transaksi yang sama.
            if ($d['dp_amount'] !== '') {
                $dpAmountC = Money::toCents($d['dp_amount']);
                try {
                    (new Payment($this->db))->create([
                        'booking_id' => (string) $bookingId,
                        'amount'     => $d['dp_amount'],
                        // Bila nominal sama dengan total, tipenya pelunasan.
                        'type'       => $dpAmountC >= $pricing['total_cents'] ? 'pelunasan' : 'dp',
                        'method'     => $d['dp_method'],
                        'paid_at'    => date('Y-m-d H:i:s'),
                        'note'       => 'DP awal saat pembuatan booking',
                    ]);
                } catch (ValidationException $e) {
                    // Pesan error Payment dipetakan ke nama field di form booking.
                    $map = ['amount' => 'dp_amount', 'type' => 'dp_amount', 'method' => 'dp_method', 'paid_at' => 'dp_amount'];
                    $errors = [];
                    foreach ($e->getErrors() as $field => $message) {
                        $errors[$map[$field] ?? 'dp_amount'] = $message;
                    }
                    throw new ValidationException($errors);
                }
            }

            return $bookingId;
        });
    }

    // ==================================================================
    // UPDATE
    // ==================================================================

    /**
     * Memperbarui booking dalam satu transaksi (termasuk sinkronisasi frame).
     * Harga dihitung ulang. Status "done" hanya boleh bila sudah lunas.
     *
     * @throws ValidationException
     * @throws BookingConflictException
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new ValidationException(['event_name' => 'Booking tidak ditemukan.']);
        }

        $d = $this->normalizeInput($data);
        $this->validateInput($d, false);
        $pricing = $this->calculatePricing(
            (int) $d['package_id'], $d['frame_ids'], (int) $d['overtime_hours'], $d['discount']
        );

        return (bool) $this->transaction(function () use ($id, $d, $pricing, $existing) {
            // Kunci baris booking agar tidak berebut dengan pencatatan pembayaran.
            $this->run('SELECT id FROM bookings WHERE id = :id FOR UPDATE', [':id' => $id]);

            // Cek unit + bentrok; booking ini sendiri dikecualikan dari pengecekan.
            $this->lockAndCheckSchedule($d, $id, (int) $existing['booth_unit_id']);

            // Total baru tidak boleh lebih kecil dari uang yang sudah masuk.
            $row = $this->run(
                'SELECT COALESCE(SUM(amount), 0) AS paid FROM payments WHERE booking_id = :id',
                [':id' => $id]
            )->fetch();
            $paidC = Money::toCents($row['paid']);

            if ($paidC > $pricing['total_cents']) {
                throw new ValidationException([
                    'discount' => 'Total baru (Rp ' . number_format($pricing['total_cents'] / 100, 0, ',', '.')
                        . ') lebih kecil dari pembayaran yang sudah masuk (Rp '
                        . number_format($paidC / 100, 0, ',', '.')
                        . '). Sesuaikan paket, frame, overtime, atau diskon.',
                ]);
            }

            // Aturan F: Selesai hanya bila lunas.
            if ($d['status'] === 'done' && $paidC < $pricing['total_cents']) {
                throw new ValidationException([
                    'status' => 'Booking hanya dapat berstatus Selesai jika sudah lunas. Sisa tagihan: Rp '
                        . number_format(($pricing['total_cents'] - $paidC) / 100, 0, ',', '.') . '.',
                ]);
            }

            $params = $this->bookingParams($d, $pricing);
            $params[':id'] = $id;
            $this->run(
                'UPDATE bookings
                    SET customer_id = :customer_id, package_id = :package_id, booth_unit_id = :booth_unit_id,
                        event_name = :event_name, venue = :venue, event_date = :event_date,
                        start_time = :start_time, end_time = :end_time, overtime_hours = :overtime_hours,
                        discount = :discount, total_price = :total_price, status = :status, notes = :notes
                  WHERE id = :id',
                $params
            );

            $this->syncFrames($id, $d['frame_ids']);
            return true;
        });
    }

    // ==================================================================
    // DELETE
    // ==================================================================

    /**
     * Booking yang sudah punya pembayaran tidak boleh dihapus (sarankan batalkan).
     * Booking tanpa pembayaran boleh dihapus; relasi booking_frames ikut terhapus
     * oleh ON DELETE CASCADE.
     */
    protected function ensureDeletable(int $id): void
    {
        $used = $this->countUsage('payments', 'booking_id', $id);
        if ($used > 0) {
            throw new DeleteRestrictedException(
                'Booking ini sudah memiliki ' . $used . ' pembayaran sehingga tidak dapat dihapus. '
                . 'Ubah statusnya menjadi Dibatalkan agar riwayat pembayaran tetap tersimpan.'
            );
        }
    }

    // ==================================================================
    // A. BENTROK JADWAL
    // ==================================================================

    /**
     * Mengunci unit booth, memastikan unit boleh dipakai, lalu memeriksa bentrok.
     * Booking berstatus cancelled tidak memblokir jadwal, jadi pengecekan dilewati.
     * Harus dipanggil di dalam transaksi (FOR UPDATE menahan booking bersamaan).
     *
     * @param int|null $ignoreId         Booking yang dikecualikan (saat update).
     * @param int|null $currentBoothId   Unit lama booking (saat update), atau null.
     */
    private function lockAndCheckSchedule(array $d, ?int $ignoreId, ?int $currentBoothId): void
    {
        if ($d['status'] === 'cancelled') {
            return;
        }

        $boothId = (int) $d['booth_unit_id'];
        $unit = $this->run(
            'SELECT id, code, status FROM booth_units WHERE id = :id FOR UPDATE',
            [':id' => $boothId]
        )->fetch();

        if ($unit === false) {
            throw new ValidationException(['booth_unit_id' => 'Unit booth tidak ditemukan.']);
        }

        // Unit maintenance tidak boleh dipilih (kecuali booking lama yang tetap di unitnya).
        if ($unit['status'] === 'maintenance' && $boothId !== $currentBoothId) {
            throw new ValidationException([
                'booth_unit_id' => 'Unit "' . $unit['code'] . '" sedang dalam perawatan dan tidak dapat dipilih.',
            ]);
        }

        $conflict = $this->findConflict(
            $boothId, $d['event_date'], $this->toTime($d['start_time']), $this->toTime($d['end_time']), $ignoreId
        );
        if ($conflict !== null) {
            $date = DateTime::createFromFormat('Y-m-d', $conflict['event_date']);
            throw new BookingConflictException(
                sprintf(
                    'Jadwal bentrok dengan booking #%d "%s" (%s, %s-%s) pada unit %s.',
                    $conflict['id'],
                    $conflict['event_name'],
                    $date ? $date->format('d/m/Y') : $conflict['event_date'],
                    substr($conflict['start_time'], 0, 5),
                    substr($conflict['end_time'], 0, 5),
                    $unit['code']
                ),
                $conflict
            );
        }
    }

    /**
     * Mencari booking lain pada unit dan tanggal yang sama dengan rentang waktu beririsan:
     *   start_baru < end_lama  DAN  end_baru > start_lama
     * Booking cancelled diabaikan; $ignoreId dikecualikan.
     */
    private function findConflict(int $boothId, string $date, string $start, string $end, ?int $ignoreId): ?array
    {
        $row = $this->run(
            "SELECT id, event_name, event_date, start_time, end_time
               FROM bookings
              WHERE booth_unit_id = :unit
                AND event_date = :event_date
                AND status <> 'cancelled'
                AND id <> :ignore
                AND :new_start < end_time
                AND :new_end > start_time
           ORDER BY start_time ASC
              LIMIT 1",
            [
                ':unit' => $boothId, ':event_date' => $date, ':ignore' => $ignoreId ?? 0,
                ':new_start' => $start, ':new_end' => $end,
            ]
        )->fetch();

        return $row === false ? null : $row;
    }

    // ==================================================================
    // Internal: normalisasi, validasi, penyimpanan
    // ==================================================================

    /** Merapikan input mentah (hanya string/array yang diharapkan) menjadi struktur bersih. */
    private function normalizeInput(array $data): array
    {
        $s = fn(string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        // frame_ids: hanya angka bulat positif, tanpa duplikat.
        $frameIds = [];
        foreach ((array) ($data['frame_ids'] ?? []) as $fid) {
            if (is_string($fid) && ctype_digit($fid) && (int) $fid > 0) {
                $frameIds[] = (int) $fid;
            }
        }

        return [
            'customer_id'    => $s('customer_id'),
            'package_id'     => $s('package_id'),
            'booth_unit_id'  => $s('booth_unit_id'),
            'event_name'     => $s('event_name'),
            'venue'          => $s('venue'),
            'event_date'     => $s('event_date'),
            'start_time'     => $s('start_time'),
            'end_time'       => $s('end_time'),
            'overtime_hours' => $s('overtime_hours') === '' ? '0' : $s('overtime_hours'),
            'discount'       => $s('discount') === '' ? '0' : $s('discount'),
            'status'         => $s('status') === '' ? 'pending' : $s('status'),
            'notes'          => $s('notes'),
            'frame_ids'      => array_values(array_unique($frameIds)),
            'dp_amount'      => $s('dp_amount'),
            'dp_method'      => $s('dp_method'),
        ];
    }

    /** Validasi lengkap; seluruh error dikumpulkan lalu dilempar sekaligus. */
    private function validateInput(array $d, bool $isCreate): void
    {
        $errors = [];

        $allowedStatuses = $isCreate ? ['pending', 'confirmed'] : array_keys(self::STATUSES);

        try {
            $this->validate($d, [
                'customer_id'    => ['required', 'integer', 'positive'],
                'package_id'     => ['required', 'integer', 'positive'],
                'booth_unit_id'  => ['required', 'integer', 'positive'],
                'event_name'     => ['required', 'max:150'],
                'venue'          => ['max:200'],
                'event_date'     => ['required', 'date'],
                'start_time'     => ['required', 'time'],
                'end_time'       => ['required', 'time'],
                'overtime_hours' => ['integer', 'between:0,24'],
                'discount'       => ['decimal', 'nonnegative'],
                'status'         => ['required', 'in:' . implode(',', $allowedStatuses)],
                'notes'          => ['max:2000'],
                'dp_amount'      => ['decimal', 'positive'],
            ], [
                'customer_id' => 'Customer', 'package_id' => 'Paket', 'booth_unit_id' => 'Unit booth',
                'event_name' => 'Nama event', 'venue' => 'Lokasi', 'event_date' => 'Tanggal event',
                'start_time' => 'Jam mulai', 'end_time' => 'Jam selesai', 'overtime_hours' => 'Overtime',
                'discount' => 'Diskon', 'status' => 'Status', 'notes' => 'Catatan', 'dp_amount' => 'Nominal DP',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
        }

        // B. Jam selesai harus setelah jam mulai.
        if (!isset($errors['start_time']) && !isset($errors['end_time'])
            && $this->toTime($d['end_time']) <= $this->toTime($d['start_time'])) {
            $errors['end_time'] = 'Jam selesai harus setelah jam mulai.';
        }

        // Pastikan customer, paket, dan unit yang dipilih benar-benar ada.
        foreach (['customer_id' => 'customers', 'package_id' => 'packages', 'booth_unit_id' => 'booth_units'] as $field => $table) {
            if (!isset($errors[$field]) && $this->countUsage($table, 'id', (int) $d[$field]) === 0) {
                $errors[$field] = 'Pilihan tidak ditemukan.';
            }
        }

        // DP awal: hanya saat membuat, dan wajib disertai metode.
        if ($d['dp_amount'] !== '') {
            if (!$isCreate) {
                $errors['dp_amount'] = 'DP awal hanya dapat diisi saat membuat booking. Gunakan menu Pembayaran.';
            } elseif (!isset(Payment::METHODS[$d['dp_method']])) {
                $errors['dp_method'] = 'Pilih metode pembayaran untuk DP.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /** Parameter terikat untuk INSERT/UPDATE bookings. */
    private function bookingParams(array $d, array $pricing): array
    {
        return [
            ':customer_id'    => (int) $d['customer_id'],
            ':package_id'     => (int) $d['package_id'],
            ':booth_unit_id'  => (int) $d['booth_unit_id'],
            ':event_name'     => $d['event_name'],
            ':venue'          => $d['venue'] === '' ? null : $d['venue'],
            ':event_date'     => $d['event_date'],
            ':start_time'     => $this->toTime($d['start_time']),
            ':end_time'       => $this->toTime($d['end_time']),
            ':overtime_hours' => (int) $d['overtime_hours'],
            ':discount'       => $pricing['discount'],
            ':total_price'    => $pricing['total'],
            ':status'         => $d['status'],
            ':notes'          => $d['notes'] === '' ? null : $d['notes'],
        ];
    }

    /**
     * Menyamakan frame booking dengan pilihan terbaru: hapus semua relasi lama
     * lalu isi ulang. Dipanggil di dalam transaksi pemanggil.
     */
    private function syncFrames(int $bookingId, array $frameIds): void
    {
        $this->run('DELETE FROM booking_frames WHERE booking_id = :id', [':id' => $bookingId]);

        foreach ($frameIds as $frameId) {
            $this->run(
                'INSERT INTO booking_frames (booking_id, frame_id) VALUES (:booking_id, :frame_id)',
                [':booking_id' => $bookingId, ':frame_id' => (int) $frameId]
            );
        }
    }

    /** Menyeragamkan jam "H:i" menjadi "H:i:s" agar perbandingan string akurat. */
    private function toTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
