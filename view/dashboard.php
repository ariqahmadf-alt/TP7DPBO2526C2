<?php
/**
 * view/dashboard.php
 *
 * Ringkasan bisnis. Semua angka dihitung di model (query agregat); view hanya
 * menampilkan. Variabel: $stats, $upcoming, $topBooths, $monthLabel.
 */
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <span class="page-sub"><?= Html::e($monthLabel) ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Booking bulan ini</span>
        <span class="stat-value"><?= (int) $stats['bookings_this_month'] ?></span>
        <span class="stat-note">Acara aktif (tidak termasuk batal)</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Pendapatan bulan ini</span>
        <span class="stat-value"><?= Html::e(Html::rupiah($stats['revenue_this_month'])) ?></span>
        <span class="stat-note">Pembayaran yang diterima</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Sisa tagihan</span>
        <span class="stat-value"><?= Html::e(Html::rupiah($stats['outstanding'])) ?></span>
        <span class="stat-note">Booking aktif yang belum lunas</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Unit paling sering dipakai</span>
        <span class="stat-value"><?= Html::e($topBooths[0]['code'] ?? '-') ?></span>
        <span class="stat-note"><?= isset($topBooths[0]) ? (int) $topBooths[0]['total'] . ' booking' : 'Belum ada data' ?></span>
    </div>
</div>

<div class="dashboard-grid">
    <section class="card">
        <h2>5 Booking Terdekat</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Event</th><th>Jadwal</th><th>Unit</th><th>Status</th><th>Pembayaran</th></tr>
                </thead>
                <tbody>
                <?php if (empty($upcoming)): ?>
                    <tr><td colspan="5" class="empty">Belum ada booking yang akan datang.</td></tr>
                <?php endif; ?>
                <?php foreach ($upcoming as $row): ?>
                    <tr>
                        <td>
                            <strong><?= Html::e($row['event_name']) ?></strong><br>
                            <small><?= Html::e($row['customer_name']) ?></small>
                        </td>
                        <td class="nowrap">
                            <?= Html::e(Html::date($row['event_date'])) ?><br>
                            <small><?= Html::e(Html::time($row['start_time'])) ?>&ndash;<?= Html::e(Html::time($row['end_time'])) ?></small>
                        </td>
                        <td class="nowrap"><?= Html::e($row['booth_code']) ?></td>
                        <td class="nowrap"><?= Html::bookingBadge($row['status']) ?></td>
                        <td class="nowrap">
                            <?= Html::paymentBadge($row['summary']['status']) ?>
                            <?php if ($row['summary']['status'] !== 'lunas'): ?>
                                <br><small>Sisa <?= Html::e(Html::rupiah($row['summary']['remaining'])) ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Unit Paling Sering Dipakai</h2>
        <?php if (empty($topBooths)): ?>
            <p class="empty">Belum ada data pemakaian unit.</p>
        <?php else: ?>
            <ol class="rank-list">
                <?php foreach ($topBooths as $booth): ?>
                    <li>
                        <span><strong><?= Html::e($booth['code']) ?></strong> <small><?= Html::e($booth['type']) ?></small></span>
                        <span class="badge badge-primary"><?= (int) $booth['total'] ?> booking</span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>
