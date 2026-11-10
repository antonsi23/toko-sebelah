<?php
// Konfigurasi Database
$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "toko_online";

// Membuat koneksi
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Fungsi helper
function query($sql) {
    global $conn;
    return $conn->query($sql);
}

function escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

function generateKode($prefix, $table, $field) {
    global $conn;
    $tahun = date('Y');
    $bulan = date('m');
    $sql = "SELECT MAX($field) as max_kode FROM $table WHERE $field LIKE '$prefix/$tahun/$bulan/%'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    if ($row['max_kode']) {
        $parts = explode('/', $row['max_kode']);
        $no = intval(end($parts)) + 1;
    } else {
        $no = 1;
    }

    return sprintf("%s/%s/%s/%04d", $prefix, $tahun, $bulan, $no);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function showFlash() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $msg = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-$type'>$msg</div>";
    }
    return "";
}

function logAktivitas($id_user, $aktivitas, $detail = '') {
    global $conn;
    $aktivitas = escape($aktivitas);
    $detail = escape($detail);
    $sql = "INSERT INTO log_aktivitas (id_user, aktivitas, detail) VALUES ($id_user, '$aktivitas', '$detail')";
    $conn->query($sql);
}
?>