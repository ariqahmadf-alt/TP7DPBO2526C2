<?php
/**
 * view/customers.php
 *
 * Tampilan entitas Customer: daftar (mode "list") dan form tambah/ubah (mode "form").
 * View ini TIDAK berisi query; data datang dari CrudController.
 * Variabel: $mode, $rows, $paginator, $item, $label, $currentPage.
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Customer</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Tambah Customer</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th><th>Kontak</th><th>Organisasi</th>
                        <th class="num">Booking</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="empty">Belum ada customer.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= Html::e($row['name']) ?></strong></td>
                        <td>
                            <?= Html::e($row['phone']) ?><br>
                            <small><?= Html::e($row['email'] ?? '-') ?></small>
                        </td>
                        <td><?= Html::e($row['organization'] ?? '-') ?></td>
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
        ['name' => 'name',         'label' => 'Nama',          'required' => true, 'attrs' => 'maxlength="100"'],
        ['name' => 'phone',        'label' => 'Nomor telepon', 'type' => 'tel', 'required' => true, 'attrs' => 'maxlength="20"', 'hint' => 'Contoh: 081234567890'],
        ['name' => 'email',        'label' => 'Email',         'type' => 'email', 'attrs' => 'maxlength="100"'],
        ['name' => 'organization', 'label' => 'Organisasi',    'attrs' => 'maxlength="100"', 'hint' => 'Opsional: sekolah, kampus, atau perusahaan.'],
    ];
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah' : 'Tambah' ?> Customer</h1></div>
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
