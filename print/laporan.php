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
$dataLaporan = getDataLaporan($conn, $periode);
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
    <title>Laporan Toko - <?= $escapeHtml($periode['label']) ?></title>
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
                <div class="muted">Ringkasan laporan toko</div>
            </div>
            <div>
                <h2 class="invoice-title">LAPORAN</h2>
                <div class="invoice-number">Periode <?= $escapeHtml($periode['label']) ?></div>
            </div>
        </header>

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

        <footer class="footer">Laporan dicetak pada <?= date('d/m/Y H:i') ?>.</footer>
    </main>
</body>
</html>