<?php
session_start();
require_once '../includes/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT p.*, s.nama_supplier, s.alamat, s.telepon, u.nama AS nama_user FROM pembelian p LEFT JOIN supplier s ON p.id_supplier = s.id LEFT JOIN users u ON p.id_user = u.id WHERE p.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice tidak ditemukan.';
    exit;
}

$detail_stmt = $conn->prepare('SELECT d.jumlah, d.harga_beli, d.subtotal, b.nama_barang, b.satuan FROM detail_pembelian d LEFT JOIN barang b ON d.id_barang = b.id WHERE d.id_pembelian = ? ORDER BY d.id');
$detail_stmt->bind_param('i', $id);
$detail_stmt->execute();
$details = $detail_stmt->get_result();
$payment_stmt = $conn->prepare('SELECT p.tanggal, p.jumlah, u.nama AS nama_user FROM pembayaran_pembelian p LEFT JOIN users u ON p.id_user = u.id WHERE p.id_pembelian = ? ORDER BY p.tanggal, p.id');
$payment_stmt->bind_param('i', $id);
$payment_stmt->execute();
$payments = $payment_stmt->get_result();
$escapeHtml = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $escapeHtml($invoice['no_faktur']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/invoice.css">
</head>
<body>
    <div class="toolbar"><button class="print-button" type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main class="invoice">
        <header class="invoice-header">
            <div>
                <h1 class="brand">Toko Online</h1>
                <div class="muted">Invoice pembelian barang</div>
            </div>
            <div>
                <h2 class="invoice-title">INVOICE</h2>
                <div class="invoice-number"><?= $escapeHtml($invoice['no_faktur']) ?></div>
            </div>
        </header>

        <section class="meta">
            <div>
                <h2>Supplier</h2>
                <p><strong><?= $escapeHtml($invoice['nama_supplier'] ?? '-') ?></strong></p>
                <?php if (!empty($invoice['alamat'])): ?><p class="muted"><?= nl2br($escapeHtml($invoice['alamat'])) ?></p><?php endif; ?>
                <?php if (!empty($invoice['telepon'])): ?><p class="muted">Telp. <?= $escapeHtml($invoice['telepon']) ?></p><?php endif; ?>
            </div>
            <div>
                <h2>Informasi Transaksi</h2>
                <p>Tanggal: <strong><?= date('d/m/Y H:i', strtotime($invoice['tanggal'])) ?></strong></p>
                <p>Status: <strong><?= $escapeHtml(ucfirst($invoice['status'])) ?></strong></p>
                <p>Dicatat oleh: <?= $escapeHtml($invoice['nama_user'] ?? '-') ?></p>
            </div>
        </section>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Barang</th>
                    <th class="numeric">Jumlah</th>
                    <th class="numeric">Harga</th>
                    <th class="numeric">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($detail = $details->fetch_assoc()): ?>
                <tr>
                    <td><?= $escapeHtml($detail['nama_barang'] ?? 'Barang dihapus') ?></td>
                    <td class="numeric"><?= number_format($detail['jumlah'], 0, ',', '.') ?> <?= $escapeHtml($detail['satuan'] ?? '') ?></td>
                    <td class="numeric">Rp <?= number_format($detail['harga_beli'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($detail['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <table class="summary">
            <tr><td>Total barang</td><td class="numeric">Rp <?= number_format($invoice['total'], 0, ',', '.') ?></td></tr>
            <?php if ($invoice['jenis_pembayaran'] === 'cicilan'): ?>
            <tr><td>Bunga cicilan (<?= intval($invoice['tenor_bulan']) ?> bulan, <?= number_format($invoice['bunga_persen'], 2, ',', '.') ?>%)</td><td class="numeric">Rp <?= number_format($invoice['total_tagihan'] - $invoice['total'], 0, ',', '.') ?></td></tr>
            <?php endif; ?>
            <tr class="grand-total"><td>Total tagihan</td><td class="numeric">Rp <?= number_format($invoice['total_tagihan'], 0, ',', '.') ?></td></tr>
            <?php if ($invoice['jenis_pembayaran'] === 'cicilan'): ?>
            <tr><td>Cicilan per bulan</td><td class="numeric">Rp <?= number_format($invoice['total_tagihan'] / $invoice['tenor_bulan'], 0, ',', '.') ?></td></tr>
            <?php endif; ?>
            <tr><td>Sudah dibayar</td><td class="numeric">Rp <?= number_format($invoice['dibayar'], 0, ',', '.') ?></td></tr>
            <tr><td>Sisa tagihan</td><td class="numeric">Rp <?= number_format(max(0, $invoice['total_tagihan'] - $invoice['dibayar']), 0, ',', '.') ?></td></tr>
        </table>

        <?php if ($invoice['jenis_pembayaran'] === 'cicilan'): ?>
        <h2>Riwayat Pembayaran</h2>
        <table class="items-table"><thead><tr><th>Tanggal</th><th>Dicatat oleh</th><th class="numeric">Jumlah</th></tr></thead><tbody>
            <?php while ($payment = $payments->fetch_assoc()): ?>
            <tr><td><?= date('d/m/Y H:i', strtotime($payment['tanggal'])) ?></td><td><?= $escapeHtml($payment['nama_user'] ?? '-') ?></td><td class="numeric">Rp <?= number_format($payment['jumlah'], 0, ',', '.') ?></td></tr>
            <?php endwhile; ?>
        </tbody></table>
        <?php endif; ?>

        <footer class="footer">Terima kasih. Invoice ini dibuat secara otomatis oleh sistem.</footer>
    </main>
</body>
</html>