<?php
/**
 * config/autoload.php
 *
 * Autoloader sederhana: setiap class/trait dimuat otomatis saat pertama kali
 * dipakai, sehingga tidak perlu require manual satu per satu.
 * Aturan: nama file = nama class (mis. Customer -> Customer.php) dan boleh
 * berada di folder class/ maupun semua subfoldernya (core, exceptions, helpers).
 */
spl_autoload_register(function (string $className): void {
    // Peta nama class => path file, dibangun sekali lalu disimpan di variabel static.
    static $map = null;

    if ($map === null) {
        $map = [];
        $baseDir = dirname(__DIR__) . '/class';

        // Telusuri folder class/ beserta seluruh subfoldernya.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $map[$file->getBasename('.php')] = $file->getPathname();
            }
        }
    }

    // Muat file hanya jika class yang diminta ada di peta.
    if (isset($map[$className])) {
        require_once $map[$className];
    }
});
