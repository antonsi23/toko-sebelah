<?php
$title = "Pengguna - Toko Online";
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

if ($role !== 'admin') {
    header('Location: home.php');
    exit;
}

if (isset($_POST['tambah'])) {
    $nama = escape($_POST['nama']);
    $username = escape($_POST['username']);
    $password = $_POST['password'];
    $roleUser = in_array($_POST['role'], ['admin', 'kasir']) ? $_POST['role'] : 'kasir';

    if ($password === '') {
        setFlash('danger', 'Password harus diisi!');
        header('Location: users.php');
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (nama, username, password, role) VALUES ('$nama', '$username', '$hash', '$roleUser')";
    if (query($sql)) {
        setFlash('success', 'User berhasil ditambahkan!');
        logAktivitas($_SESSION['user_id'], 'Tambah User', "Menambah user: $username");
    } else {
        setFlash('danger', 'Gagal menambahkan user!');
    }
    header('Location: users.php');
    exit;
}

if (isset($_POST['edit'])) {
    $id = intval($_POST['id']);
    $nama = escape($_POST['nama']);
    $username = escape($_POST['username']);
    $roleUser = in_array($_POST['role'], ['admin', 'kasir']) ? $_POST['role'] : 'kasir';

    $sql = "UPDATE users SET nama='$nama', username='$username', role='$roleUser'";
    if (!empty($_POST['password'])) {
        $sql .= ", password='" . password_hash($_POST['password'], PASSWORD_DEFAULT) . "'";
    }
    $sql .= " WHERE id=$id";

    if (query($sql)) {
        setFlash('success', 'User berhasil diupdate!');
        logAktivitas($_SESSION['user_id'], 'Edit User', "Update user ID: $id");
    } else {
        setFlash('danger', 'Gagal mengupdate user!');
    }
    header('Location: users.php');
    exit;
}

if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    if ($id == $_SESSION['user_id']) {
        setFlash('danger', 'Anda tidak dapat menghapus akun sendiri!');
        header('Location: users.php');
        exit;
    }

    if (query("DELETE FROM users WHERE id=$id")) {
        setFlash('success', 'User berhasil dihapus!');
        logAktivitas($_SESSION['user_id'], 'Hapus User', "Hapus user ID: $id");
    } else {
        setFlash('danger', 'Gagal menghapus user!');
    }
    header('Location: users.php');
    exit;
}

$users = query("SELECT * FROM users ORDER BY id DESC");
?>

<div class="topbar">
    <div class="topbar-left"><h4>Kelola Pengguna</h4></div>
    <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('modalTambah')"><i class="fas fa-plus"></i> Tambah User</button>
    </div>
</div>

<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['role']) ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="editUser(<?= $row['id'] ?>, '<?= addslashes($row['nama']) ?>', '<?= addslashes($row['username']) ?>', '<?= addslashes($row['role']) ?>')"><i class="fas fa-edit"></i></button>
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Apakah Anda yakin ingin menghapus user ini?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalTambah">
    <div class="modal">
        <div class="modal-header"><h4>Tambah User</h4><button class="modal-close" onclick="closeModal('modalTambah')">&times;</button></div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group"><label>Nama</label><input type="text" name="nama" class="form-control" required></div>
                <div class="form-group"><label>Username</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" class="form-control">
                        <option value="admin">Admin</option>
                        <option value="kasir" selected>Kasir</option>
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

<div class="modal-overlay" id="modalEdit">
    <div class="modal">
        <div class="modal-header"><h4>Edit User</h4><button class="modal-close" onclick="closeModal('modalEdit')">&times;</button></div>
        <form method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group"><label>Nama</label><input type="text" name="nama" id="edit_nama" class="form-control" required></div>
                <div class="form-group"><label>Username</label><input type="text" name="username" id="edit_username" class="form-control" required></div>
                <div class="form-group"><label>Password Baru</label><input type="password" name="password" id="edit_password" class="form-control" placeholder="Kosongkan jika tidak ingin ubah"></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" id="edit_role" class="form-control">
                        <option value="admin">Admin</option>
                        <option value="kasir">Kasir</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalEdit')">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(id, nama, username, role) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_password').value = '';
    openModal('modalEdit');
}
</script>

<?php require_once '../includes/footer.php'; ?>
