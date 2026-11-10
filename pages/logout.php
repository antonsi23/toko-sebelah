<?php
session_start();
require_once '../includes/koneksi.php';

if (isset($_SESSION['user_id'])) {
    logAktivitas($_SESSION['user_id'], 'Logout', 'User logout dari sistem');
}

session_destroy();
header("Location: ../index.php");
exit;
?>