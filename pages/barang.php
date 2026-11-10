<?php
$title = "Data Barang - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Tambah Barang
if (isset($_POST['tambah'])) {
    $kode = escape($_POST['kode_barang']);
    $nama = escape($_POST['nama_barang']);
    $kategori = intval($_POST['id_kategori']);
    $harga_beli = floatval($_POST['harga_beli']);
    $harga_jual = floatval($_POST['harga_jual']);
    $stok = intval($_POST['stok']);
    $satuan = escape($_POST['satuan']);

    $sql = "INSERT INTO barang (kode_barang, nama_barang, id_kategori, harga_beli, harga_jual, stok, satuan) 
            VALUES ('$kode', '$nama', $kategori, $harga_beli, $harga_jual, $stok, '$satuan')";

    if (query($sql)) {
        setFlash('success', 'Barang berhasil ditambahkan!');
        logAktivitas($_SESSION['user_id'], 'Tambah Barang', "Menambah barang: $nama");
    } else {
        setFlash('danger', 'Gagal menambahkan barang!');
    }
    header("Location: barang.php");
    exit;
}

// Edit Barang
if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $nama = escape($_POST['nama_barang']);
    $kategori = intval($_POST['id_kategori']);
    $harga_beli = floatval($_POST['harga_beli']);
    $harga_jual = floatval($_POST['harga_jual']);
    $stok = intval($_POST['stok']);
    $satuan = escape($_POST['satuan']);

    $sql = "UPDATE barang SET nama_barang='$nama', id_kategori=$kategori, harga_beli=$harga_beli, 
            harga_jual=$harga_jual, stok=$stok, satuan='$satuan' WHERE id=$id";

    if (query($sql)) {
        setFlash('success', 'Barang berhasil diupdate!');
        logAktivitas($_SESSION['user_id'], 'Edit Barang', "Update barang ID: $id");
    } else {
        setFlash('danger', 'Gagal mengupdate barang!');
    }
    header("Location: barang.php");
    exit;
}

// Hapus Barang
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    if (query("DELETE FROM barang WHERE id=$id")) {
        setFlash('success', 'Barang berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus Barang', "Hapus barang ID: $id");
    } else {
        setFlash('danger', 'Gagal menghapus barang!');
    }
    header("Location: barang.php");
    exit;
}

// Ambil Data
$barang = query("SELECT b.*, k.nama_kategori FROM barang b LEFT JOIN kategori k ON b.id_kategori = k.id ORDER BY b.id DESC");
$kategori_list = query("SELECT * FROM kategori ORDER BY nama_kategori");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Data Barang</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')">
            <i class="fas fa-plus"></i> Tambah Barang
        </button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Harga Beli</th>
                    <th>Harga Jual</th><th>Stok</th><th>Satuan</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $barang->fetch_assoc()): 
                    $stok_class = $row['stok'] < 10 ? 'style="color:var(--danger);font-weight:600;"' : '';
                ?>
                <tr>
                    <td><?= $row['kode_barang'] ?></td>
                    <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                    <td><?= $row['nama_kategori'] ?? '-' ?></td>
                    <td>Rp <?= number_format($row['harga_beli'], 0, ',', '.') ?></td>
                    <td>Rp <?= number_format($row['harga_jual'], 0, ',', '.') ?></td>
                    <td <?= $stok_class ?>><?= $row['stok'] ?></td>
                    <td><?= $row['satuan'] ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editBarang(<?= $row['id'] ?>, '<?= addslashes($row['nama_barang']) ?>', <?= $row['id_kategori'] ?? 0 ?>, <?= $row['harga_beli'] ?>, <?= $row['harga_jual'] ?>, <?= $row['stok'] ?>, '<?= $row['satuan'] ?>', '<?= $row['kode_barang'] ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete()">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Tambah Barang</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Kode Barang</label><input type="text" name="kode_barang" class="form-control" required></div>
                <div class="form-group"><label>Nama Barang</label><input type="text" name="nama_barang" class="form-control" required></div>
                <div class="form-group"><label>Kategori</label>
                    <select name="id_kategori" class="form-control">
                        <option value="0">-- Pilih Kategori --</option>
                        <?php while ($k = $kategori_list->fetch_assoc()): ?>
                        <option value="<?= $k['id'] ?>"><?= $k['nama_kategori'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group"><label>Harga Beli</label><input type="number" name="harga_beli" class="form-control" required></div>
                <div class="form-group"><label>Harga Jual</label><input type="number" name="harga_jual" class="form-control" required></div>
                <div class="form-group"><label>Stok Awal</label><input type="number" name="stok" class="form-control" required></div>
                <div class="form-group"><label>Satuan</label><input type="text" name="satuan" class="form-control" placeholder="pcs, kg, liter, dll"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal">
        <div class="modal-header"><h4>Edit Barang</h4><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <input type="hidden" name="kode_barang" id="edit_kode">
            <div class="modal-body">
                <div class="form-group"><label>Nama Barang</label><input type="text" name="nama_barang" id="edit_nama" class="form-control" required></div>
                <div class="form-group"><label>Kategori</label>
                    <select name="id_kategori" id="edit_kategori" class="form-control">
                        <option value="0">-- Pilih Kategori --</option>
                        <?php mysqli_data_seek($kategori_list, 0); while ($k = $kategori_list->fetch_assoc()): ?>
                        <option value="<?= $k['id'] ?>"><?= $k['nama_kategori'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group"><label>Harga Beli</label><input type="number" name="harga_beli" id="edit_harga_beli" class="form-control" required></div>
                <div class="form-group"><label>Harga Jual</label><input type="number" name="harga_jual" id="edit_harga_jual" class="form-control" required></div>
                <div class="form-group"><label>Stok</label><input type="number" name="stok" id="edit_stok" class="form-control" required></div>
                <div class="form-group"><label>Satuan</label><input type="text" name="satuan" id="edit_satuan" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBarang(id, nama, kategori, harga_beli, harga_jual, stok, satuan, kode) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_kode').value = kode;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_kategori').value = kategori;
    document.getElementById('edit_harga_beli').value = harga_beli;
    document.getElementById('edit_harga_jual').value = harga_jual;
    document.getElementById('edit_stok').value = stok;
    document.getElementById('edit_satuan').value = satuan;
    openModal('modalEdit');
}
</script>

<?php require_once '../includes/footer.php'; ?>