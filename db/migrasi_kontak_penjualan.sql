ALTER TABLE penjualan
    ADD COLUMN telepon_pembeli VARCHAR(30) DEFAULT NULL AFTER nama_pembeli,
    ADD COLUMN email_pembeli VARCHAR(150) DEFAULT NULL AFTER telepon_pembeli;