<?php
/**
 * view/footer.php
 *
 * Bagian bawah setiap halaman: penutup <main>, footer, dan JavaScript kecil
 * untuk membuka/menutup menu di layar sempit (hamburger).
 */
?>
</main>

<footer class="site-footer">
    <?= Html::e(APP_NAME) ?> &middot; data pada aplikasi ini adalah data contoh.
</footer>

<script>
    // Menu lipat: tombol "Menu" membuka/menutup daftar navigasi di layar sempit.
    (function () {
        var toggle = document.getElementById('nav-toggle');
        var menu = document.getElementById('nav-menu');
        if (!toggle || !menu) { return; }
        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
</body>
</html>
