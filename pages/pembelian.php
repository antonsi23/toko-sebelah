<?php
$title = "Pembelian - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if (isset($_POST['tambah'])) {
    $id_supplier = intval($_POST['id_supplier']);
    $status = in_array($_POST['status'], ['lunas', 'hutang']) ? $_POST['status'] : 'lunas';
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
        header('Location: pembelian.php');
        exit;
    }

    if ($no_faktur === '') {
        $no_faktur = 'PO-' . date('YmdHis');
    }

    try {
        $conn->begin_transaction();
        $barang_stmt = $conn->prepare('SELECT id, harga_beli FROM barang WHERE id = ? FOR UPDATE');
        $detail_barang = [];
        $total = 0;

        foreach ($jumlah_barang as $id_barang => $jumlah) {
            $barang_stmt->bind_param('i', $id_barang);
            $barang_stmt->execute();
            $barang = $barang_stmt->get_result()->fetch_assoc();
            if (!$barang) {
                throw new RuntimeException('Barang yang dipilih tidak ditemukan.');
            }
            $subtotal = $barang['harga_beli'] * $jumlah;
            $total += $subtotal;
            $detail_barang[] = ['id' => $id_barang, 'jumlah' => $jumlah, 'harga' => $barang['harga_beli'], 'subtotal' => $subtotal];
        }

        $nomor_sementara = 'TMP-' . bin2hex(random_bytes(8));
        $stmt = $conn->prepare('INSERT INTO pembelian (no_faktur, id_supplier, id_user, tanggal, total, status) VALUES (?, ?, ?, NOW(), ?, ?)');
        $stmt->bind_param('siids', $nomor_sementara, $id_supplier, $id_user, $total, $status);
        $stmt->execute();
        $id_pembelian = $conn->insert_id;
        $no_faktur = sprintf('PO-%s-%06d', date('Ymd'), $id_pembelian);
        $nomor_stmt = $conn->prepare('UPDATE pembelian SET no_faktur = ? WHERE id = ?');
        $nomor_stmt->bind_param('si', $no_faktur, $id_pembelian);
        $nomor_stmt->execute();

        $detail_stmt = $conn->prepare('INSERT INTO detail_pembelian (id_pembelian, id_barang, jumlah, harga_beli, subtotal) VALUES (?, ?, ?, ?, ?)');
        $stok_stmt = $conn->prepare('UPDATE barang SET stok = stok + ? WHERE id = ?');
        foreach ($detail_barang as $detail) {
            $detail_stmt->bind_param('iiidd', $id_pembelian, $detail['id'], $detail['jumlah'], $detail['harga'], $detail['subtotal']);
            $detail_stmt->execute();
            $stok_stmt->bind_param('ii', $detail['jumlah'], $detail['id']);
            $stok_stmt->execute();
        }
        $conn->commit();
        setFlash('success', "Transaksi pembelian berhasil ditambahkan. No. faktur: $no_faktur");
        logAktivitas($_SESSION['user_id'], 'Tambah Pembelian', "No faktur: $no_faktur");
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', $error instanceof RuntimeException ? $error->getMessage() : 'Gagal menyimpan transaksi pembelian.');
    }
    header('Location: pembelian.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    try {
        $conn->begin_transaction();
        $detail_stmt = $conn->prepare('SELECT id_barang, jumlah FROM detail_pembelian WHERE id_pembelian = ? FOR UPDATE');
        $detail_stmt->bind_param('i', $id);
        $detail_stmt->execute();
        $details = $detail_stmt->get_result();
        $barang_stmt = $conn->prepare('SELECT stok FROM barang WHERE id = ? FOR UPDATE');
        $stok_stmt = $conn->prepare('UPDATE barang SET stok = stok - ? WHERE id = ?');
        while ($detail = $details->fetch_assoc()) {
            $id_barang = $detail['id_barang'];
            $barang_stmt->bind_param('i', $id_barang);
            $barang_stmt->execute();
            $barang = $barang_stmt->get_result()->fetch_assoc();
            if (!$barang || $barang['stok'] < $detail['jumlah']) {
                throw new RuntimeException('Pembelian tidak dapat dihapus karena sebagian stoknya sudah terpakai.');
            }
            $stok_stmt->bind_param('ii', $detail['jumlah'], $id_barang);
            $stok_stmt->execute();
        }
        $delete_stmt = $conn->prepare('DELETE FROM pembelian WHERE id = ?');
        $delete_stmt->bind_param('i', $id);
        $delete_stmt->execute();
        $conn->commit();
        setFlash('success', 'Transaksi pembelian berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus Pembelian', "Hapus transaksi ID: $id");
    } catch (Throwable $error) {
        $conn->rollback();
        setFlash('danger', 'Gagal menghapus transaksi pembelian!');
    }
    header('Location: pembelian.php');
    exit;
}

$supplier_list = query("SELECT * FROM supplier ORDER BY nama_supplier");
$barang_list = query('SELECT id, kode_barang, nama_barang, harga_beli, stok, satuan FROM barang ORDER BY nama_barang');
$pembelian = query("SELECT p.*, s.nama_supplier, u.nama AS nama_user FROM pembelian p LEFT JOIN supplier s ON p.id_supplier = s.id LEFT JOIN users u ON p.id_user = u.id ORDER BY p.tanggal DESC");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Pembelian</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus"></i> Pembelian Baru</button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>No Faktur</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $pembelian->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['no_faktur']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                    <td><?= htmlspecialchars($row['nama_supplier'] ?? '-') ?></td>
                    <td>Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                    <td>
                        <a href="../print/pembelian.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener" title="Cetak atau simpan PDF" aria-label="Cetak atau simpan PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus transaksi pembelian ini?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Transaksi Pembelian</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST" data-inventory-form>
            <div class="modal-body">
                <div class="form-group"><label>No. Faktur</label><p>Nomor faktur dibuat otomatis saat transaksi disimpan.</p></div>
                <div class="form-group"><label>Supplier</label>
                    <select name="id_supplier" class="form-control" required>
                        <option value="">-- Pilih Supplier --</option>
                        <?php while ($s = $supplier_list->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama_supplier']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group"><label>Barang</label>
                    <div class="inventory-lines">
                        <div class="inventory-line">
                            <select name="id_barang[]" class="form-control inventory-item" required>
                                <option value="">-- Pilih Barang --</option>
                                <?php while ($barang = $barang_list->fetch_assoc()): ?>
                                <option value="<?= $barang['id'] ?>" data-price="<?= $barang['harga_beli'] ?>"><?= htmlspecialchars($barang['nama_barang']) ?> - stok <?= $barang['stok'] ?> <?= htmlspecialchars($barang['satuan'] ?? '') ?> - Rp <?= number_format($barang['harga_beli'], 0, ',', '.') ?></option>
                                <?php endwhile; ?>
                            </select>
                            <input type="number" name="jumlah[]" class="form-control inventory-quantity" min="1" value="1" required aria-label="Jumlah barang">
                            <button type="button" class="btn btn-danger btn-sm inventory-remove" aria-label="Hapus baris barang"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm inventory-add"><i class="fas fa-plus"></i> Tambah Barang</button>
                </div>
                <div class="form-group"><label>Total</label><input type="number" class="form-control inventory-total" readonly value="0"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" class="form-control">
                        <option value="lunas">Lunas</option>
                        <option value="hutang">Hutang</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<template id="inventoryLineTemplate">
    <div class="inventory-line">
        <select name="id_barang[]" class="form-control inventory-item" required>
            <option value="">-- Pilih Barang --</option>
            <?php mysqli_data_seek($barang_list, 0); while ($barang = $barang_list->fetch_assoc()): ?>
            <option value="<?= $barang['id'] ?>" data-price="<?= $barang['harga_beli'] ?>"><?= htmlspecialchars($barang['nama_barang']) ?> - stok <?= $barang['stok'] ?> <?= htmlspecialchars($barang['satuan'] ?? '') ?> - Rp <?= number_format($barang['harga_beli'], 0, ',', '.') ?></option>
            <?php endwhile; ?>
        </select>
        <input type="number" name="jumlah[]" class="form-control inventory-quantity" min="1" value="1" required aria-label="Jumlah barang">
        <button type="button" class="btn btn-danger btn-sm inventory-remove" aria-label="Hapus baris barang"><i class="fas fa-trash"></i></button>
    </div>
</template>

<?php require_once '../includes/footer.php'; ?>
