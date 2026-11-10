<?php
/**
 * view/bookings.php
 *
 * Tampilan Booking: daftar + filter (mode "list") dan form tambah/ubah (mode "form").
 * Tidak ada query SQL; seluruh data dan hasil hitung datang dari controller/model.
 * Variabel list : $rows, $paginator, $filters, $paginationParams, $currentPage
 * Variabel form : $item, $customerOptions, $packageOptions, $boothOptions,
 *                 $frames, $selectedFrames, $overtimeRate
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Booking</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Buat Booking</a>
    </div>

    <!-- Pencarian dan filter (GET, sehingga hasilnya bisa dibagikan lewat URL) -->
    <form method="get" action="index.php" class="card filter-bar" role="search">
        <input type="hidden" name="page" value="<?= Html::e($currentPage) ?>">

        <div class="form-group">
            <label for="flt-q">Cari event / customer</label>
            <input type="search" id="flt-q" name="q" value="<?= Html::e($filters['q']) ?>" placeholder="mis. wisuda atau Rina">
        </div>
        <div class="form-group">
            <label for="flt-status">Status</label>
            <select id="flt-status" name="status">
                <option value="">Semua status</option>
                <?php foreach (Booking::STATUSES as $value => $text): ?>
                    <option value="<?= Html::e($value) ?>"<?= $filters['status'] === $value ? ' selected' : '' ?>><?= Html::e($text) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="flt-from">Tanggal dari</label>
            <input type="date" id="flt-from" name="date_from" value="<?= Html::e($filters['date_from']) ?>">
        </div>
        <div class="form-group">
            <label for="flt-to">Tanggal sampai</label>
            <input type="date" id="flt-to" name="date_to" value="<?= Html::e($filters['date_to']) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Cari</button>
            <a class="btn btn-secondary" href="<?= Html::e(Http::url(['page' => $currentPage])) ?>">Reset</a>
        </div>
    </form>

    <p class="result-count"><?= (int) $paginator->total() ?> booking ditemukan.</p>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th><th>Jadwal</th><th>Unit</th>
                        <th class="num">Total</th><th>Status</th><th>Pembayaran</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="empty">Tidak ada booking yang cocok.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?= Html::e($row['event_name']) ?></strong><br>
                            <small>#<?= (int) $row['id'] ?> &middot; <?= Html::e($row['customer_name']) ?> &middot; <?= Html::e($row['package_name']) ?></small>
                            <?php if (!empty($row['frame_names'])): ?><br><small>Frame: <?= Html::e($row['frame_names']) ?></small><?php endif; ?>
                        </td>
                        <td class="nowrap">
                            <?= Html::e(Html::date($row['event_date'])) ?><br>
                            <small><?= Html::e(Html::time($row['start_time'])) ?>&ndash;<?= Html::e(Html::time($row['end_time'])) ?></small>
                        </td>
                        <td class="nowrap"><?= Html::e($row['booth_code']) ?></td>
                        <td class="num"><?= Html::e(Html::rupiah($row['total_price'])) ?></td>
                        <td class="nowrap"><?= Html::bookingBadge($row['status']) ?></td>
                        <td class="nowrap">
                            <?= Html::paymentBadge($row['summary']['status']) ?>
                            <?php if ($row['summary']['status'] !== 'lunas'): ?>
                                <br><small>Sisa <?= Html::e(Html::rupiah($row['summary']['remaining'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn btn-secondary btn-sm"
                                   href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'edit', 'id' => $row['id']])) ?>">Ubah</a>
                                <?php if ($row['status'] !== 'cancelled' && $row['summary']['status'] !== 'lunas'): ?>
                                    <a class="btn btn-primary btn-sm"
                                       href="<?= Html::e(Http::url(['page' => 'payments', 'action' => 'create', 'booking_id' => $row['id']])) ?>">Bayar</a>
                                <?php endif; ?>
                                <form method="post"
                                      action="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'delete'])) ?>"
                                      onsubmit="return confirm('Hapus booking ini? Tindakan tidak dapat dibatalkan.');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
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

    // Pilihan dropdown; opsi pertama kosong agar admin memilih secara sadar.
    $withBlank = fn(array $options, string $text): array => ['' => $text] + $options;
    $statusOptions = $isEdit
        ? Booking::STATUSES
        : array_intersect_key(Booking::STATUSES, array_flip(['pending', 'confirmed']));

    $formFields = [
        ['name' => 'customer_id',   'label' => 'Customer', 'type' => 'select', 'required' => true, 'options' => $withBlank($customerOptions, '-- Pilih customer --')],
        ['name' => 'package_id',    'label' => 'Paket', 'type' => 'select', 'required' => true, 'options' => $withBlank($packageOptions, '-- Pilih paket --')],
        ['name' => 'booth_unit_id', 'label' => 'Unit booth', 'type' => 'select', 'required' => true, 'options' => $withBlank($boothOptions, '-- Pilih unit --'), 'hint' => 'Unit yang sedang perawatan tidak dapat dipilih.'],
        ['name' => 'event_name',    'label' => 'Nama event', 'required' => true, 'attrs' => 'maxlength="150"'],
        ['name' => 'venue',         'label' => 'Lokasi', 'attrs' => 'maxlength="200"'],
        ['name' => 'event_date',    'label' => 'Tanggal event', 'type' => 'date', 'required' => true],
        ['name' => 'start_time',    'label' => 'Jam mulai', 'type' => 'time', 'required' => true],
        ['name' => 'end_time',      'label' => 'Jam selesai', 'type' => 'time', 'required' => true],
        ['name' => 'overtime_hours', 'label' => 'Tambahan durasi (jam)', 'type' => 'number', 'default' => '0', 'attrs' => 'min="0" max="24" step="1"', 'hint' => 'Tarif: ' . Html::rupiah($overtimeRate) . ' per jam.'],
        ['name' => 'discount',      'label' => 'Diskon (Rp)', 'type' => 'number', 'default' => '0', 'attrs' => 'min="0" step="0.01" inputmode="decimal"', 'hint' => 'Tidak boleh melebihi subtotal.'],
        ['name' => 'status',        'label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'pending', 'options' => $statusOptions,
            'hint' => $isEdit ? 'Status Selesai hanya dapat dipilih bila booking sudah lunas.' : ''],
    ];

    $frameError = Flash::fieldError('frame_ids');
    $checkedFrames = array_map('strval', (array) Flash::old('frame_ids', $selectedFrames));
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah Booking #' . (int) $item['id'] : 'Buat Booking' ?></h1></div>

    <div class="card">
        <p class="form-note">
            Total harga dihitung otomatis saat disimpan:
            <em>harga paket + biaya frame + (overtime &times; <?= Html::e(Html::rupiah($overtimeRate)) ?>) &minus; diskon</em>.
            <?php if ($isEdit): ?>
                Total saat ini: <strong><?= Html::e(Html::rupiah($item['total_price'])) ?></strong>
                (terbayar <?= Html::e(Html::rupiah($item['summary']['paid'])) ?>).
            <?php endif; ?>
        </p>

        <form method="post" class="form-grid"
              action="<?= Html::e(Http::url(['page' => $currentPage, 'action' => $isEdit ? 'update' : 'store'])) ?>">
            <?= Csrf::field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>

            <?php foreach ($formFields as $f): require __DIR__ . '/partials/field.php'; endforeach; ?>

            <fieldset class="form-group form-wide<?= $frameError ? ' has-error' : '' ?>">
                <legend>Frame</legend>
                <div class="check-grid">
                    <?php foreach ($frames as $frame): ?>
                        <label class="check">
                            <input type="checkbox" name="frame_ids[]" value="<?= (int) $frame['id'] ?>"
                                   <?= in_array((string) $frame['id'], $checkedFrames, true) ? 'checked' : '' ?>>
                            <span><?= Html::e($frame['name']) ?>
                                <small>(<?= (float) $frame['extra_price'] > 0 ? '+' . Html::e(Html::rupiah($frame['extra_price'])) : 'gratis' ?>)</small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ($frameError): ?><p class="form-error"><?= Html::e($frameError) ?></p><?php endif; ?>
            </fieldset>

            <?php
            $notesField = ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'attrs' => 'maxlength="2000"'];
            $f = $notesField; require __DIR__ . '/partials/field.php';
            ?>

            <?php if (!$isEdit): ?>
                <?php
                // DP awal opsional; dicatat dalam transaksi yang sama dengan pembuatan booking.
                $dpFields = [
                    ['name' => 'dp_amount', 'label' => 'DP awal (Rp) - opsional', 'type' => 'number', 'attrs' => 'min="0.01" step="0.01" inputmode="decimal"', 'hint' => 'Kosongkan bila belum ada pembayaran.'],
                    ['name' => 'dp_method', 'label' => 'Metode DP', 'type' => 'select', 'default' => 'transfer', 'options' => Payment::METHODS],
                ];
                foreach ($dpFields as $f): require __DIR__ . '/partials/field.php'; endforeach;
                ?>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-secondary" href="<?= Html::e(Http::url(['page' => $currentPage])) ?>">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>
