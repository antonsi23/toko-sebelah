<?php
$title = "Laporan - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($role !== 'admin') {
    header('Location: home.php');
    exit;
}

$penjualan_hari_ini = query("SELECT COALESCE(SUM(total),0) AS total FROM penjualan WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['total'];
$pembelian_hari_ini = query("SELECT COALESCE(SUM(total),0) AS total FROM pembelian WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['total'];
$total_penjualan = query("SELECT COALESCE(SUM(total),0) AS total FROM penjualan")->fetch_assoc()['total'];
$total_pembelian = query("SELECT COALESCE(SUM(total),0) AS total FROM pembelian")->fetch_assoc()['total'];
$stok_rendah = query("SELECT COUNT(*) AS total FROM barang WHERE stok < 10")->fetch_assoc()['total'];
$jumlah_barang = query("SELECT COUNT(*) AS total FROM barang")->fetch_assoc()['total'];
$transaksi_terbaru = query("SELECT p.*, u.nama AS nama_user FROM penjualan p LEFT JOIN users u ON p.id_user = u.id ORDER BY p.tanggal DESC LIMIT 10");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Laporan</h4></div>
</div>

<div class="main-content">
    <div class="stats-grid">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($penjualan_hari_ini, 0, ',', '.') ?></h3>
                <p>Penjualan Hari Ini</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($pembelian_hari_ini, 0, ',', '.') ?></h3>
                <p>Pembelian Hari Ini</p>
            </div>
        </div>
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($total_penjualan, 0, ',', '.') ?></h3>
                <p>Total Penjualan</p>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-box-open"></i></div>
            <div class="stat-info">
                <h3><?= number_format($stok_rendah, 0, ',', '.') ?></h3>
                <p>Stok Rendah</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-chart-bar"></i> Ringkasan</h4>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Nilai</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Total Penjualan</td><td>Rp <?= number_format($total_penjualan, 0, ',', '.') ?></td></tr>
                <tr><td>Total Pembelian</td><td>Rp <?= number_format($total_pembelian, 0, ',', '.') ?></td></tr>
                <tr><td>Jumlah Barang</td><td><?= number_format($jumlah_barang, 0, ',', '.') ?> item</td></tr>
                <tr><td>Barang Stok Rendah</td><td><?= number_format($stok_rendah, 0, ',', '.') ?> item</td></tr>
            </tbody>
        </table>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-history"></i> 10 Transaksi Terakhir</h4>
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
                <?php while ($row = $transaksi_terbaru->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli'] ?? 'Umum') ?></td>
                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
