<?php
$title = "Dashboard - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Statistik
$stats = [];

// Total Penjualan Hari Ini
$r = query("SELECT COALESCE(SUM(total),0) as total FROM penjualan WHERE DATE(tanggal) = CURDATE()");
$stats['penjualan_hari_ini'] = $r->fetch_assoc()['total'];

// Total Pembelian Hari Ini
$r = query("SELECT COALESCE(SUM(total),0) as total FROM pembelian WHERE DATE(tanggal) = CURDATE()");
$stats['pembelian_hari_ini'] = $r->fetch_assoc()['total'];

// Total Barang
$r = query("SELECT COUNT(*) as total FROM barang");
$stats['total_barang'] = $r->fetch_assoc()['total'];

// Stok Rendah (< 10)
$r = query("SELECT COUNT(*) as total FROM barang WHERE stok < 10");
$stats['stok_rendah'] = $r->fetch_assoc()['total'];

// Ringkasan penjualan dan pembelian per bulan selama 12 bulan terakhir
$bulanMulai = new DateTimeImmutable('first day of this month');
$bulanMulai = $bulanMulai->modify('-11 months');
$bulanSelesai = (new DateTimeImmutable('first day of next month'));
$grafikTransaksi = [];
$labelBulan = [];
$namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

for ($i = 0; $i < 12; $i++) {
    $bulan = $bulanMulai->modify("+$i months");
    $periode = $bulan->format('Y-m');
    $labelBulan[] = $namaBulan[(int) $bulan->format('n') - 1] . ' ' . $bulan->format('Y');
    $grafikTransaksi[$periode] = ['penjualan' => 0, 'pembelian' => 0];
}

$tanggalMulai = $bulanMulai->format('Y-m-d');
$tanggalSelesai = $bulanSelesai->format('Y-m-d');
$ringkasanTransaksi = query("SELECT periode, SUM(penjualan) AS penjualan, SUM(pembelian) AS pembelian FROM (
    SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode, SUM(total) AS penjualan, 0 AS pembelian
    FROM penjualan WHERE tanggal >= '$tanggalMulai' AND tanggal < '$tanggalSelesai' GROUP BY periode
    UNION ALL
    SELECT DATE_FORMAT(tanggal, '%Y-%m') AS periode, 0 AS penjualan, SUM(total) AS pembelian
    FROM pembelian WHERE tanggal >= '$tanggalMulai' AND tanggal < '$tanggalSelesai' GROUP BY periode
) AS transaksi GROUP BY periode ORDER BY periode");

while ($row = $ringkasanTransaksi->fetch_assoc()) {
    if (isset($grafikTransaksi[$row['periode']])) {
        $grafikTransaksi[$row['periode']] = [
            'penjualan' => (float) $row['penjualan'],
            'pembelian' => (float) $row['pembelian']
        ];
    }
}

$dataPenjualan = array_column($grafikTransaksi, 'penjualan');
$dataPembelian = array_column($grafikTransaksi, 'pembelian');

// Transaksi Terbaru
$transaksi = query("SELECT p.*, u.nama as nama_user FROM penjualan p 
    LEFT JOIN users u ON p.id_user = u.id 
    ORDER BY p.tanggal DESC LIMIT 5");
?>

<!-- Topbar -->
<div class="topbar">
    <div class="topbar-left">
        <h4>Dashboard</h4>
        <span>Selamat datang, <?= htmlspecialchars($nama) ?></span>
    </div>
    <div class="topbar-right">
        <a href="logout.php" class="btn-icon" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>

    <!-- Statistik Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-cash-register"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($stats['penjualan_hari_ini'], 0, ',', '.') ?></h3>
                <p>Penjualan Hari Ini</p>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-info">
                <h3>Rp <?= number_format($stats['pembelian_hari_ini'], 0, ',', '.') ?></h3>
                <p>Pembelian Hari Ini</p>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-info">
                <h3><?= number_format($stats['total_barang'], 0, ',', '.') ?></h3>
                <p>Total Barang</p>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <h3><?= $stats['stok_rendah'] ?></h3>
                <p>Stok Rendah</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-chart-bar"></i> Grafik Transaksi 12 Bulan Terakhir</h4>
        </div>
        <div class="chart-container">
            <canvas id="grafikTransaksi" aria-label="Grafik nilai penjualan dan pembelian per bulan" role="img"></canvas>
        </div>
    </div>

    <!-- Menu Grid (Instagram Style) -->
    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-th-large"></i> Menu Utama</h4>
        </div>
        <div class="menu-grid">
            <a href="penjualan.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-cash-register"></i></div>
                <h4>Penjualan</h4>
                <p>Transaksi penjualan</p>
            </a>
            <a href="pembelian.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-shopping-cart"></i></div>
                <h4>Pembelian</h4>
                <p>Transaksi pembelian</p>
            </a>
            <a href="barang.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-box"></i></div>
                <h4>Data Barang</h4>
                <p>Kelola barang</p>
            </a>
            <?php if ($role == 'admin'): ?>
            <a href="kategori.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-tags"></i></div>
                <h4>Kategori</h4>
                <p>Kategori barang</p>
            </a>
            <a href="supplier.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-truck"></i></div>
                <h4>Supplier</h4>
                <p>Data supplier</p>
            </a>
            <a href="users.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-users"></i></div>
                <h4>Pengguna</h4>
                <p>Kelola user</p>
            </a>
            <a href="laporan.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-chart-bar"></i></div>
                <h4>Laporan</h4>
                <p>Lihat laporan</p>
            </a>
            <?php endif; ?>
            <a href="logout.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-sign-out-alt"></i></div>
                <h4>Logout</h4>
                <p>Keluar sistem</p>
            </a>
        </div>
    </div>

    <!-- Transaksi Terbaru -->
    <div class="content-card">
        <div class="card-header">
            <h4><i class="fas fa-history"></i> Transaksi Penjualan Terbaru</h4>
            <a href="penjualan.php" class="btn btn-primary btn-sm">Lihat Semua</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>No Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Total</th>
                    <th>Kasir</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $transaksi->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['no_transaksi'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli']) ?></td>
                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const canvasTransaksi = document.getElementById('grafikTransaksi');
new Chart(canvasTransaksi, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelBulan, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
        datasets: [
            {
                label: 'Penjualan',
                data: <?= json_encode($dataPenjualan) ?>,
                backgroundColor: 'rgba(67, 97, 238, 0.78)',
                borderRadius: 4
            },
            {
                label: 'Pembelian',
                data: <?= json_encode($dataPembelian) ?>,
                backgroundColor: 'rgba(46, 196, 182, 0.78)',
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            tooltip: {
                callbacks: {
                    label: context => `${context.dataset.label}: Rp ${new Intl.NumberFormat('id-ID').format(context.parsed.y)}`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: value => `Rp ${new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value)}`
                }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>