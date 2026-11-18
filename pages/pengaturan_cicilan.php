<?php
$title = "Pengaturan Bunga Cicilan - Toko Online";
require_once '../includes/header.php';

if ($role !== 'admin') {
    http_response_code(403);
    exit('Akses hanya untuk admin.');
}

if (isset($_POST['simpan'])) {
    $rates = [];
    foreach ([3, 6, 12] as $term) {
        $rate = filter_var($_POST['bunga_' . $term] ?? null, FILTER_VALIDATE_FLOAT);
        if ($rate === false || $rate < 0 || $rate > 100) {
            setFlash('danger', 'Bunga setiap tenor harus berupa angka antara 0 sampai 100%.');
            header('Location: pengaturan_cicilan.php');
            exit;
        }
        $rates[$term] = $rate;
    }

    $stmt = $conn->prepare('UPDATE pengaturan_cicilan SET bunga_3_bulan = ?, bunga_6_bulan = ?, bunga_12_bulan = ? WHERE id = 1');
    $stmt->bind_param('ddd', $rates[3], $rates[6], $rates[12]);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        $check = query('SELECT id FROM pengaturan_cicilan WHERE id = 1');
        if ($check->num_rows === 0) {
            $insert = $conn->prepare('INSERT INTO pengaturan_cicilan (id, bunga_3_bulan, bunga_6_bulan, bunga_12_bulan) VALUES (1, ?, ?, ?)');
            $insert->bind_param('ddd', $rates[3], $rates[6], $rates[12]);
            $insert->execute();
        }
    }
    setFlash('success', 'Pengaturan bunga cicilan berhasil disimpan.');
    header('Location: pengaturan_cicilan.php');
    exit;
}

$settings_result = query('SELECT bunga_3_bulan, bunga_6_bulan, bunga_12_bulan FROM pengaturan_cicilan WHERE id = 1');
$settings = $settings_result->fetch_assoc() ?: [
    'bunga_3_bulan' => 0,
    'bunga_6_bulan' => 0,
    'bunga_12_bulan' => 0,
];
require_once '../includes/sidebar.php';
?>

<div class="topbar"><div class="topbar-left"><h4>Pengaturan Bunga Cicilan</h4></div></div>
<div class="main-content">
    <?= showFlash() ?>
    <div class="content-card">
        <p>Atur bunga flat satu kali untuk setiap tenor. Nilai ini digunakan pada transaksi cicilan baru; transaksi lama tidak berubah.</p>
        <form method="POST">
            <?php foreach ([3, 6, 12] as $term): ?>
            <div class="form-group">
                <label for="bunga_<?= $term ?>">Bunga tenor <?= $term ?> bulan (%)</label>
                <input id="bunga_<?= $term ?>" type="number" name="bunga_<?= $term ?>" class="form-control" min="0" max="100" step="0.01" value="<?= htmlspecialchars((string) $settings['bunga_' . $term . '_bulan']) ?>" required>
            </div>
            <?php endforeach; ?>
            <button type="submit" name="simpan" class="btn btn-primary">Simpan Pengaturan</button>
        </form>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
