<?php
/**
 * Paginator
 *
 * Menghitung pembagian halaman (pagination) dari total data. Dipakai bersama
 * oleh semua tampilan daftar: controller membuatnya, view hanya menampilkan.
 */
class Paginator
{
    private int $total;
    private int $perPage;
    private int $current;
    private int $pages;

    /**
     * @param int $total   Total seluruh data.
     * @param int $perPage Jumlah data per halaman.
     * @param int $current Nomor halaman yang diminta (otomatis dibatasi ke rentang valid).
     */
    public function __construct(int $total, int $perPage, int $current)
    {
        $this->total   = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->pages   = max(1, (int) ceil($this->total / $this->perPage));
        $this->current = min(max(1, $current), $this->pages);
    }

    public function total(): int   { return $this->total; }
    public function perPage(): int { return $this->perPage; }
    public function current(): int { return $this->current; }
    public function pages(): int   { return $this->pages; }

    /** Posisi awal data (OFFSET) untuk halaman saat ini. */
    public function offset(): int
    {
        return ($this->current - 1) * $this->perPage;
    }
}
