<?php
$title = "Kategori - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($role !== 'admin') {
    header('Location: home.php');
    exit;
}

if (isset($_POST['tambah'])) {
    $nama = escape($_POST['nama_kategori']);
    $keterangan = escape($_POST['keterangan']);

    $sql = "INSERT INTO kategori (nama_kategori, keterangan) VALUES ('$nama', '$keterangan')";
    if (query($sql)) {
        setFlash('success', 'Kategori berhasil ditambahkan!');
        logAktivitas($_SESSION['user_id'], 'Tambah Kategori', "Menambah kategori: $nama");
    } else {
        setFlash('danger', 'Gagal menambahkan kategori!');
    }
    header('Location: kategori.php');
    exit;
}

if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $nama = escape($_POST['nama_kategori']);
    $keterangan = escape($_POST['keterangan']);

    $sql = "UPDATE kategori SET nama_kategori='$nama', keterangan='$keterangan' WHERE id=$id";
    if (query($sql)) {
        setFlash('success', 'Kategori berhasil diupdate!');
        logAktivitas($_SESSION['user_id'], 'Edit Kategori', "Update kategori ID: $id");
    } else {
        setFlash('danger', 'Gagal mengupdate kategori!');
    }
    header('Location: kategori.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    if (query("DELETE FROM kategori WHERE id=$id")) {
        setFlash('success', 'Kategori berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus Kategori', "Hapus kategori ID: $id");
    } else {
        setFlash('danger', 'Gagal menghapus kategori!');
    }
    header('Location: kategori.php');
    exit;
}

$kategori = query("SELECT * FROM kategori ORDER BY id DESC");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Kategori Barang</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus"></i> Tambah Kategori</button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th>Keterangan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $kategori->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                    <td><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editKategori(<?= $row['id'] ?>, '<?= addslashes($row['nama_kategori']) ?>', '<?= addslashes($row['keterangan'] ?? '') ?>')"><i class="fas fa-edit"></i></button>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus kategori ini?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Tambah Kategori</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Nama Kategori</label><input type="text" name="nama_kategori" class="form-control" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" class="form-control" rows="4"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalTambah')">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEdit">
    <div class="modal">
        <div class="modal-header"><h4>Edit Kategori</h4><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group"><label>Nama Kategori</label><input type="text" name="nama_kategori" id="edit_nama" class="form-control" required></div>
                <div class="form-group"><label>Keterangan</label><textarea name="keterangan" id="edit_keterangan" class="form-control" rows="4"></textarea></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editKategori(id, nama, keterangan) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_keterangan').value = keterangan;
    openModal('modalEdit');
}
</script>

<?php require_once '../includes/footer.php'; ?>
