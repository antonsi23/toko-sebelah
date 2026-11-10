<?php
session_start();
require_once '../includes/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT p.*, u.nama AS nama_user FROM penjualan p LEFT JOIN users u ON p.id_user = u.id WHERE p.id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();

if (!$invoice) {
    http_response_code(404);
    echo 'Invoice tidak ditemukan.';
    exit;
}

$detail_stmt = $conn->prepare('SELECT d.jumlah, d.harga_jual, d.subtotal, b.nama_barang, b.satuan FROM detail_penjualan d LEFT JOIN barang b ON d.id_barang = b.id WHERE d.id_penjualan = ? ORDER BY d.id');
$detail_stmt->bind_param('i', $id);
$detail_stmt->execute();
$details = $detail_stmt->get_result();
$escapeHtml = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= $escapeHtml($invoice['no_transaksi']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/invoice.css">
</head>
<body>
    <div class="toolbar">
        <a href="../pages/penjualan.php">Kembali ke histori</a>
        <button class="print-button" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    </div>
    <main class="invoice">
        <header class="invoice-header">
            <div>
                <h1 class="brand">Toko Online</h1>
                <div class="muted">Bukti transaksi penjualan</div>
            </div>
            <div>
                <h2 class="invoice-title">INVOICE</h2>
                <div class="invoice-number"><?= $escapeHtml($invoice['no_transaksi']) ?></div>
            </div>
        </header>

        <section class="meta">
            <div>
                <h2>Data Pembeli</h2>
                <p><strong><?= $escapeHtml($invoice['nama_pembeli'] ?: 'Umum') ?></strong></p>
                <?php if (!empty($invoice['telepon_pembeli'])): ?><p>Telepon: <?= $escapeHtml($invoice['telepon_pembeli']) ?></p><?php endif; ?>
                <?php if (!empty($invoice['email_pembeli'])): ?><p>Email: <?= $escapeHtml($invoice['email_pembeli']) ?></p><?php endif; ?>
            </div>
            <div>
                <h2>Informasi Transaksi</h2>
                <p>Tanggal: <strong><?= date('d/m/Y H:i', strtotime($invoice['tanggal'])) ?></strong></p>
                <p>Kasir: <?= $escapeHtml($invoice['nama_user'] ?? '-') ?></p>
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
                    <td class="numeric">Rp <?= number_format($detail['harga_jual'], 0, ',', '.') ?></td>
                    <td class="numeric">Rp <?= number_format($detail['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <table class="summary">
            <tr class="grand-total"><td>Total</td><td class="numeric">Rp <?= number_format($invoice['total'], 0, ',', '.') ?></td></tr>
            <tr><td>Bayar</td><td class="numeric">Rp <?= number_format($invoice['bayar'], 0, ',', '.') ?></td></tr>
            <tr><td>Kembalian</td><td class="numeric">Rp <?= number_format($invoice['kembalian'], 0, ',', '.') ?></td></tr>
        </table>

        <footer class="footer">Simpan invoice ini sebagai bukti transaksi dan referensi garansi.</footer>
    </main>
</body>
</html>