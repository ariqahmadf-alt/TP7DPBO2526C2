<?php
/**
 * view/frames.php
 *
 * Tampilan entitas Frame: daftar dan form tambah/ubah. Tanpa query SQL.
 */
?>
<?php if ($mode === 'list'): ?>
    <div class="page-head">
        <h1>Frame</h1>
        <a class="btn btn-primary" href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'create'])) ?>">+ Tambah Frame</a>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th><th>Tema</th><th class="num">Biaya tambahan</th>
                        <th class="num">Dipakai</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" class="empty">Belum ada frame.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= Html::e($row['name']) ?></strong></td>
                        <td><?= Html::e($row['theme'] ?? '-') ?></td>
                        <td class="num"><?= (float) $row['extra_price'] > 0 ? Html::e(Html::rupiah($row['extra_price'])) : 'Gratis' ?></td>
                        <td class="num"><?= (int) $row['booking_count'] ?> booking</td>
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
        ['name' => 'name',        'label' => 'Nama frame', 'required' => true, 'attrs' => 'maxlength="100"'],
        ['name' => 'theme',       'label' => 'Tema', 'attrs' => 'maxlength="50"', 'hint' => 'Contoh: Vintage, Pop Art, Minimalis.'],
        ['name' => 'extra_price', 'label' => 'Biaya tambahan (Rp)', 'type' => 'number', 'default' => '0', 'attrs' => 'min="0" step="0.01" inputmode="decimal"', 'hint' => 'Isi 0 bila frame tidak menambah biaya.'],
    ];
    ?>
    <div class="page-head"><h1><?= $isEdit ? 'Ubah' : 'Tambah' ?> Frame</h1></div>
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
