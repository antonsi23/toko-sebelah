<?php
$title = "Penjualan - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

function getInstallmentRates(mysqli $conn): array {
    $result = $conn->query('SELECT bunga_3_bulan, bunga_6_bulan, bunga_12_bulan FROM pengaturan_cicilan WHERE id = 1');
    $settings = $result->fetch_assoc();
    return [
        3 => (float) ($settings['bunga_3_bulan'] ?? 0),
        6 => (float) ($settings['bunga_6_bulan'] ?? 0),
        12 => (float) ($settings['bunga_12_bulan'] ?? 0),
    ];
}

$bunga_tenor = getInstallmentRates($conn);

if (isset($_POST['bayar_cicilan'])) {
    $id_penjualan = intval($_POST['id_penjualan'] ?? 0);
    $jumlah_bayar = filter_var($_POST['jumlah_pembayaran'] ?? null, FILTER_VALIDATE_FLOAT);
    try {
        $conn->begin_transaction();
        if ($jumlah_bayar === false || $jumlah_bayar <= 0) {
            throw new RuntimeException('Jumlah pembayaran harus lebih dari 0.');
        }
        $transaksi_stmt = $conn->prepare('SELECT total_tagihan, dibayar, jenis_pembayaran FROM penjualan WHERE id = ? FOR UPDATE');
        $transaksi_stmt->bind_param('i', $id_penjualan);
        $transaksi_stmt->execute();
        $transaksi = $transaksi_stmt->get_result()->fetch_assoc();
        if (!$transaksi || $transaksi['jenis_pembayaran'] !== 'cicilan') {
            throw new RuntimeException('Transaksi cicilan tidak ditemukan.');
        }
        $sisa = (float) $transaksi['total_tagihan'] - (float) $transaksi['dibayar'];
        if ($jumlah_bayar > $sisa) {
            throw new RuntimeException('Pembayaran melebihi sisa tagihan.');
        }
        $id_user = $_SESSION['user_id'];
        $insert = $conn->prepare('INSERT INTO pembayaran_penjualan (id_penjualan, id_user, jumlah) VALUES (?, ?, ?)');
        $insert->bind_param('iid', $id_penjualan, $id_user, $jumlah_bayar);
        $insert->execute();
        $update = $conn->prepare('UPDATE penjualan SET dibayar = dibayar + ? WHERE id = ?');
        $update->bind_param('di', $jumlah_bayar, $id_penjualan);
        $update->execute();
        $conn->commit();
        setFlash('success', 'Pembayaran cicilan berhasil dicatat.');
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', $error instanceof RuntimeException ? $error->getMessage() : 'Gagal mencatat pembayaran cicilan.');
    }
    header('Location: penjualan.php');
    exit;
}

if (isset($_POST['tambah'])) {
    $no_transaksi = escape($_POST['no_transaksi']);
    $nama_pembeli = escape($_POST['nama_pembeli']);
    $telepon_pembeli = escape(trim($_POST['telepon_pembeli'] ?? ''));
    $email_pembeli = trim($_POST['email_pembeli'] ?? '');
    if ($email_pembeli !== '' && !filter_var($email_pembeli, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'Format email pembeli tidak valid.');
        header('Location: penjualan.php');
        exit;
    }
    $email_pembeli = escape($email_pembeli);
    $jenis_pembayaran = ($_POST['jenis_pembayaran'] ?? '') === 'cicilan' ? 'cicilan' : 'tunai';
    $tenor_bulan = $jenis_pembayaran === 'cicilan' ? intval($_POST['tenor_bulan'] ?? 0) : 0;
    $bayar = filter_var($_POST['bayar'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($bayar === false || $bayar < 0) {
        setFlash('danger', 'Jumlah bayar harus berupa angka yang tidak negatif.');
        header('Location: penjualan.php');
        exit;
    }
    $id_user = $_SESSION['user_id'];
    $jumlah_barang = [];

    foreach ($_POST['id_barang'] ?? [] as $index => $id_barang) {
        $id_barang = intval($id_barang);
        $jumlah = intval($_POST['jumlah'][$index] ?? 0);
        if ($id_barang > 0 && $jumlah > 0) {
            $jumlah_barang[$id_barang] = ($jumlah_barang[$id_barang] ?? 0) + $jumlah;
        }
    }

    if (!$jumlah_barang) {
        setFlash('danger', 'Pilih minimal satu barang dan isi jumlahnya.');
        header('Location: penjualan.php');
        exit;
    }

    if ($no_transaksi === '') {
        $no_transaksi = 'TRX-' . date('YmdHis');
    }

    try {
        $conn->begin_transaction();
        $barang_stmt = $conn->prepare('SELECT id, nama_barang, stok, harga_jual FROM barang WHERE id = ? FOR UPDATE');
        $detail_barang = [];
        $total = 0;

        foreach ($jumlah_barang as $id_barang => $jumlah) {
            $barang_stmt->bind_param('i', $id_barang);
            $barang_stmt->execute();
            $barang = $barang_stmt->get_result()->fetch_assoc();
            if (!$barang || $barang['stok'] < $jumlah) {
                throw new RuntimeException('Stok barang tidak cukup untuk ' . ($barang['nama_barang'] ?? 'barang yang dipilih') . '.');
            }
            $subtotal = $barang['harga_jual'] * $jumlah;
            $total += $subtotal;
            $detail_barang[] = ['id' => $id_barang, 'jumlah' => $jumlah, 'harga' => $barang['harga_jual'], 'subtotal' => $subtotal];
        }

        $bunga_persen = 0.0;
        if ($jenis_pembayaran === 'cicilan') {
            if (!isset($bunga_tenor[$tenor_bulan])) {
                throw new RuntimeException('Pilih tenor cicilan 3, 6, atau 12 bulan.');
            }
            $bunga_persen = $bunga_tenor[$tenor_bulan];
        }

        $total_tagihan = round($total * (1 + $bunga_persen / 100), 2);
        if ($jenis_pembayaran === 'tunai' && $bayar < $total_tagihan) {
            throw new RuntimeException('Jumlah bayar kurang dari total transaksi.');
        }
        if ($jenis_pembayaran === 'cicilan' && $bayar > $total_tagihan) {
            throw new RuntimeException('Uang muka melebihi total tagihan.');
        }
        $dibayar = $jenis_pembayaran === 'tunai' ? $total_tagihan : $bayar;
        $kembalian = $jenis_pembayaran === 'tunai' ? $bayar - $total_tagihan : 0;
        $stmt = $conn->prepare('INSERT INTO penjualan (no_transaksi, id_user, tanggal, total, total_tagihan, jenis_pembayaran, tenor_bulan, bunga_persen, bayar, dibayar, kembalian, nama_pembeli, telepon_pembeli, email_pembeli) VALUES (?, ?, NOW(), ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('siddsiddddsss', $no_transaksi, $id_user, $total, $total_tagihan, $jenis_pembayaran, $tenor_bulan, $bunga_persen, $bayar, $dibayar, $kembalian, $nama_pembeli, $telepon_pembeli, $email_pembeli);
        $stmt->execute();
        $id_penjualan = $conn->insert_id;

        if ($dibayar > 0) {
            $payment_stmt = $conn->prepare('INSERT INTO pembayaran_penjualan (id_penjualan, id_user, jumlah) VALUES (?, ?, ?)');
            $payment_stmt->bind_param('iid', $id_penjualan, $id_user, $dibayar);
            $payment_stmt->execute();
        }

        $detail_stmt = $conn->prepare('INSERT INTO detail_penjualan (id_penjualan, id_barang, jumlah, harga_jual, subtotal) VALUES (?, ?, ?, ?, ?)');
        $stok_stmt = $conn->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?');
        foreach ($detail_barang as $detail) {
            $detail_stmt->bind_param('iiidd', $id_penjualan, $detail['id'], $detail['jumlah'], $detail['harga'], $detail['subtotal']);
            $detail_stmt->execute();
            $stok_stmt->bind_param('ii', $detail['jumlah'], $detail['id']);
            $stok_stmt->execute();
        }
        $conn->commit();
        logAktivitas($_SESSION['user_id'], 'Tambah Penjualan', "No transaksi: $no_transaksi");
        header('Location: ../print/penjualan.php?id=' . $id_penjualan);
        exit;
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', $error instanceof RuntimeException ? $error->getMessage() : 'Gagal menyimpan transaksi penjualan.');
    }
    header('Location: penjualan.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    try {
        $conn->begin_transaction();
        $detail_stmt = $conn->prepare('SELECT id_barang, jumlah FROM detail_penjualan WHERE id_penjualan = ? FOR UPDATE');
        $detail_stmt->bind_param('i', $id);
        $detail_stmt->execute();
        $details = $detail_stmt->get_result();
        $stok_stmt = $conn->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?');
        while ($detail = $details->fetch_assoc()) {
            $stok_stmt->bind_param('ii', $detail['jumlah'], $detail['id_barang']);
            $stok_stmt->execute();
        }
        $delete_stmt = $conn->prepare('DELETE FROM penjualan WHERE id = ?');
        $delete_stmt->bind_param('i', $id);
        $delete_stmt->execute();
        $conn->commit();
        setFlash('success', 'Transaksi berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus Penjualan', "Hapus transaksi ID: $id");
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', 'Gagal menghapus transaksi!');
    }
    header('Location: penjualan.php');
    exit;
}

$transaksi = query("SELECT p.*, u.nama AS nama_user FROM penjualan p LEFT JOIN users u ON p.id_user = u.id ORDER BY p.tanggal DESC");
$barang_list = query('SELECT id, kode_barang, nama_barang, harga_jual, stok, satuan FROM barang WHERE stok > 0 ORDER BY nama_barang');
?>

<div class="topbar">
    <div class="topbar-left"><h4>Penjualan</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus"></i> Transaksi Baru</button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No Transaksi</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Telepon</th>
                    <th>Email</th>
                    <th>Total</th>
                    <th>Sudah Dibayar</th>
                    <th>Pembayaran</th>
                    <th>Kembalian</th>
                    <th>Kasir</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $transaksi->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($row['nama_pembeli'] ?? 'Umum') ?></td>
                    <td><?= htmlspecialchars($row['telepon_pembeli'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['email_pembeli'] ?? '-') ?></td>
                    <td>Rp <?= number_format($row['total_tagihan'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($row['dibayar'], 0, ',', '.') ?></td>
                    <td><?= $row['jenis_pembayaran'] === 'cicilan' ? 'Cicilan ' . intval($row['tenor_bulan']) . ' bln' : 'Tunai' ?></td>
                    <td>Rp <?= number_format($row['kembalian'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                    <td>
                        <?php if ($row['jenis_pembayaran'] === 'cicilan' && $row['dibayar'] < $row['total_tagihan']): ?>
                        <button type="button" class="btn btn-success btn-sm open-payment-modal" data-id="<?= $row['id'] ?>" data-balance="<?= max(0, $row['total_tagihan'] - $row['dibayar']) ?>" data-name="<?= htmlspecialchars($row['no_transaksi'], ENT_QUOTES, 'UTF-8') ?>" title="Catat pembayaran cicilan"><i class="fas fa-money-bill"></i></button>
                        <?php endif; ?>
                        <a href="../print/penjualan.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Cetak atau simpan PDF" aria-label="Cetak atau simpan PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus transaksi ini?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Transaksi Penjualan</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST" data-inventory-form data-installment-form>
            <div class="modal-body">
                <div class="form-group"><label>No. Transaksi</label><input type="text" name="no_transaksi" class="form-control" placeholder="Opsional"></div>
                <div class="form-group"><label>Nama Pembeli</label><input type="text" name="nama_pembeli" class="form-control" value="Umum" required></div>
                <div class="form-group"><label>Nomor Telepon</label><input type="tel" name="telepon_pembeli" class="form-control" autocomplete="tel" placeholder="Opsional"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email_pembeli" class="form-control" autocomplete="email" placeholder="Opsional"></div>
                <div class="form-group"><label>Barang</label>
                    <div class="inventory-lines">
                        <div class="inventory-line">
                            <select name="id_barang[]" class="form-control inventory-item" required>
                                <option value="">-- Pilih Barang --</option>
                                <?php while ($barang = $barang_list->fetch_assoc()): ?>
                                <option value="<?= $barang['id'] ?>" data-price="<?= $barang['harga_jual'] ?>" data-stock="<?= $barang['stok'] ?>"><?= htmlspecialchars($barang['nama_barang']) ?> - stok <?= $barang['stok'] ?> <?= htmlspecialchars($barang['satuan'] ?? '') ?> - Rp <?= number_format($barang['harga_jual'], 0, ',', '.') ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="number" name="jumlah[]" class="form-control inventory-quantity" min="1" value="1" required aria-label="Jumlah barang">
                            <button type="button" class="btn btn-danger btn-sm inventory-remove" aria-label="Hapus baris barang"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm inventory-add"><i class="fas fa-plus"></i> Tambah Barang</button>
                </div>
                <div class="form-group"><label>Metode Pembayaran</label>
                    <select name="jenis_pembayaran" class="form-control payment-method">
                        <option value="tunai">Tunai / Lunas</option>
                        <option value="cicilan">Cicilan</option>
                    </select>
                </div>
                <div class="form-group installment-options" hidden>
                    <label>Tenor Cicilan</label>
                    <select name="tenor_bulan" class="form-control installment-term">
                        <?php foreach ([3, 6, 12] as $term): ?>
                        <option value="<?= $term ?>" data-interest="<?= $bunga_tenor[$term] ?>"><?= $term ?> bulan (bunga <?= number_format($bunga_tenor[$term], 2, ',', '.') ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Total Barang</label><input type="number" class="form-control inventory-total" readonly value="0"></div>
                <div class="form-group installment-summary" hidden>
                    <label>Total Tagihan Setelah Bunga</label><input type="number" class="form-control installment-total" readonly value="0">
                    <small class="installment-monthly"></small>
                </div>
                <div class="form-group"><label class="payment-label">Bayar</label><input type="number" name="bayar" class="form-control payment-amount" required min="0" step="0.01"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalPembayaran">
    <div class="modal">
        <div class="modal-header"><h4>Catat Pembayaran Cicilan</h4><button class="modal-close" onclick="closeModal('modalPembayaran')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id_penjualan" class="payment-transaction-id">
                <p>Transaksi: <strong class="payment-transaction-name"></strong></p>
                <p>Sisa tagihan: <strong class="payment-balance"></strong></p>
                <div class="form-group"><label>Jumlah pembayaran</label><input type="number" name="jumlah_pembayaran" class="form-control payment-entry-amount" min="0.01" step="0.01" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('modalPembayaran')">Batal</button><button type="submit" name="bayar_cicilan" class="btn btn-primary">Simpan Pembayaran</button></div>
        </form>
    </div>
</div>

<template id="inventoryLineTemplate">
    <div class="inventory-line">
        <select name="id_barang[]" class="form-control inventory-item" required>
            <option value="">-- Pilih Barang --</option>
            <?php mysqli_data_seek($barang_list, 0); while ($barang = $barang_list->fetch_assoc()): ?>
            <option value="<?= $barang['id'] ?>" data-price="<?= $barang['harga_jual'] ?>" data-stock="<?= $barang['stok'] ?>"><?= htmlspecialchars($barang['nama_barang']) ?> - stok <?= $barang['stok'] ?> <?= htmlspecialchars($barang['satuan'] ?? '') ?> - Rp <?= number_format($barang['harga_jual'], 0, ',', '.') ?></option>
            <?php endwhile; ?>
        </select>
        <input type="number" name="jumlah[]" class="form-control inventory-quantity" min="1" value="1" required aria-label="Jumlah barang">
        <button type="button" class="btn btn-danger btn-sm inventory-remove" aria-label="Hapus baris barang"><i class="fas fa-trash"></i></button>
    </div>
</template>

<?php require_once '../includes/footer.php'; ?>
