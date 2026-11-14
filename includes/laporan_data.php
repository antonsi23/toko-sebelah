<?php
function parseTanggalLaporan($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$tanggal || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $tanggal->format('Y-m-d') !== $value) {
        return null;
    }

    return $tanggal;
}

function getPeriodeLaporan(array $input)
{
    $hariIni = new DateTimeImmutable('today');
    $bulanIni = $hariIni->modify('first day of this month');
    $mode = ($input['periode'] ?? '') === 'tanggal' ? 'tanggal' : 'bulan';
    $error = '';

    if ($mode === 'bulan') {
        $bulanDipilih = $input['bulan'] ?? $hariIni->format('Y-m');
        if (!is_string($bulanDipilih) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanDipilih)) {
            $bulanDipilih = $hariIni->format('Y-m');
            $error = 'Bulan tidak valid. Periode bulan ini yang digunakan.';
        }

        $mulai = DateTimeImmutable::createFromFormat('!Y-m-d', $bulanDipilih . '-01');
        $selesai = $mulai->modify('last day of this month');
        $namaBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $label = $namaBulan[(int) $mulai->format('n') - 1] . ' ' . $mulai->format('Y');
    } else {
        $mulaiInput = parseTanggalLaporan($input['tanggal_mulai'] ?? null);
        $selesaiInput = parseTanggalLaporan($input['tanggal_selesai'] ?? null);

        if (!$mulaiInput || !$selesaiInput || $mulaiInput > $selesaiInput) {
            $mulai = $bulanIni;
            $selesai = $hariIni;
            $error = 'Rentang tanggal tidak valid. Periode bulan ini yang digunakan.';
        } else {
            $mulai = $mulaiInput;
            $selesai = $selesaiInput;
        }

        $label = $mulai->format('d/m/Y') . ' - ' . $selesai->format('d/m/Y');
    }

    return [
        'mode' => $mode,
        'bulan' => $mode === 'bulan' ? $bulanDipilih : $hariIni->format('Y-m'),
        'tanggal_mulai' => $mode === 'tanggal' ? $mulai->format('Y-m-d') : $bulanIni->format('Y-m-d'),
        'tanggal_selesai' => $mode === 'tanggal' ? $selesai->format('Y-m-d') : $hariIni->format('Y-m-d'),
        'mulai' => $mulai->format('Y-m-d'),
        'sampai' => $selesai->modify('+1 day')->format('Y-m-d'),
        'label' => $label,
        'error' => $error,
    ];
}

function getDataLaporan(mysqli $conn, array $periode)
{
    $stmt = $conn->prepare('SELECT COALESCE(SUM(total), 0) AS total FROM penjualan WHERE tanggal >= ? AND tanggal < ?');
    $stmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $stmt->execute();
    $totalPenjualan = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare('SELECT COALESCE(SUM(total), 0) AS total FROM pembelian WHERE tanggal >= ? AND tanggal < ?');
    $stmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $stmt->execute();
    $totalPembelian = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $jumlahBarang = $conn->query('SELECT COUNT(*) AS total FROM barang')->fetch_assoc()['total'];
    $stokRendah = $conn->query('SELECT COUNT(*) AS total FROM barang WHERE stok < 10')->fetch_assoc()['total'];

    $stmt = $conn->prepare('SELECT p.*, u.nama AS nama_user FROM penjualan p LEFT JOIN users u ON p.id_user = u.id WHERE p.tanggal >= ? AND p.tanggal < ? ORDER BY p.tanggal DESC LIMIT 10');
    $stmt->bind_param('ss', $periode['mulai'], $periode['sampai']);
    $stmt->execute();
    $transaksiTerbaru = $stmt->get_result();

    return [
        'total_penjualan' => $totalPenjualan,
        'total_pembelian' => $totalPembelian,
        'jumlah_barang' => $jumlahBarang,
        'stok_rendah' => $stokRendah,
        'transaksi_terbaru' => $transaksiTerbaru,
    ];
}
?>