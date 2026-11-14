<?php
$title = "Laporan - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($role !== 'admin') {
    header('Location: home.php');
    exit;
}

require_once '../includes/laporan_data.php';
$periode = getPeriodeLaporan($_GET);
$dataLaporan = getDataLaporan($conn, $periode);
$queryPeriode = http_build_query([
    'periode' => $periode['mode'],
    'bulan' => $periode['bulan'],
    'tanggal_mulai' => $periode['tanggal_mulai'],
    'tanggal_selesai' => $periode['tanggal_selesai'],
]);
?>

<div class="topbar">
    <div class="topbar-left"><h4>Laporan</h4></div>
    <div class="topbar-right">
        <a href="../print/laporan.php?<?= htmlspecialchars($queryPeriode, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Cetak / Simpan PDF</a>
    </div>
</div>

<div class="main-content">
    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-calendar-alt"></i> Periode Laporan: <?= htmlspecialchars($periode['label'], ENT_QUOTES, 'UTF-8') ?></h4>
        </div>
        <?php if ($periode['error']): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($periode['error'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="GET" class="filter-form" id="formPeriodeLaporan">
            <div class="form-group">
                <label for="periode">Jenis Periode</label>
                <select name="periode" id="periode" class="form-control">
                    <option value="bulan" <?= $periode['mode'] === 'bulan' ? 'selected' : '' ?>>Per Bulan</option>
                    <option value="tanggal" <?= $periode['mode'] === 'tanggal' ? 'selected' : '' ?>>Rentang Tanggal</option>
                </select>
            </div>
            <div class="form-group" data-periode-field="bulan" <?= $periode['mode'] !== 'bulan' ? 'hidden' : '' ?>>
                <label for="bulan">Bulan</label>
                <input type="month" name="bulan" id="bulan" class="form-control" value="<?= htmlspecialchars($periode['bulan'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" data-periode-field="tanggal" <?= $periode['mode'] !== 'tanggal' ? 'hidden' : '' ?>>
                <label for="tanggal_mulai">Dari Tanggal</label>
                <input type="date" name="tanggal_mulai" id="tanggal_mulai" class="form-control" value="<?= htmlspecialchars($periode['tanggal_mulai'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" data-periode-field="tanggal" <?= $periode['mode'] !== 'tanggal' ? 'hidden' : '' ?>>
                <label for="tanggal_selesai">Sampai Tanggal</label>
                <input type="date" name="tanggal_selesai" id="tanggal_selesai" class="form-control" value="<?= htmlspecialchars($periode['tanggal_selesai'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Tampilkan</button>
        </form>
    </div>

    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($dataLaporan['total_penjualan'], 0, ',', '.') ?></h3>
                <p>Penjualan Periode</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($dataLaporan['total_pembelian'], 0, ',', '.') ?></h3>
                <p>Pembelian Periode</p>
            </div>
        </div>
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <h3><?= number_format($dataLaporan['jumlah_barang'], 0, ',', '.') ?></h3>
                <p>Jumlah Barang</p>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-info">
                <h3><?= number_format($dataLaporan['stok_rendah'], 0, ',', '.') ?></h3>
                <p>Stok Rendah</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-history"></i> 10 Transaksi Penjualan Periode <?= htmlspecialchars($periode['label'], ENT_QUOTES, 'UTF-8') ?></h4>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>No Transaksi</th>
                    <th>Pelanggan</th>
                    <th>Total</th>
                    <th>Kasir</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($dataLaporan['transaksi_terbaru']->num_rows === 0): ?>
                <tr><td colspan="5">Tidak ada transaksi pada periode ini.</td></tr>
                <?php else: ?>
                <?php while ($row = $dataLaporan['transaksi_terbaru']->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli'] ?? 'Umum') ?></td>
                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const pilihanPeriode = document.getElementById('periode');
pilihanPeriode.addEventListener('change', () => {
    document.querySelectorAll('[data-periode-field]').forEach((field) => {
        field.hidden = field.dataset.periodeField !== pilihanPeriode.value;
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
