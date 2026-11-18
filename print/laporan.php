<?php
session_start();
require_once '../includes/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Anda tidak memiliki akses ke laporan.';
    exit;
}

require_once '../includes/laporan_data.php';
$periode = getPeriodeLaporan($_GET);
$jenis = $_GET['jenis'] ?? 'ringkasan';
if (!in_array($jenis, ['ringkasan', 'penjualan', 'pembelian', 'stok'], true)) {
    http_response_code(400);
    echo 'Jenis laporan tidak valid.';
    exit;
}
$namaLaporan = [
    'ringkasan' => 'Ringkasan Laporan',
    'penjualan' => 'Laporan Penjualan Barang',
    'pembelian' => 'Laporan Pembelian Barang',
    'stok' => 'Laporan Stok Barang',
][$jenis];
$dataLaporan = null;
$items = null;
$statistik = null;

if ($jenis === 'ringkasan') {
    $dataLaporan = getDataLaporan($conn, $periode);
} elseif ($jenis === 'penjualan') {
    $stmt = $conn->prepare('SELECT p.no_transaksi, p.tanggal, p.nama_pembeli, d.jumlah, d.harga_jual, d.subtotal, b.kode_barang, b.nama_barang FROM penjualan p JOIN detail_penjualan d ON d.id_penjualan = p.id LEFT JOIN barang b ON b.id = d.id_barang WHERE p.tanggal >= ? AND p.tanggal < ? ORDER BY p.tanggal, p.no_transaksi, d.id');
    $stmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $stmt->execute();
    $items = $stmt->get_result();

    $statsStmt = $conn->prepare('SELECT COUNT(DISTINCT p.id) AS jumlah_transaksi, COALESCE(SUM(d.jumlah), 0) AS jumlah_barang, COALESCE(SUM(d.subtotal), 0) AS total FROM penjualan p JOIN detail_penjualan d ON d.id_penjualan = p.id WHERE p.tanggal >= ? AND p.tanggal < ?');
    $statsStmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $statsStmt->execute();
    $statistik = $statsStmt->get_result()->fetch_assoc();
} elseif ($jenis === 'pembelian') {
    $stmt = $conn->prepare('SELECT p.no_faktur, p.tanggal, s.nama_supplier, d.jumlah, d.harga_beli, d.subtotal, b.kode_barang, b.nama_barang FROM pembelian p JOIN detail_pembelian d ON d.id_pembelian = p.id LEFT JOIN supplier s ON s.id = p.id_supplier LEFT JOIN barang b ON b.id = d.id_barang WHERE p.tanggal >= ? AND p.tanggal < ? ORDER BY p.tanggal, p.no_faktur, d.id');
    $stmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $stmt->execute();
    $items = $stmt->get_result();

    $statsStmt = $conn->prepare('SELECT COUNT(DISTINCT p.id) AS jumlah_transaksi, COALESCE(SUM(d.jumlah), 0) AS jumlah_barang, COALESCE(SUM(d.subtotal), 0) AS total FROM pembelian p JOIN detail_pembelian d ON d.id_pembelian = p.id WHERE p.tanggal >= ? AND p.tanggal < ?');
    $statsStmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $statsStmt->execute();
    $statistik = $statsStmt->get_result()->fetch_assoc();
} else {
    $items = $conn->query('SELECT b.kode_barang, b.nama_barang, k.nama_kategori, b.stok, b.satuan, b.harga_beli, b.harga_jual, (b.stok * b.harga_beli) AS nilai_stok FROM barang b LEFT JOIN kategori k ON k.id = b.id_kategori ORDER BY b.nama_barang');
}
$queryPeriode = http_build_query([
    'periode' => $periode['mode'],
    'bulan' => $periode['bulan'],
    'tanggal_mulai' => $periode['tanggal_mulai'],
    'tanggal_selesai' => $periode['tanggal_selesai'],
]);
$escapeHtml = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escapeHtml($namaLaporan) ?> - <?= $escapeHtml($periode['label']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/invoice.css">
    <style>
        .report-label {
            margin: 28px 0 10px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(29, 53, 87, .1);
            font-size: 11px;
            text-align: left;
        }

        .report-table th {
            background: rgba(67, 97, 238, .08);
            font-size: 10px;
            text-transform: uppercase;
        }

        .report-table .numeric {
            text-align: right;
        }

        @media print {
            .report-label {
                break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="../pages/laporan.php?<?= $escapeHtml($queryPeriode) ?>">Kembali ke laporan</a>
        <button class="print-button" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main class="invoice">
        <header class="invoice-header">
            <div>
                <h1 class="brand">Toko Sebelah</h1>
                <div class="muted"><?= $escapeHtml($namaLaporan) ?></div>
            </div>
            <div>
                <h2 class="invoice-title">LAPORAN</h2>
                <div class="invoice-number"><?= $jenis === 'stok' ? 'Stok per ' . date('d/m/Y') : 'Periode ' . $escapeHtml($periode['label']) ?></div>
            </div>
        </header>

        <?php if ($jenis === 'ringkasan'): ?>
        <section class="meta">
            <div>
                <h2>Total Periode</h2>
                <p>Penjualan: <strong>Rp <?= number_format($dataLaporan['total_penjualan'], 0, ',', '.') ?></strong></p>
                <p>Pembelian: <strong>Rp <?= number_format($dataLaporan['total_pembelian'], 0, ',', '.') ?></strong></p>
            </div>
            <div>
                <h2>Inventaris</h2>
                <p>Jumlah barang saat ini: <strong><?= number_format($dataLaporan['jumlah_barang'], 0, ',', '.') ?> item</strong></p>
                <p>Barang stok rendah saat ini: <strong><?= number_format($dataLaporan['stok_rendah'], 0, ',', '.') ?> item</strong></p>
            </div>
        </section>

        <h2 class="report-label">10 Transaksi Penjualan Periode <?= $escapeHtml($periode['label']) ?></h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Pelanggan</th>
                    <th class="numeric">Total</th>
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
                        <td><?= $escapeHtml($row['no_transaksi']) ?></td>
                        <td><?= $escapeHtml($row['nama_pembeli'] ?? 'Umum') ?></td>
                        <td class="numeric">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                        <td><?= $escapeHtml($row['nama_user'] ?? '-') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php elseif ($jenis === 'penjualan'): ?>
        <section class="meta">
            <div>
                <h2>Ringkasan Penjualan</h2>
                <p>Jumlah transaksi: <strong><?= number_format($statistik['jumlah_transaksi'], 0, ',', '.') ?></strong></p>
                <p>Barang terjual: <strong><?= number_format($statistik['jumlah_barang'], 0, ',', '.') ?></strong></p>
            </div>
            <div>
                <h2>Periode</h2>
                <p><?= $escapeHtml($periode['label']) ?></p>
                <p>Total penjualan barang: <strong>Rp <?= number_format($statistik['total'], 0, ',', '.') ?></strong></p>
            </div>
        </section>
        <h2 class="report-label">Rincian Penjualan Barang</h2>
        <table class="report-table">
            <thead><tr><th>No. Transaksi</th><th>Tanggal</th><th>Pelanggan</th><th>Kode</th><th>Barang</th><th class="numeric">Jumlah</th><th class="numeric">Harga</th><th class="numeric">Subtotal</th></tr></thead>
            <tbody>
                <?php if ($items->num_rows === 0): ?><tr><td colspan="8">Tidak ada penjualan pada periode ini.</td></tr><?php endif; ?>
                <?php while ($row = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= $escapeHtml($row['no_transaksi']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= $escapeHtml($row['nama_pembeli'] ?? 'Umum') ?></td>
                    <td><?= $escapeHtml($row['kode_barang'] ?? '-') ?></td>
                    <td><?= $escapeHtml($row['nama_barang'] ?? 'Barang dihapus') ?></td>
                    <td class="numeric"><?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php elseif ($jenis === 'pembelian'): ?>
        <section class="meta">
            <div>
                <h2>Ringkasan Pembelian</h2>
                <p>Jumlah transaksi: <strong><?= number_format($statistik['jumlah_transaksi'], 0, ',', '.') ?></strong></p>
                <p>Barang dibeli: <strong><?= number_format($statistik['jumlah_barang'], 0, ',', '.') ?></strong></p>
            </div>
            <div>
                <h2>Periode</h2>
                <p><?= $escapeHtml($periode['label']) ?></p>
                <p>Total pembelian barang: <strong>Rp <?= number_format($statistik['total'], 0, ',', '.') ?></strong></p>
            </div>
        </section>
        <h2 class="report-label">Rincian Pembelian Barang</h2>
        <table class="report-table">
            <thead><tr><th>No. Faktur</th><th>Tanggal</th><th>Supplier</th><th>Kode</th><th>Barang</th><th class="numeric">Jumlah</th><th class="numeric">Harga</th><th class="numeric">Subtotal</th></tr></thead>
            <tbody>
                <?php if ($items->num_rows === 0): ?><tr><td colspan="8">Tidak ada pembelian pada periode ini.</td></tr><?php endif; ?>
                <?php while ($row = $items->fetch_assoc()): ?>
                <tr>
                    <td><?= $escapeHtml($row['no_faktur']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= $escapeHtml($row['nama_supplier'] ?? '-') ?></td>
                    <td><?= $escapeHtml($row['kode_barang'] ?? '-') ?></td>
                    <td><?= $escapeHtml($row['nama_barang'] ?? 'Barang dihapus') ?></td>
                    <td class="numeric"><?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <?php $jumlahStok = 0; $nilaiStok = 0; ?>
        <section class="meta">
            <div><h2>Persediaan Saat Ini</h2><p>Jumlah jenis barang: <strong><?= number_format($items->num_rows, 0, ',', '.') ?></strong></p></div>
            <div><h2>Stok dicatat per <?= date('d/m/Y') ?></h2></div>
        </section>
        <h2 class="report-label">Daftar Stok Barang</h2>
        <table class="report-table">
            <thead><tr><th>Kode</th><th>Barang</th><th>Kategori</th><th class="numeric">Stok</th><th>Satuan</th><th class="numeric">Harga Beli</th><th class="numeric">Harga Jual</th><th class="numeric">Nilai Stok</th></tr></thead>
            <tbody>
                <?php if ($items->num_rows === 0): ?><tr><td colspan="8">Belum ada data barang.</td></tr><?php endif; ?>
                <?php while ($row = $items->fetch_assoc()): $jumlahStok += (int) $row['stok']; $nilaiStok += (float) $row['nilai_stok']; ?>
                <tr>
                    <td><?= $escapeHtml($row['kode_barang']) ?></td>
                    <td><?= $escapeHtml($row['nama_barang']) ?></td>
                    <td><?= $escapeHtml($row['nama_kategori'] ?? '-') ?></td>
                    <td class="numeric"><?= number_format($row['stok'], 0, ',', '.') ?></td>
                    <td><?= $escapeHtml($row['satuan'] ?? '-') ?></td>
                    <td class="numeric">Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($row['nilai_stok'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot><tr><th colspan="3">Total</th><th class="numeric"><?= number_format($jumlahStok, 0, ',', '.') ?></th><th colspan="3"></th><th class="numeric">Rp <?= number_format($nilaiStok, 0, ',', '.') ?></th></tr></tfoot>
        </table>
        <?php endif; ?>

        <footer class="footer">Laporan dicetak pada <?= date('d/m/Y H:i') ?>.</footer>
    </main>
</body>
</html>