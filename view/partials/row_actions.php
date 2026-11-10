<?php
/**
 * view/partials/row_actions.php
 *
 * Tombol Ubah + Hapus untuk satu baris tabel. Butuh: $currentPage dan $row['id'].
 * Hapus memakai form POST + token CSRF (bukan link GET) dan konfirmasi browser.
 */
?>
<div class="actions">
    <a class="btn btn-secondary btn-sm"
       href="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'edit', 'id' => $row['id']])) ?>">Ubah</a>

    <form method="post"
          action="<?= Html::e(Http::url(['page' => $currentPage, 'action' => 'delete'])) ?>"
          onsubmit="return confirm('Hapus data ini? Tindakan tidak dapat dibatalkan.');">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
    </form>
</div>
