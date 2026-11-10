<?php
$title = "Supplier - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($role !== 'admin') {
    header('Location: home.php');
    exit;
}

if (isset($_POST['tambah'])) {
    $nama = escape($_POST['nama_supplier']);
    $alamat = escape($_POST['alamat']);
    $telepon = escape($_POST['telepon']);
    $email = escape($_POST['email']);

    $sql = "INSERT INTO supplier (nama_supplier, alamat, telepon, email) VALUES ('$nama', '$alamat', '$telepon', '$email')";
    if (query($sql)) {
        setFlash('success', 'Supplier berhasil ditambahkan!');
        logAktivitas($_SESSION['user_id'], 'Tambah Supplier', "Menambah supplier: $nama");
    } else {
        setFlash('danger', 'Gagal menambahkan supplier!');
    }
    header('Location: supplier.php');
    exit;
}

if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $nama = escape($_POST['nama_supplier']);
    $alamat = escape($_POST['alamat']);
    $telepon = escape($_POST['telepon']);
    $email = escape($_POST['email']);

    $sql = "UPDATE supplier SET nama_supplier='$nama', alamat='$alamat', telepon='$telepon', email='$email' WHERE id=$id";
    if (query($sql)) {
        setFlash('success', 'Supplier berhasil diupdate!');
        logAktivitas($_SESSION['user_id'], 'Edit Supplier', "Update supplier ID: $id");
    } else {
        setFlash('danger', 'Gagal mengupdate supplier!');
    }
    header('Location: supplier.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    if (query("DELETE FROM supplier WHERE id=$id")) {
        setFlash('success', 'Supplier berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus Supplier', "Hapus supplier ID: $id");
    } else {
        setFlash('danger', 'Gagal menghapus supplier!');
    }
    header('Location: supplier.php');
    exit;
}

$supplier = query("SELECT * FROM supplier ORDER BY id DESC");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Data Supplier</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus"></i> Tambah Supplier</button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Supplier</th>
                    <th>Alamat</th>
                    <th>Telepon</th>
                    <th>Email</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $supplier->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                    <td><?= htmlspecialchars($row['alamat'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['telepon'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['email'] ?? '-') ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editSupplier(<?= $row['id'] ?>, '<?= addslashes($row['nama_supplier']) ?>', '<?= addslashes($row['alamat'] ?? '') ?>', '<?= addslashes($row['telepon'] ?? '') ?>', '<?= addslashes($row['email'] ?? '') ?>')"><i class="fas fa-edit"></i></button>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus supplier ini?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Tambah Supplier</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Nama Supplier</label><input type="text" name="nama_supplier" class="form-control" required></div>
                <div class="form-group"><label>Alamat</label><textarea name="alamat" class="form-control" rows="3"></textarea></div>
                <div class="form-group"><label>Telepon</label><input type="text" name="telepon" class="form-control"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
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
        <div class="modal-header"><h4>Edit Supplier</h4><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group"><label>Nama Supplier</label><input type="text" name="nama_supplier" id="edit_nama" class="form-control" required></div>
                <div class="form-group"><label>Alamat</label><textarea name="alamat" id="edit_alamat" class="form-control" rows="3"></textarea></div>
                <div class="form-group"><label>Telepon</label><input type="text" name="telepon" id="edit_telepon" class="form-control"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSupplier(id, nama, alamat, telepon, email) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_alamat').value = alamat;
    document.getElementById('edit_telepon').value = telepon;
    document.getElementById('edit_email').value = email;
    openModal('modalEdit');
}
</script>

<?php require_once '../includes/footer.php'; ?>
