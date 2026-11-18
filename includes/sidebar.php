<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">
            <i class="fas fa-store"></i>
        </div>
        <h3>Toko Sebelah</h3>
        <p>Sistem Manajemen Toko</p>
    </div>

    <ul class="sidebar-menu">
        <li class="<?= $current_page == 'home' ? 'active' : '' ?>">
            <a href="home.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="<?= $current_page == 'penjualan' ? 'active' : '' ?>">
            <a href="penjualan.php"><i class="fas fa-cash-register"></i> Penjualan</a>
        </li>
        <li class="<?= $current_page == 'pembelian' ? 'active' : '' ?>">
            <a href="pembelian.php"><i class="fas fa-shopping-cart"></i> Pembelian</a>
        </li>
        <li class="<?= $current_page == 'pembayaran_cicilan' ? 'active' : '' ?>">
            <a href="pembayaran_cicilan.php"><i class="fas fa-money-bill-wave"></i> Pembayaran Cicilan</a>
        </li>
        <li class="<?= $current_page == 'barang' ? 'active' : '' ?>">
            <a href="barang.php"><i class="fas fa-box"></i> Data Barang</a>
        </li>
        <?php if ($role == 'admin'): ?>
        <li class="<?= $current_page == 'kategori' ? 'active' : '' ?>">
            <a href="kategori.php"><i class="fas fa-tags"></i> Kategori</a>
        </li>
        <li class="<?= $current_page == 'supplier' ? 'active' : '' ?>">
            <a href="supplier.php"><i class="fas fa-truck"></i> Supplier</a>
        </li>
        <li class="<?= $current_page == 'users' ? 'active' : '' ?>">
            <a href="users.php"><i class="fas fa-users"></i> Pengguna</a>
        </li>
        <li class="<?= $current_page == 'pengaturan_cicilan' ? 'active' : '' ?>">
            <a href="pengaturan_cicilan.php"><i class="fas fa-percent"></i> Bunga Cicilan</a>
        </li>
        <li class="<?= $current_page == 'laporan' ? 'active' : '' ?>">
            <a href="laporan.php"><i class="fas fa-chart-bar"></i> Laporan</a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= $initial ?></div>
            <div class="user-details">
                <h5><?= htmlspecialchars($nama) ?></h5>
                <span><?= $role ?></span>
            </div>
        </div>
    </div>
</aside>
