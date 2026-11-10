<?php
/**
 * view/partials/pagination.php
 *
 * Navigasi halaman. Butuh: $paginator (Paginator) dan $currentPage (nama halaman).
 * Parameter tambahan pada URL (mis. filter) boleh dikirim lewat $paginationParams.
 */
$paginationParams = $paginationParams ?? [];
if ($paginator->pages() > 1):
    $link = fn(int $n): string => Html::e(
        Http::url(array_merge(['page' => $currentPage], $paginationParams, ['p' => $n]))
    );
?>
<nav class="pagination" aria-label="Navigasi halaman">
    <?php if ($paginator->current() > 1): ?>
        <a href="<?= $link($paginator->current() - 1) ?>" rel="prev">&laquo; Sebelumnya</a>
    <?php endif; ?>

    <?php for ($n = 1; $n <= $paginator->pages(); $n++): ?>
        <a href="<?= $link($n) ?>"
           class="<?= $n === $paginator->current() ? 'active' : '' ?>"
           <?= $n === $paginator->current() ? 'aria-current="page"' : '' ?>><?= $n ?></a>
    <?php endfor; ?>

    <?php if ($paginator->current() < $paginator->pages()): ?>
        <a href="<?= $link($paginator->current() + 1) ?>" rel="next">Berikutnya &raquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
