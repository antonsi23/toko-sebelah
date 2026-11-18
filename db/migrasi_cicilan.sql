ALTER TABLE pembelian
    ADD COLUMN total_tagihan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN jenis_pembayaran ENUM('tunai','cicilan') NOT NULL DEFAULT 'tunai' AFTER total_tagihan,
    ADD COLUMN tenor_bulan TINYINT UNSIGNED DEFAULT NULL AFTER jenis_pembayaran,
    ADD COLUMN bunga_persen DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER tenor_bulan,
    ADD COLUMN dibayar DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER bunga_persen;

UPDATE pembelian
SET total_tagihan = total, dibayar = IF(status = 'lunas', total, 0);

ALTER TABLE penjualan
    ADD COLUMN total_tagihan DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN jenis_pembayaran ENUM('tunai','cicilan') NOT NULL DEFAULT 'tunai' AFTER total_tagihan,
    ADD COLUMN tenor_bulan TINYINT UNSIGNED DEFAULT NULL AFTER jenis_pembayaran,
    ADD COLUMN bunga_persen DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER tenor_bulan,
    ADD COLUMN dibayar DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER bayar;

UPDATE penjualan SET total_tagihan = total, dibayar = total;

CREATE TABLE pengaturan_cicilan (
    id TINYINT UNSIGNED PRIMARY KEY,
    bunga_3_bulan DECIMAL(5,2) NOT NULL DEFAULT 0,
    bunga_6_bulan DECIMAL(5,2) NOT NULL DEFAULT 0,
    bunga_12_bulan DECIMAL(5,2) NOT NULL DEFAULT 0
);

INSERT INTO pengaturan_cicilan (id) VALUES (1);

CREATE TABLE pembayaran_pembelian (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pembelian INT NOT NULL,
    id_user INT,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    jumlah DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_pembelian) REFERENCES pembelian(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE pembayaran_penjualan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_penjualan INT NOT NULL,
    id_user INT,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    jumlah DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_penjualan) REFERENCES penjualan(id) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO pembayaran_pembelian (id_pembelian, jumlah)
    SELECT id, dibayar FROM pembelian WHERE dibayar > 0;

INSERT INTO pembayaran_penjualan (id_penjualan, jumlah)
    SELECT id, dibayar FROM penjualan WHERE dibayar > 0;
