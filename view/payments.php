<?php
/**
 * view/payments.php
 *
 * Tampilan Pembayaran: daftar (mode "list") dan form catat/ubah (mode "form").
 * Status pembayaran dan sisa tagihan sudah dihitung oleh model (turunan, bukan
 * kolom database). Tanpa query SQL.
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Pembayaran</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Catat Pembayaran</a>
    </div>

    <?php if (!empty($filterBooking)): ?>
        <div class="card info-box">
            <p>
                Menampilkan pembayaran untuk <strong>#<?= (int) $filterBooking['id'] ?> <?= Html::e($filterBooking['event_name']) ?></strong>
                (<?= Html::e($filterBooking['customer_name']) ?>) &mdash;
                total <?= Html::e(Html::rupiah($filterBooking['total_price'])) ?>,
                terbayar <?= Html::e(Html::rupiah($filterBooking['summary']['paid'])) ?>,
                sisa <?= Html::e(Html::rupiah($filterBooking['summary']['remaining'])) ?>
                <?= Html::paymentBadge($filterBooking['summary']['status']) ?>
            </p>
            <a class="btn btn-secondary btn-sm" href="<?= Html::e(Http::url(['page' => $currentPage])) ?>">Tampilkan semua</a>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Waktu bayar</th><th>Booking</th><th>Tipe</th><th>Metode</th>
                        <th class="num">Nominal</th><th>Status booking</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="empty">Belum ada pembayaran.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <?= Html::e(Html::date($row['paid_at'])) ?><br>
                            <small><?= Html::e(date('H:i', strtotime($row['paid_at']))) ?></small>
                        </td>
                        <td>
                            <a href="<?= Html::e(Http::url(['page' => $currentPage, 'booking_id' => $row['booking_id']])) ?>">
                                <strong>#<?= (int) $row['booking_id'] ?> <?= Html::e($row['event_name']) ?></strong>
                            </a><br>
                            <small><?= Html::e($row['customer_name']) ?></small>
                        </td>
                        <td><?= Html::e(Payment::TYPES[$row['type']] ?? $row['type']) ?></td>
                        <td><?= Html::e(Payment::METHODS[$row['method']] ?? $row['method']) ?></td>
                        <td class="num"><?= Html::e(Html::rupiah($row['amount'])) ?></td>
                        <td>
                            <?= Html::paymentBadge($row['summary']['status']) ?>
                            <?php if ($row['summary']['status'] !== 'lunas'): ?>
                                <br><small>Sisa <?= Html::e(Html::rupiah($row['summary']['remaining'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php require __DIR__ . '/partials/row_actions.php'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php require __DIR__ . '/partials/pagination.php'; ?>

<?php else: ?>
    <?php
    $isEdit = $item !== null;
    $formFields = [];

    // Saat mencatat baru, booking dipilih dari daftar yang masih punya sisa tagihan.
    if (!$isEdit) {
        $formFields[] = [
            'name' => 'booking_id', 'label' => 'Booking', 'type' => 'select', 'required' => true,
            'default' => $defaultBookingId > 0 ? (string) $defaultBookingId : '',
            'options' => ['' => '-- Pilih booking --'] + $bookingOptions,
            'hint' => 'Hanya booking aktif yang masih punya sisa tagihan yang tampil.',
        ];
    }

    $formFields[] = ['name' => 'amount', 'label' => 'Nominal (Rp)', 'type' => 'number', 'required' => true, 'attrs' => 'min="0.01" step="0.01" inputmode="decimal"', 'hint' => 'Total pembayaran tidak boleh melebihi total tagihan booking.'];
    $formFields[] = ['name' => 'type', 'label' => 'Tipe', 'type' => 'select', 'required' => true, 'default' => 'dp', 'options' => Payment::TYPES, 'hint' => 'DP = pembayaran sebagian. Pelunasan = menutup seluruh sisa tagihan.'];
    $formFields[] = ['name' => 'method', 'label' => 'Metode', 'type' => 'select', 'required' => true, 'default' => 'transfer', 'options' => Payment::METHODS];
    $formFields[] = ['name' => 'paid_at', 'label' => 'Waktu bayar', 'type' => 'datetime-local', 'required' => true, 'default' => date('Y-m-d\TH:i')];
    $formFields[] = ['name' => 'note', 'label' => 'Catatan', 'attrs' => 'maxlength="200"'];
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah' : 'Catat' ?> Pembayaran</h1></div>

    <?php if ($isEdit && $bookingInfo): ?>
        <div class="card info-box">
            <p>
                Booking <strong>#<?= (int) $bookingInfo['id'] ?> <?= Html::e($bookingInfo['event_name']) ?></strong>
                (<?= Html::e($bookingInfo['customer_name']) ?>) &mdash;
                total <?= Html::e(Html::rupiah($bookingInfo['total_price'])) ?>,
                terbayar <?= Html::e(Html::rupiah($bookingInfo['summary']['paid'])) ?>
                <?= Html::paymentBadge($bookingInfo['summary']['status']) ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" class="form-grid"
              action="<?= Html::e(Http::url(['page' => $currentPage, 'action' => $isEdit ? 'update' : 'store'])) ?>">
            <?= Csrf::field() ?>
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="booking_id" value="<?= (int) $item['booking_id'] ?>">
            <?php endif; ?>

            <?php foreach ($formFields as $f): require __DIR__ . '/partials/field.php'; endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-secondary" href="<?= Html::e(Http::url(['page' => $currentPage])) ?>">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>
