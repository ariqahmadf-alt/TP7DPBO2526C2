<?php
/**
 * Payment
 *
 * Model tabel `payments` (pembayaran DP / pelunasan sebuah booking).
 *
 * Aturan bisnis utama:
 *  - Total pembayaran satu booking tidak boleh melebihi total_price booking.
 *  - Status pembayaran DITURUNKAN (tidak disimpan): belum bayar, DP, atau lunas.
 *  - Tipe "pelunasan" harus menutup seluruh sisa tagihan; tipe "dp" harus
 *    menyisakan tagihan.
 *  - Booking yang sudah berstatus Selesai harus tetap lunas.
 */
class Payment extends BaseModel
{
    protected string $table = 'payments';

    public const TYPES   = ['dp' => 'DP', 'pelunasan' => 'Pelunasan'];
    public const METHODS = ['transfer' => 'Transfer', 'cash' => 'Tunai', 'qris' => 'QRIS'];

    // ------------------------------------------------------------------
    // Status pembayaran turunan
    // ------------------------------------------------------------------

    /**
     * Menurunkan status pembayaran dari total tagihan dan total terbayar.
     *   belum : terbayar = 0
     *   dp    : 0 < terbayar < total
     *   lunas : terbayar >= total (termasuk tagihan bernilai 0)
     *
     * @return array{paid:string, remaining:string, status:string}
     */
    public static function summarize(string|float|int|null $total, string|float|int|null $paid): array
    {
        $totalC = Money::toCents($total);
        $paidC  = Money::toCents($paid);

        if ($paidC >= $totalC) {
            $status = 'lunas';
        } elseif ($paidC > 0) {
            $status = 'dp';
        } else {
            $status = 'belum';
        }

        return [
            'paid'      => Money::fromCents($paidC),
            'remaining' => Money::fromCents(max(0, $totalC - $paidC)),
            'status'    => $status,
        ];
    }

    // ------------------------------------------------------------------
    // Query baca
    // ------------------------------------------------------------------

    /** Subquery total terbayar suatu booking; dipakai ulang di beberapa query. */
    private const PAID_SUBQUERY =
        '(SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2 WHERE p2.booking_id = b.id)';

    /**
     * Daftar pembayaran satu halaman beserta info booking, customer, dan status
     * bayar booking. Dapat difilter per booking.
     */
    public function search(?int $bookingId, int $limit, int $offset): array
    {
        $sql = 'SELECT pay.*, b.event_name, b.total_price, b.status AS booking_status,
                       c.name AS customer_name, ' . self::PAID_SUBQUERY . ' AS paid_total
                  FROM payments pay
                  JOIN bookings b  ON b.id = pay.booking_id
                  JOIN customers c ON c.id = b.customer_id';
        $params = [];

        if ($bookingId !== null) {
            $sql .= ' WHERE pay.booking_id = :booking_id';
            $params[':booking_id'] = $bookingId;
        }

        $sql .= ' ORDER BY pay.paid_at DESC, pay.id DESC LIMIT :limit OFFSET :offset';
        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        $rows = $this->run($sql, $params)->fetchAll();
        foreach ($rows as &$row) {
            $row['summary'] = self::summarize($row['total_price'], $row['paid_total']);
        }
        return $rows;
    }

    /** Jumlah pembayaran (opsional per booking) untuk pagination. */
    public function countFor(?int $bookingId): int
    {
        if ($bookingId === null) {
            return $this->count();
        }
        return $this->countUsage('payments', 'booking_id', $bookingId);
    }

    /** Total terbayar sebuah booking; $ignorePaymentId dikecualikan (untuk update). */
    public function totalPaidFor(int $bookingId, ?int $ignorePaymentId = null): string
    {
        $row = $this->run(
            'SELECT COALESCE(SUM(amount), 0) AS paid FROM payments
              WHERE booking_id = :booking_id AND id <> :ignore',
            [':booking_id' => $bookingId, ':ignore' => $ignorePaymentId ?? 0]
        )->fetch();
        return (string) $row['paid'];
    }

    // ------------------------------------------------------------------
    // Agregat untuk dashboard
    // ------------------------------------------------------------------

    /**
     * Pendapatan = total pembayaran yang DITERIMA pada rentang waktu tertentu,
     * tidak termasuk booking berstatus cancelled.
     *
     * @param string $from Awal rentang (inklusif), format 'Y-m-d H:i:s'.
     * @param string $to   Akhir rentang (eksklusif), format 'Y-m-d H:i:s'.
     */
    public function revenueBetween(string $from, string $to): string
    {
        $row = $this->run(
            "SELECT COALESCE(SUM(pay.amount), 0) AS total
               FROM payments pay
               JOIN bookings b ON b.id = pay.booking_id
              WHERE b.status <> 'cancelled'
                AND pay.paid_at >= :from_dt AND pay.paid_at < :to_dt",
            [':from_dt' => $from, ':to_dt' => $to]
        )->fetch();
        return (string) $row['total'];
    }

    /** Total sisa tagihan seluruh booking aktif (bukan cancelled) yang belum lunas. */
    public function totalOutstanding(): string
    {
        $row = $this->run(
            "SELECT COALESCE(SUM(GREATEST(b.total_price - COALESCE(x.paid, 0), 0)), 0) AS total
               FROM bookings b
          LEFT JOIN (SELECT booking_id, SUM(amount) AS paid FROM payments GROUP BY booking_id) x
                 ON x.booking_id = b.id
              WHERE b.status <> 'cancelled'"
        )->fetch();
        return (string) $row['total'];
    }

    // ------------------------------------------------------------------
    // Create / Update
    // ------------------------------------------------------------------

    /**
     * Mencatat pembayaran baru.
     * @throws ValidationException bila data tidak valid / melebihi tagihan.
     */
    public function create(array $data): int
    {
        $d = $this->normalize($data);
        $this->validateInput($d);

        return (int) $this->transaction(function () use ($d) {
            // Kunci baris booking agar dua pembayaran bersamaan tidak lolos bersamaan.
            $booking = $this->lockBooking((int) $d['booking_id']);
            $this->assertAmountAllowed($booking, $d, null, true);

            $this->run(
                'INSERT INTO payments (booking_id, amount, type, method, paid_at, note)
                 VALUES (:booking_id, :amount, :type, :method, :paid_at, :note)',
                [
                    ':booking_id' => (int) $d['booking_id'],
                    ':amount'     => Money::fromCents(Money::toCents($d['amount'])),
                    ':type'       => $d['type'],
                    ':method'     => $d['method'],
                    ':paid_at'    => $d['paid_at'],
                    ':note'       => $d['note'] === '' ? null : $d['note'],
                ]
            );
            return (int) $this->db->lastInsertId();
        });
    }

    /**
     * Mengubah pembayaran. Booking pembayaran tidak dapat dipindah.
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->find($id);
        if ($existing === null) {
            throw new ValidationException(['amount' => 'Pembayaran tidak ditemukan.']);
        }

        $data['booking_id'] = (string) $existing['booking_id'];
        $d = $this->normalize($data);
        $this->validateInput($d);

        return (bool) $this->transaction(function () use ($id, $d) {
            $booking = $this->lockBooking((int) $d['booking_id']);
            $this->assertAmountAllowed($booking, $d, $id, false);

            $this->run(
                'UPDATE payments
                    SET amount = :amount, type = :type, method = :method, paid_at = :paid_at, note = :note
                  WHERE id = :id',
                [
                    ':amount'  => Money::fromCents(Money::toCents($d['amount'])),
                    ':type'    => $d['type'],
                    ':method'  => $d['method'],
                    ':paid_at' => $d['paid_at'],
                    ':note'    => $d['note'] === '' ? null : $d['note'],
                    ':id'      => $id,
                ]
            );
            return true;
        });
    }

    // ------------------------------------------------------------------
    // Hapus
    // ------------------------------------------------------------------

    /** Booking yang sudah Selesai harus tetap lunas, jadi pembayarannya tidak boleh dihapus. */
    protected function ensureDeletable(int $id): void
    {
        $payment = $this->find($id);
        if ($payment === null) {
            return;
        }

        $booking = $this->run(
            'SELECT total_price, status FROM bookings WHERE id = :id',
            [':id' => (int) $payment['booking_id']]
        )->fetch();

        if ($booking && $booking['status'] === 'done') {
            $remainingPaidC = Money::toCents($this->totalPaidFor((int) $payment['booking_id'], $id));
            if ($remainingPaidC < Money::toCents($booking['total_price'])) {
                throw new DeleteRestrictedException(
                    'Pembayaran tidak dapat dihapus karena booking sudah berstatus Selesai dan harus tetap lunas. '
                    . 'Ubah status booking terlebih dahulu bila memang perlu.'
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Internal: normalisasi, validasi, aturan nominal
    // ------------------------------------------------------------------

    /** Merapikan input mentah menjadi string bersih. */
    private function normalize(array $data): array
    {
        $s = fn(string $key): string => is_string($data[$key] ?? null) ? trim($data[$key]) : '';

        return [
            'booking_id' => $s('booking_id'),
            'amount'     => $s('amount'),
            'type'       => $s('type'),
            'method'     => $s('method'),
            'paid_at'    => $s('paid_at'),
            'note'       => $s('note'),
        ];
    }

    /** Validasi field dasar; hasil paid_at dinormalkan ke 'Y-m-d H:i:s'. */
    private function validateInput(array &$d): void
    {
        $errors = [];
        try {
            $this->validate($d, [
                'booking_id' => ['required', 'integer', 'positive'],
                'amount'     => ['required', 'decimal', 'positive'],
                'type'       => ['required', 'in:' . implode(',', array_keys(self::TYPES))],
                'method'     => ['required', 'in:' . implode(',', array_keys(self::METHODS))],
                'paid_at'    => ['required'],
                'note'       => ['max:200'],
            ], [
                'booking_id' => 'Booking', 'amount' => 'Nominal', 'type' => 'Tipe',
                'method' => 'Metode', 'paid_at' => 'Waktu bayar', 'note' => 'Catatan',
            ]);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
        }

        if (!isset($errors['paid_at'])) {
            $paidAt = $this->parsePaidAt($d['paid_at']);
            if ($paidAt === null) {
                $errors['paid_at'] = 'Waktu bayar tidak valid.';
            } elseif (strtotime($paidAt) > time() + 60) {
                $errors['paid_at'] = 'Waktu bayar tidak boleh di masa depan.';
            } else {
                $d['paid_at'] = $paidAt;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /** Mengubah input datetime-local/MySQL menjadi 'Y-m-d H:i:s'; null bila tidak valid. */
    private function parsePaidAt(string $value): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $dt = DateTime::createFromFormat($format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        return null;
    }

    /** Mengunci dan mengambil baris booking (harus di dalam transaksi). */
    private function lockBooking(int $bookingId): array
    {
        $row = $this->run(
            'SELECT id, event_name, total_price, status FROM bookings WHERE id = :id FOR UPDATE',
            [':id' => $bookingId]
        )->fetch();

        if ($row === false) {
            throw new ValidationException(['booking_id' => 'Booking tidak ditemukan.']);
        }
        return $row;
    }

    /**
     * Memastikan nominal tidak melanggar aturan tagihan.
     *
     * @param array    $booking   Baris booking terkunci.
     * @param array    $d         Input pembayaran yang sudah dinormalkan.
     * @param int|null $paymentId ID pembayaran yang sedang diubah (null saat membuat).
     * @param bool     $isCreate  Apakah ini pembayaran baru.
     */
    private function assertAmountAllowed(array $booking, array $d, ?int $paymentId, bool $isCreate): void
    {
        if ($isCreate && $booking['status'] === 'cancelled') {
            throw new ValidationException(['booking_id' => 'Booking yang dibatalkan tidak dapat menerima pembayaran.']);
        }

        $totalC       = Money::toCents($booking['total_price']);
        $othersC      = Money::toCents($this->totalPaidFor((int) $booking['id'], $paymentId));
        $amountC      = Money::toCents($d['amount']);
        $afterC       = $othersC + $amountC;
        $remainingC   = max(0, $totalC - $othersC);

        // Aturan utama: total pembayaran tidak boleh melebihi total_price.
        if ($afterC > $totalC) {
            throw new ValidationException([
                'amount' => 'Nominal melebihi sisa tagihan. Sisa tagihan saat ini: '
                    . 'Rp ' . number_format($remainingC / 100, 0, ',', '.') . '.',
            ]);
        }

        // Konsistensi tipe pembayaran.
        if ($d['type'] === 'pelunasan' && $afterC < $totalC) {
            throw new ValidationException([
                'type' => 'Pelunasan harus menutup seluruh sisa tagihan (Rp '
                    . number_format($remainingC / 100, 0, ',', '.') . '). Gunakan tipe DP untuk pembayaran sebagian.',
            ]);
        }
        if ($d['type'] === 'dp' && $afterC === $totalC) {
            throw new ValidationException([
                'type' => 'Nominal ini melunasi tagihan, silakan pilih tipe Pelunasan.',
            ]);
        }

        // Booking Selesai harus tetap lunas.
        if ($booking['status'] === 'done' && $afterC < $totalC) {
            throw new ValidationException([
                'amount' => 'Booking sudah berstatus Selesai dan harus tetap lunas.',
            ]);
        }
    }
}
