<?php
/**
 * view/packages.php
 *
 * Tampilan entitas Paket: daftar dan form tambah/ubah. Tanpa query SQL.
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Paket</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Tambah Paket</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th><th class="num">Harga</th><th class="num">Durasi</th>
                        <th>Deskripsi</th><th class="num">Booking</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="empty">Belum ada paket.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= Html::e($row['name']) ?></strong></td>
                        <td class="num"><?= Html::e(Html::rupiah($row['price'])) ?></td>
                        <td class="num"><?= (int) $row['duration_hours'] ?> jam</td>
                        <td><?= Html::e(Html::excerpt($row['description'], 70)) ?></td>
                        <td class="num"><?= (int) $row['booking_count'] ?></td>
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
    $formFields = [
        ['name' => 'name',           'label' => 'Nama paket', 'required' => true, 'attrs' => 'maxlength="100"'],
        ['name' => 'price',          'label' => 'Harga (Rp)', 'type' => 'number', 'required' => true, 'attrs' => 'min="0.01" step="0.01" inputmode="decimal"', 'hint' => 'Angka saja, tanpa titik ribuan.'],
        ['name' => 'duration_hours', 'label' => 'Durasi (jam)', 'type' => 'number', 'required' => true, 'attrs' => 'min="1" max="24" step="1"'],
        ['name' => 'description',    'label' => 'Deskripsi', 'type' => 'textarea', 'attrs' => 'maxlength="2000"'],
    ];
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah' : 'Tambah' ?> Paket</h1></div>
    <div class="card">
        <form method="post" class="form-grid"
              action="<?= Html::e(Http::url(['page' => $currentPage, 'action' => $isEdit ? 'update' : 'store'])) ?>">
            <?= Csrf::field() ?>
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><?php endif; ?>

            <?php foreach ($formFields as $f): require __DIR__ . '/partials/field.php'; endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-secondary" href="<?= Html::e(Http::url(['page' => $currentPage])) ?>">Batal</a>
            </div>
        </form>
    </div>
<?php endif; ?>
