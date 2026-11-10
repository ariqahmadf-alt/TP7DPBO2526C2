<?php
/**
 * DashboardController
 *
 * Menyiapkan data ringkasan untuk halaman dashboard. Semua angka diambil dari
 * model lewat query agregat/JOIN (prepared statement); view hanya menampilkan.
 */
class DashboardController
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function __construct(
        private Booking $booking,
        private Payment $payment,
        private BoothUnit $boothUnit
    ) {
    }

    /** Menghitung statistik lalu merender dashboard. */
    public function handle(): void
    {
        $now        = new DateTimeImmutable('now');
        $firstDay   = $now->modify('first day of this month');
        $nextMonth  = $firstDay->modify('+1 month');

        $stats = [
            // Booking aktif yang acaranya jatuh di bulan ini.
            'bookings_this_month' => $this->booking->countBetween(
                $firstDay->format('Y-m-d'), $nextMonth->modify('-1 day')->format('Y-m-d')
            ),
            // Pembayaran yang diterima bulan ini (booking cancelled tidak dihitung).
            'revenue_this_month' => $this->payment->revenueBetween(
                $firstDay->format('Y-m-d 00:00:00'), $nextMonth->format('Y-m-d 00:00:00')
            ),
            // Sisa tagihan seluruh booking aktif yang belum lunas.
            'outstanding' => $this->payment->totalOutstanding(),
        ];

        $upcoming  = $this->booking->upcoming(5, $now->format('Y-m-d'));
        $topBooths = $this->boothUnit->mostUsed(3);

        $monthLabel = self::MONTHS[(int) $now->format('n')] . ' ' . $now->format('Y');
        $currentPage = 'dashboard';
        $label = 'Dashboard';

        $viewDir = dirname(__DIR__, 2) . '/view/';
        require $viewDir . 'header.php';
        require $viewDir . 'dashboard.php';
        require $viewDir . 'footer.php';
    }
}
