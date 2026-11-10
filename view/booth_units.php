<?php
/**
 * view/booth_units.php
 *
 * Tampilan entitas Unit Booth: daftar dan form tambah/ubah. Tanpa query SQL.
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Unit Booth</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Tambah Unit</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th><th>Tipe</th><th>Status</th>
                        <th class="num">Booking</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="empty">Belum ada unit booth.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= Html::e($row['code']) ?></strong></td>
                        <td><?= Html::e($row['type']) ?></td>
                        <td>
                            <span class="badge <?= $row['status'] === 'available' ? 'badge-success' : 'badge-pending' ?>">
                                <?= Html::e(BoothUnit::STATUSES[$row['status']] ?? $row['status']) ?>
                            </span>
                        </td>
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
        ['name' => 'code',   'label' => 'Kode unit', 'required' => true, 'attrs' => 'maxlength="20"', 'hint' => 'Harus unik, mis. BTH-004.'],
        ['name' => 'type',   'label' => 'Tipe', 'required' => true, 'attrs' => 'maxlength="50"'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'default' => 'available', 'options' => BoothUnit::STATUSES],
    ];
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah' : 'Tambah' ?> Unit Booth</h1></div>
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
