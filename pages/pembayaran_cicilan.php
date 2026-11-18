<?php
$title = "Pembayaran Cicilan - Toko Online";
require_once '../includes/header.php';

if (isset($_POST['bayar_cicilan'])) {
    $jenis = $_POST['jenis_transaksi'] ?? '';
    $id_transaksi = intval($_POST['id_transaksi'] ?? 0);
    $jumlah = filter_var($_POST['jumlah_pembayaran'] ?? null, FILTER_VALIDATE_FLOAT);

    try {
        $conn->begin_transaction();
        if (!in_array($jenis, ['penjualan', 'pembelian'], true) || $id_transaksi < 1) {
            throw new RuntimeException('Transaksi cicilan tidak valid.');
        }
        if ($jumlah === false || $jumlah <= 0) {
            throw new RuntimeException('Jumlah pembayaran harus lebih dari 0.');
        }

        if ($jenis === 'penjualan') {
            $table = 'penjualan';
            $payment_table = 'pembayaran_penjualan';
            $foreign_key = 'id_penjualan';
        } else {
            $table = 'pembelian';
            $payment_table = 'pembayaran_pembelian';
            $foreign_key = 'id_pembelian';
        }

        $transaksi_stmt = $conn->prepare("SELECT total_tagihan, dibayar, jenis_pembayaran FROM $table WHERE id = ? FOR UPDATE");
        $transaksi_stmt->bind_param('i', $id_transaksi);
        $transaksi_stmt->execute();
        $transaksi = $transaksi_stmt->get_result()->fetch_assoc();
        if (!$transaksi || $transaksi['jenis_pembayaran'] !== 'cicilan') {
            throw new RuntimeException('Transaksi cicilan tidak ditemukan.');
        }

        $sisa = (float) $transaksi['total_tagihan'] - (float) $transaksi['dibayar'];
        if ($jumlah > $sisa) {
            throw new RuntimeException('Pembayaran melebihi sisa tagihan.');
        }

        $id_user = $_SESSION['user_id'];
        $payment_stmt = $conn->prepare("INSERT INTO $payment_table ($foreign_key, id_user, jumlah) VALUES (?, ?, ?)");
        $payment_stmt->bind_param('iid', $id_transaksi, $id_user, $jumlah);
        $payment_stmt->execute();

        if ($jenis === 'pembelian') {
            $update_stmt = $conn->prepare("UPDATE pembelian SET status = IF(dibayar + ? >= total_tagihan, 'lunas', 'hutang'), dibayar = dibayar + ? WHERE id = ?");
            $update_stmt->bind_param('ddi', $jumlah, $jumlah, $id_transaksi);
        } else {
            $update_stmt = $conn->prepare('UPDATE penjualan SET dibayar = dibayar + ? WHERE id = ?');
            $update_stmt->bind_param('di', $jumlah, $id_transaksi);
        }
        $update_stmt->execute();
        $conn->commit();
        logAktivitas($id_user, 'Pembayaran Cicilan', ucfirst($jenis) . " ID: $id_transaksi, jumlah: $jumlah");
        setFlash('success', 'Pembayaran cicilan berhasil dicatat.');
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', $error instanceof RuntimeException ? $error->getMessage() : 'Gagal mencatat pembayaran cicilan.');
    }

    header('Location: pembayaran_cicilan.php');
    exit;
}

$tagihan = query("
    SELECT 'penjualan' AS jenis, p.id, p.no_transaksi AS nomor, p.nama_pembeli AS pihak,
           p.tanggal, p.tenor_bulan, p.total_tagihan, p.dibayar
    FROM penjualan p
    WHERE p.jenis_pembayaran = 'cicilan' AND p.dibayar < p.total_tagihan
    UNION ALL
    SELECT 'pembelian' AS jenis, p.id, p.no_faktur AS nomor, s.nama_supplier AS pihak,
           p.tanggal, p.tenor_bulan, p.total_tagihan, p.dibayar
    FROM pembelian p
    LEFT JOIN supplier s ON p.id_supplier = s.id
    WHERE p.jenis_pembayaran = 'cicilan' AND p.dibayar < p.total_tagihan
    ORDER BY tanggal DESC
");
$riwayat = query("
    SELECT 'Penjualan' AS jenis, t.no_transaksi AS nomor, b.tanggal, b.jumlah, u.nama AS nama_user
    FROM pembayaran_penjualan b
    JOIN penjualan t ON t.id = b.id_penjualan
    LEFT JOIN users u ON u.id = b.id_user
    WHERE t.jenis_pembayaran = 'cicilan'
    UNION ALL
    SELECT 'Pembelian' AS jenis, t.no_faktur AS nomor, b.tanggal, b.jumlah, u.nama AS nama_user
    FROM pembayaran_pembelian b
    JOIN pembelian t ON t.id = b.id_pembelian
    LEFT JOIN users u ON u.id = b.id_user
    WHERE t.jenis_pembayaran = 'cicilan'
    ORDER BY tanggal DESC
    LIMIT 50
");

require_once '../includes/sidebar.php';
?>

<div class="topbar"><div class="topbar-left"><h4>Pembayaran Cicilan</h4></div></div>
<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <h4>Tagihan Cicilan</h4>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>No. Transaksi</th>
                    <th>Pelanggan / Supplier</th>
                    <th>Tanggal</th>
                    <th>Tenor</th>
                    <th>Total Tagihan</th>
                    <th>Dibayar</th>
                    <th>Sisa</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tagihan->num_rows === 0): ?>
                <tr><td colspan="9">Tidak ada tagihan cicilan yang belum lunas.</td></tr>
                <?php endif; ?>
                <?php while ($row = $tagihan->fetch_assoc()): ?>
                <?php $sisa = max(0, (float) $row['total_tagihan'] - (float) $row['dibayar']); ?>
                <tr>
                    <td><?= ucfirst($row['jenis']) ?></td>
                    <td><?= htmlspecialchars($row['nomor']) ?></td>
                    <td><?= htmlspecialchars($row['pihak'] ?: '-') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= intval($row['tenor_bulan']) ?> bulan</td>
                    <td>Rp <?= number_format($row['total_tagihan'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($row['dibayar'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($sisa, 0, ',', '.') ?></td>
                    <td>
                        <button type="button" class="btn btn-success btn-sm open-payment-modal"
                            data-id="<?= $row['id'] ?>"
                            data-type="<?= htmlspecialchars($row['jenis'], ENT_QUOTES, 'UTF-8') ?>"
                            data-balance="<?= $sisa ?>"
                            data-name="<?= htmlspecialchars($row['nomor'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="fas fa-money-bill"></i> Bayar
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="content-card">
        <h4>Riwayat Pembayaran Cicilan (50 terakhir)</h4>
        <table class="data-table">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>No. Transaksi</th><th>Dicatat oleh</th><th>Jumlah</th></tr></thead>
            <tbody>
                <?php if ($riwayat->num_rows === 0): ?>
                <tr><td colspan="5">Belum ada pembayaran cicilan.</td></tr>
                <?php endif; ?>
                <?php while ($row = $riwayat->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($row['jenis']) ?></td>
                    <td><?= htmlspecialchars($row['nomor']) ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                    <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalPembayaran">
    <div class="modal">
        <div class="modal-header"><h4>Catat Pembayaran Cicilan</h4><button class="modal-close" onclick="closeModal('modalPembayaran')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="jenis_transaksi" class="payment-transaction-type">
                <input type="hidden" name="id_transaksi" class="payment-transaction-id">
                <p>Transaksi: <strong class="payment-transaction-name"></strong></p>
                <p>Sisa tagihan: <strong class="payment-balance"></strong></p>
                <div class="form-group"><label>Jumlah pembayaran</label><input type="number" name="jumlah_pembayaran" class="form-control payment-entry-amount" min="0.01" step="0.01" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalPembayaran')">Batal</button><button type="submit" name="bayar_cicilan" class="btn btn-primary">Simpan Pembayaran</button></div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
